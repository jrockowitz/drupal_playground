## Recipe: User login redirect to /admin/content

ID: drupal_playground_eca



### Installation

```shell
## Import recipe
composer require drupal/drupal_playground_eca

# Apply recipe with Drush (requires version 13 or later):
drush recipe ../recipes/drupal_playground_eca

# Apply recipe without Drush:
cd web && php core/scripts/drupal recipe ../recipes/drupal_playground_eca

# Rebuilding caches is optional, sometimes required:
drush cr
```
