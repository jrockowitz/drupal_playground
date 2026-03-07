# TODO

Create recipes from `.ddev/commands/web/install`

Move the install and configuration from `.ddev/commands/web/install`

The `.ddev/commands/web/install` command may  need to run some drush commands

Create a composer.json in each recipe and add it to the /composer.json

Every reciped should depend on the `drupal_playground_base` recipe

Create a `drupal_playground_base` recipe
- With the below modules
  - "drupal/key": "^1",
  - "drupal/token": "^1",
- Installs media media_library key token

Create a `drupal_playground_admin` recipe
- With the below modules
  - "drupal/coffee": "^2",
  - "drupal/dashboard": "^2",
  - "drupal/gin": "^5",
  - "drupal/gin_toolbar": "^3",
  - "drupal/navigation_extra_tools": "^1",

Create a `drupal_playground_devel` recipe
- With the below modules
  "drupal/devel": "^5"

Create a `drupal_playground_translation` recipe
- Install language content_translation locale config_translation
- Enablew Spansish translation


