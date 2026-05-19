# Drupal Playground Webform Setup

Installs the Webform module, supporting contrib integrations, and the regular
Webform example modules for Drupal Playground.

## What It Includes

- Webform core and builder modules such as `webform`, `webform_ui`,
  `webform_templates`, and `webform_submission_log`
- Supporting integrations used by the source demo install, including
  `address`, `captcha`, `image_captcha`, `honeypot`, `imce`, `recaptcha`,
  `entity_print`, and client-side validation support
- Webform example modules such as `webform_examples`,
  `webform_examples_accessibility`, `webform_example_remote_post`,
  `webform_example_element`, `webform_example_composite`,
  `webform_example_handler`, `webform_example_custom_form`, and
  `webform_example_variant`

## What It Avoids

- No admin theme changes
- No site name or slogan changes
- No verbose logging or devel dumper changes
- No test-only Webform modules

## Installation

```shell
ddev install webform-setup
```
