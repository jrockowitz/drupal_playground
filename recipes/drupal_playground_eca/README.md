## Recipe: User login redirect to /admin/content

ID: kw7qjd6lx4bp



### Installation

```shell
## Import recipe
composer require drupal/kw7qjd6lx4bp

# Apply recipe with Drush (requires version 13 or later):
drush recipe ../recipes/kw7qjd6lx4bp

# Apply recipe without Drush:
cd web && php core/scripts/drupal recipe ../recipes/kw7qjd6lx4bp

# Rebuilding caches is optional, sometimes required:
drush cr
```