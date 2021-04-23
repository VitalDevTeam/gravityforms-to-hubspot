<?php
if (!defined('ABSPATH')) {
	exit;
}

GFForms::include_addon_framework();

class GF2HS extends GFAddOn {

	protected $_version = GF2HS_VERSION;
	protected $_min_gravityforms_version = '2.4';
	protected $_slug = 'gf2hs';
	protected $_path = 'gravityforms-to-hubspot/gravityforms-to-hubspot.php';
	protected $_full_path = __FILE__;
	protected $_title = 'Gravity Forms to HubSpot';
	protected $_short_title = 'Gravity Forms to HubSpot';

	private static $_instance = null;

	public static function get_instance() {
		if (self::$_instance == null) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Handles hooks.
	 */
	public function init() {
		parent::init();

		add_filter(
			'gform_pre_render', function($form) {
				$this->update_global_contact();
				return $form;
			}
		);

		add_action(
			'gform_after_submission', function($entry, $form) {
				$this->after_submission($entry, $form);
			}, 10, 2
		);
	}

	/**
	 * Configures the settings which should be rendered on the add-on settings tab.
	 *
	 * @todo Add validation via is_valid_setting callback. Just check that it's a valid string and stripped of any bad stuff.
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {
		return [
			[
				'title'  => esc_html__( 'Settings', 'gravityforms-to-hubspot' ),
				'fields' => [
					[
						'name'              => 'portal_id',
						'label'             => esc_html__( 'HubSpot Portal ID', 'gravityforms-to-hubspot' ),
						'placeholder'       => '1234567',
						'type'              => 'text',
						'instructions'      => 'Portal ID can be found by logging into users Hubspot account',
						'class'             => 'medium',
						'feedback_callback' => [$this, 'is_valid_setting'],
					],
					[
						'name'              => 'apikey',
						'label'             => esc_html__( 'HubSpot API Key', 'gravityforms-to-hubspot' ),
						'type'              => 'text',
						'class'             => 'medium',
						'feedback_callback' => [$this, 'is_valid_setting'],
					],
				],
			],
		];
	}

	/**
	 * Configures the individual form settings
	 *
	 * @return array
	 */
	public function form_settings_fields( $form ) {

		$opts = array_map(
			function ($hs_form) use ($form) {

				return [
					'label' => esc_html($hs_form['name']),
					'value' => $hs_form['guid'],
				];

			}, $this->get_hubspot_forms()
		);

		// Alphabetize options by label
		usort(
			$opts, function($a, $b) {
				return $a['label'] <=> $b['label'];
			}
		);

		return [
			[
				'title'  => esc_html__( 'Gravity Forms to HubSpot Settings', 'gravityforms-to-hubspot' ),
				'fields' => [
					[
						'label'   => esc_html__( 'HubSpot Form', 'gravityforms-to-hubspot' ),
						'type'    => 'select',
						'name'    => 'hubspot_form_id',
						'tooltip' => esc_html__( 'Select a HubSpot Form to map this to. If you need to map this form’s fields to a property with a different name, use the Admin Field Label setting on that field.', 'gravityforms-to-hubspot' ),
						'choices' => array_merge(
							[
								[
									'label' => '-- Choose form --',
									'value' => '',
								],
							], $opts
						),
					],
				],
			],
		];
	}

	/**
	 * The feedback callback for the 'mytextbox' setting on the plugin settings page and the 'mytext' setting on the form settings page.
	 *
	 * @param string $value The setting value.
	 *
	 * @return bool
	 */
	public function is_valid_setting( $value ) {
		return strlen( $value ) < 10;
	}

	/**
	 * This function takes a Gravity Forms Entry and a Gravity Forms Form and
	 * returns the entry data as a simple key/value set appropriate for
	 * posting to something other than Gravity Forms
	 *
	 * @param array $entry
	 * @param array $form
	 * @return array
	 */
	public function gform_entry_to_array($entry, $form) {
		$ret = array_map(
			function($field) use ($entry, $form) {
				return gf2hs_entry_form_field_value($field, $entry, $form);
			}, $form['fields']
		);

		return array_reduce($ret, 'array_merge', []);
	}

	/**
	 * Undocumented function
	 *
	 * @todo Add function documentation
	 *
	 * @return void
	 */
	public function update_global_contact() {
		if ($hubspot_contact = static::get_hubspot_contact()) {
			add_filter(
				'gform_field_value', function($value, $field, $name) use ($hubspot_contact) {
					$contact_properties = $hubspot_contact['properties'];

					if (!$name || !isset_and_true($field, 'allowsPrepopulate')) {
						return $value;
					}

					if ($contact_value = isset_and_true($contact_properties, $name)) {
						$value = isset_and_true($contact_value, 'value');

						//Values are saved to HubSpot using the TEXT, so I need to
						//map that back to the value where applicable
						if ($value && ($choices = isset_and_true($field, 'choices'))) {
							$choice = array_filter(
								$choices, function($c) use ($value) {
									return $c['text'] == $value;
								}
							);

							if (count($choice)) {
								$choice = array_shift($choice);
								$value = $choice['value'];
							}
						}
					}

					return $value;
				}, 10, 4
			);
		}
	}

	/**
	 * Undocumented function
	 *
	 * @todo Add function documentation
	 *
	 * @param boolean $property
	 * @return void
	 */
	public function get_hubspot_contact($property = false) {
		global $hubspot_contact;

		if ($hubspot_contact) {
			return $hubspot_contact;
		}

		if ($hutk = isset_and_true($_COOKIE, 'hubspotutk')) {
			$endpoint = sprintf('contacts/v1/contact/utk/%s/profile', $hutk);

			$query = [
				'propertyMode' => 'value_only',
			];

			if ($property) {
				$query['property'] = $property;
			}

			$hubspot_contact = static::hubspot_api_get($endpoint, $query);
		}

		return $hubspot_contact;
	}

	/**
	 * Wrapper around hubspot_api_get for easy-peasy list of forms
	 *
	 * @return array
	 */
	public function get_hubspot_forms() {
		return $this->hubspot_api_get('forms/v2/forms/');
	}

	/**
	 * Bare-bones function to simplify basic operations with the HubSpot API.
	 * Note this is ONLY good for simple GET requests using hapikey.
	 *
	 * @todo Cache this data?
	 *
	 * @param String $endpoint
	 * @param array $query
	 * @return array
	 */
	public function hubspot_api_get($endpoint, $query = []) {
		$ret = [];
		$api_host = 'api.hubapi.com';
		$settings = get_option('gravityformsaddon_gf2hs_settings');
		if ($settings && isset($settings['apikey'])) {
			$api_key = $settings['apikey'];
		}

		if ($api_key) {
			$query_string = http_build_query(
				array_merge(
					[
						'hapikey' => $api_key,
					], $query
				)
			);

			$api_url = sprintf('https://%s/%s?%s', $api_host, $endpoint, $query_string);

			$ch = curl_init($api_url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$json_data = curl_exec($ch);
			curl_close($ch);

			$ret = json_decode($json_data, true);
		}

		return $ret;
	}

	/**
	 * Given a url and page title, generate an object used for the hscontext field
	 * in a HubSpot form data payload.
	 *
	 * @param string $url
	 * @param string $title
	 * @return array
	 */
	public function get_hubspot_context_json($url, $title) {
		$hs_context = [
			'hutk'      => isset_and_true($_COOKIE, 'hubspotutk'),
			'ipAddress' => isset_and_true($_SERVER, 'REMOTE_ADDR'),
			'pageUrl'   => $url,
			'pageName'  => $title,
		];

		return json_encode($hs_context);
	}

	/**
	 * Post an array of key/value pairs to the HubSpot form corresponding to the
	 * specified guid.
	 *
	 * @param array $post_data
	 * @param string $form_id
	 * @return string
	 */
	public function send_data_to_hubspot_form($post_data, $form_id) {
		$settings = get_option('gravityformsaddon_gf2hs_settings');
		if ($settings && isset($settings['portal_id'])) {
			$portal_id = $settings['portal_id'];
		}

		if (!$portal_id) {
			if (function_exists('write_log')) {
				write_log('Can’t post to HubSpot because I dont have a portal ID!');
			}
			return false;
		}

		$endpoint = sprintf(
			'https://forms.hubspot.com/uploads/form/v2/%s/%s',
			$portal_id,
			$form_id
		);

		$str_post = http_build_query($post_data);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $str_post);
		curl_setopt($ch, CURLOPT_URL, $endpoint);
		curl_setopt(
			$ch, CURLOPT_HTTPHEADER, [
				'Content-Type: application/x-www-form-urlencoded',
			]
		);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$response    = curl_exec($ch);
		$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		curl_close($ch);

		return $response;
	}

	/**
	 * Handles actions after form submission
	 *
	 * @param object $entry The entry that was just created.
	 * @param array  $form  The current form
	 * @return void
	 */
	public function after_submission($entry, $form) {
		$form_guid = rgars($form, 'gf2hs/hubspot_form_id');

		if (!$form_guid) {
			return;
		}

		$post_id = $entry['post_id'];
		$url = get_the_permalink($post_id);
		$title = get_the_title($post_id);

		$post_data = array_merge(
			gf2hs_entry_to_array($entry, $form), [
				'hs_context' => $this->get_hubspot_context_json($url, $title),
			]
		);

		if (rgar( $entry, 'status' ) !== 'spam') {
			$this->send_data_to_hubspot_form($post_data, $form_guid);
		}

	}

}
