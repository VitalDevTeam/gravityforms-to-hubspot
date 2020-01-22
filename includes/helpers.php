<?php
/**
 * This function takes a Gravity Forms Entry and a Gravity Forms Form and
 * returns the entry data as a simple key/value set appropriate for
 * posting to something other than Gravity Forms
 *
 * @param array $entry
 * @param array $form
 * @return array
 */
function gf2hs_entry_to_array($entry, $form) {
	$ret = array_map(function($field) use ($entry, $form) {
		return gf2hs_entry_form_field_value($field, $entry, $form);
	}, $form['fields']);

	return array_reduce($ret, 'array_merge', []);
}

/**
 * Feed this function a GF field, entry, and form to get back an array
 * containing a key/value pair for each input in the field
 *
 * @param GF_Field $field
 * @param GF_Entry $entry
 * @param GF_Form $form
 * @return array
 */
function gf2hs_entry_form_field_value($field, $entry, $form) {
	$ret = [];
	$inputs = isset_and_true($field, 'inputs');

	if (is_array($inputs)) {
		foreach ($inputs as $input) {
			gf2hs_add_entry_field_to_array($entry, $input, $ret);
		}
	} else {
		gf2hs_add_entry_field_to_array($entry, $field, $ret);
	}

	$ret = apply_filters("gf2hs_entry_form_field_value_{$field->type}", $ret, $entry, $form, $field);

	return $ret;
}

/**
 * This is just used to keep gf2hs_entry_form_field_value() dry. Some GF
 * fields have a label/value of their own, and some contain multiple
 * inputs which each have a label/value. This function is the abstact
 * logic that applies to either one.
 */
function gf2hs_add_entry_field_to_array($entry, $field, &$array) {
	$field_id = (string)$field['id'];
	$field_label = gf2hs_get_form_field_label($field);
	$field_value = isset_and_true($entry, $field_id);

	if ($field_value) {
		if ($field_label) {
			$array[$field_label] = $field_value;
		} else {
			$array[] = $field_value;
		}
	}
}

/**
 * The time puts the final formatted value in the id slotted for the
 * overall field, but is *also* split up into multiple fields. The base
 * code defaults to the list, so just return the main field key/value.
 */
add_filter('gf2hs_entry_form_field_value_time', function($value, $entry, $form, $field) {
	$label = $field->label;
	$time = $entry[$field->id];

	return [$label => $time];
}, 10, 4);

/**
 * Multiselect fields return a string like
 * "['Selected Value 1', 'Selected Value 2']", so run it through
 * json_decode() to get an actual array of values.
 */
add_filter('gf2hs_entry_form_field_value_multiselect', function($value) {
	$ret = [];
	foreach ($value as $k=>$v) {
		$ret[$k] = json_decode($v);
	}

	return $ret;
});

add_filter('gf2hs_entry_form_field_value_select', function($value, $entry, $form, $field) {
	$ret = [];

	foreach ($value as $key => $val) {
		$ret[$key] = gf2hs_get_text_from_input_choices($val, $field);
	}

	return $ret;
}, 10, 4);

/**
 * This one might be over-opinionated... Right now a single checkbox field
 * that contains multiple checkboxes comes through as separate key/val
 * pairs for each individual checkbox. This filter changes it to a single
 * key (the overall field) set to the list of checked values.
 *
 *  IN: [['foo' => 'Foo'], ['bar' => 'Bar']]
 * OUT: ['The Field Name' => ['Foo', 'Bar']]
 */
add_filter('gf2hs_entry_form_field_value_checkbox', function($value, $entry, $form, $field) {
	$label = gf2hs_get_form_field_label($field);
	$checked = array_values($value);
	$checked = implode(';', $checked);

	return [$label => $checked];
}, 10, 4);

/**
 * Someone should introduce the Gravity Forms people to JSON.
 */
add_filter('gf2hs_entry_form_field_value_list', function($value) {
	return array_map('unserialize', $value);
});

/**
 * Consent fields have multiple inputs, one for the label, one for the
 * checkbox, and one for the description. We really just want "label => 1"
 * if it's checked.
 */
add_filter('gf2hs_entry_form_field_value_consent', function($value, $entry, $form, $field) {
	$ret = null;

	if (isset_and_true($value, $field->label)) {
		$ret = [$field->{'checkboxLabel'} => 1];
	}

	return $ret;
}, 10, 4);

/**
 * Pass in a field to get the label. Seems like you shouldn’t need a
 * function for this since there's $field['label'], but this will let you
 * use filters/overrides
 *
 * @param GF_Field $field
 * @return string
 */
function gf2hs_get_form_field_label($field) {
	$label = isset_and_true($field, 'label');

	if ($custom_label = isset_and_true($field, 'customLabel')) {
		$label = $custom_label;
	}

	if ($admin_label = isset_and_true($field, 'adminLabel')) {
		$label = $admin_label;
	}

	return $label;
}

/**
 * Given a value and a Gravity Forms Field that has choices, return the
 * label associated with the given value, if no match is found this will
 * just send back the value it was given.
 *
 * @param string $value
 * @param GF_Field $input
 * @return string
 */
function gf2hs_get_text_from_input_choices($value, $input) {
	$ret = $value;
	$choices = isset_and_true($input, 'choices');

	if (!$choices) {
		return $ret;
	}

	$selected = array_filter($choices, function($c) use ($value) {
		return $value === $c['value'];
	});
	$selected = array_shift($selected);

	if ($selected) {
		$ret = $selected['text'];
	}

	return $ret;
}
