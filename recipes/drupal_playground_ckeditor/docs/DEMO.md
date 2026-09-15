# DEMO

## Goals

- Leverage Drupal's Recipe API to set up a great instance of CKEditor.
- Walk-through CKEditor modules and add-ons which improve the UX/UI.
- Provide a thought-provoking discussion about CKEditor.


## Decisions

Use an HTML editor or a page builder depending on the use case.

Use an HTML Editor (CKEditor) for...

- Long form mostly text documents
- Documents that require simple embedded elements which include images, videos, quotes, etc…
- Content that could be redistributed via an API

Use a page builder (i.e. Layout, Mercury Editor, Experience Builder) for...

- Multi-column layouts on a webpage's, specifically landing page.
- Webpages that require complex widgets including forms, slideshows, tabs, accordions, etc…
- Content that is not generally distributed via an API


## Installation

### Install the 'CKEditor Recipe' sandbox module

@see https://www.drupal.org/docs/extending-drupal/drupal-recipes
@see https://www.drupal.org/docs/extending-drupal/installing-sandbox-modules

```json
{
  "repositories": {
    "recipes/ckeditor_recipe": {
      "type": "vcs",
      "url": "https://git.drupalcode.org/sandbox/jrockowitz-3510477.git"
    }
  },
  "require": {
    "recipes/ckeditor_recipe": "*"
  },
  "extra": {
    "installer-paths": {
      "recipes/{$name}": [
        "type:drupal-recipe"
      ]
    }
  }
}
```

### Install plain vanilla standard Drupal site

```bash
ddev drush -yv site-install --site-name="CKEditor Recipe Demo";\
ddev drush -y config-set system.site slogan 'A recipe to make CKEditor great!';
```

### Enable contrib modules

```bash
ddev drush en -y navigation devel devel_generate;
```

### Apply the CKEditor recipe

```bash
ddev drush -y recipe ../recipes/ckeditor_recipe; ddev drush cr; ddev drush cr;
```

### Generate content and media

```bash
ddev drush devel-generate:content --bundles=article 3;\
ddev drush devel-generate:media --media-types=image 3;
```

### Open 'Add page' node edit form

```bash
ddev drush user-login /node/add/page;
```

### Open examples and configuration forms

```bash
ddev launch /ckeditor-examples;\
ddev launch /admin/config/content/formats/manage/full_html;\
ddev launch /admin/config/content/linkit;\
ddev launch /admin/config/content/embed;\
ddev launch /admin/config/content/entity_browser;\
ddev launch https://www.drupal.org/project/embedded_content;\
ddev launch /admin/config/content/embedded-content/button;\
ddev launch /admin/config/ckeditor5-templates/content-templates;
ddev launch /admin/config/development/asset-injector/css/node_browser_iframe;
```
