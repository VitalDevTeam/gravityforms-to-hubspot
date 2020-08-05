# Gravity Forms to Hubspot
Connects Gravity Forms to HubSpot. Form submissions are sent to selected HubSpot form.

## Requirements

- [Gravity Forms](https://www.gravityforms.com/)
- [Contact Form Builder for WordPress](https://wordpress.org/plugins/leadin/)


## Changelog

### 1.1
- Cast field ID to string before adding to array
- Include admin field labels if they are utilized
- Updates to consent field logic to include labels
- Prevent password fields from sending data
- Add file field formatting
- Prevent fatal error if portal ID does not exist

### 1.2
- Prevent spam submissions from going to HubSpot