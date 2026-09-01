# Development

Install Drupal, apply the recipe, and log in
```bash
ddev drush -yv site-install --site-name="CKEditor Recipe Demo";\
ddev drush -y recipe ../recipes/ckeditor_recipe;\
ddev drush cr; ddev drush cr;\
ddev drush uli /admin/config/content/formats/manage/full_html;
```

Copy exported config into snapshot directory so that we can do a diff.
```bash
cd schemadotorg_ddev;\
ddev drush config:export -y;\
cp -rf web/sites/default/files/sync/core.extension.yml recipes/ckeditor_recipe/docs/snapshot/core.extension.yml;\
cp -rf web/sites/default/files/sync/filter.format.full_html.yml recipes/ckeditor_recipe/docs/snapshot/filter.format.full_html.yml;\
cp -rf web/sites/default/files/sync/editor.editor.full_html.yml recipes/ckeditor_recipe/docs/snapshot/editor.editor.full_html.yml;
```
