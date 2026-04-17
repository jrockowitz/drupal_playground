# AI Schema.org JSON-LD Module

# Description

The AI Schema.org JSON-LD module sets up and configures a [JSON Field](https://www.drupal.org/project/json_field) using the [AI Automators](https://project.pages.drupalcode.org/ai/1.3.x/modules/ai_automators/) and [Field Widget Actions](https://www.drupal.org/project/field_widget_actions) modules, allowing content authors to generate and review [Schema.org](http://Schema.org) JSON-LD for content (aka a node) as it is created and updated.

The majority of the module's code configures the Schema.org JSON-LD field for each content type, with a little glue code (aka a hook) to build and modify prompts and responses to and from the AI, and finally to attach a node's Schema.org JSON-LD to the page header.

# Concept

* This is a glue module that leverages AI Automators, Field Widget Actions, and JSON Field to create a Schema.org JSON-LD field populated by an AI.
  * [https://project.pages.drupalcode.org/ai/1.3.x/](https://project.pages.drupalcode.org/ai/1.3.x/)
  * [https://www.drupal.org/project/field\_widget\_actions](https://www.drupal.org/project/field_widget_actions)
  * [https://www.drupal.org/project/json\_field](https://www.drupal.org/project/json_field)
  * [JSON data type added to database schema API | Drupal.org](https://www.drupal.org/node/3389682)
* The module includes a single configuration form.
  * Defining the default prompt used to generate JSON-LD
  * Selecting the support bundle
  * Setting input and output alteration rules.
* For the selected bundles, the module
  * Creates a Schema.org JSON-LD (field\_schemadotorg\_jsonld) field with an AI automator and field widget action to generate Schema.org JSON-LD for the current node.
* The main glue code for the module is
  * hook\_field\_widget\_action\_info\_alter()
    * Adds support for json\_editor to widget types for 'automator\_json' FieldWidgetAction plugin.
  * hook\_page\_attachments()
    * Adds default jsonld
    * Adds breadcrumb jsonld
    * Determine if the current request is the node's canonical route and adds the field\_schemadotorg\_jsonld value to the page's header.
  * hook\_field\_widget\_complete\_WIDGET\_TYPE\_form\_alter()
    * Alter json\_textarea and json\_editor widget type
    * Adds 'Copy JSON-LD' widget below JSON field widget
    * \\Drupal\\schemadotorg\_jsonld\_preview\\SchemaDotOrgJsonLdPreviewBuilder::buildJsonLd
    * @web/modules/sandbox/schemadotorg/modules/schemadotorg\_jsonld\_preview/src/SchemaDotOrgJsonLdPreviewBuilder.php
    * @web/modules/sandbox/schemadotorg/modules/schemadotorg\_jsonld\_preview/js/schemadotorg\_jsonld\_preview.js
    * @web/modules/sandbox/schemadotorg/modules/schemadotorg\_jsonld\_preview/css/schemadotorg\_jsonld\_preview.css
  * hook\_entity\_field\_access()
    * Block edit access if the associate entity (aka node) has not been saved to prevent "The "node" entity cannot have a URI as it does not have an ID" errors.
    * Limit view access to field\_schemadotorg\_jsonld to only users who can update the node.
  * hook\_token\_info() and hook\_token()
    * Provides a `[{entity}:ai_schemadotorg_jsonld:content]` token
    * `[node:ai_schemadotorg_jsonld]` render the node's full display using the site default theme.
    * Classes and nested \<div\> tags should be removed
      * Remove \<div\>\<div\>\</div\>\<div\>  but keep nested  sets of divs \<div\>\<div\>\</div\>\<div\>\</div\>\<div\>
    * Change all root-relative links and URLs to absolute URLs
    * Semantic markup should be preserved

# Notes:

* module label: AI [Schema.org](http://Schema.org) JSON-LD
* module name: ai\_schemadotorg\_jsonld
* namespaces:
  * ai\_schemadotorg\_jsonld
  * AISchemaDotOrgJsonLd
* Initially, the module will only work with nodes, but the APIs should support multiple entity types

# Architectures

*
* ai\_schemadototg\_jsonld.info.yml
  * `ai_automators` and `json_field` are dependenciies
  * `json_field_widget` support should be optional
* services
  * ai\_schemadotorg\_jsonld.builder \- AISchemaDotOrgJsonLdBuilder.php
    * const FIELD\_NAME \= 'field\_schemadotog\_jsonld'
    * public ::addFieldToEntity($entity\_type\_id, $bundle)
    * protected ::createFieldStorage($entity\_type\_id)
      * @config/sync/field.storage.node.field\_schemadotorg\_jsonld
    * protected ::createField($entity\_type\_id, $bundle)
      * @config/sync/field.field.node.article.field_schemadotorg\_jsonld.yml
    * protected ::createAutomator($entity\_type\_id, $bundle)
      * @config/sync/ai_automators.ai_automator.node.article.field\_schemadotorg\_jsonld.default.yml
    * protected ::addFormDisplayComponent($entity\_type\_id, $bundle)
      * @config/sync/entity\_form\_display.node.article.default.yml
      * weight: 99 // Before actions
    * protected ::addViewDisplayComponent($entity\_type\_id, $bundle)
      * @config/sync/entity\_view\_display.node.article.default.yml
      * weight: 99 // Before links
      * Do not include component in other displays
  * AISchemaDotOrgJsonLdBuilderKernelTest
    * ::testAddField
* ai\_schemadotorg\_jsonld.settings
  * node
    * prompt
    * default\_jsonld
    * breadcrumb\_jsonld: boolean
    * bundles
* ai\_schemadotorg\_jsonld.settings form
  * title: AI [Schema.org](http://Schema.org) JSON-LD
  * path: /admin/config/ai/schemadotorg-jsonld
  * name: AiSchemaDotOrgJsonLd
* ai\_schemadotorg\_jsonld.event\_subscriber
  * \\Drupal\\ai\_automators\\Event\\ValuesChangeEvent
  * Make sure the extract the JSON from the response.
  * @web/modules/contrib/ai\_jsonld\_schema\_generator/src/Service/SchemaGeneratorService.php
* ai\_schemadotorg\_jsonld.token.inc
  * hook\_token\_info()
  * hook\_token()
* ai\_schemadotorg\_jsonld.module
  * hook\_field\_widget\_action\_info\_alter()
  * hook\_page\_attachments()
  * hook\_entity\_field\_access()
* composer.json
* [README.md](http://README.md) \- Extract from these notes and research
* Copy from entity\_labels
  * .gitlab-ci.yml
  * phpcs.xml.dist
  * phpstan.neon

## **Configuration workflow**

* Site builder goes to Schema.org JSON-LD settings page
  * /admin/config/ai/schemadotorg-jsonld
* Site builder selects which content types should have the Schema.org JSON-LD field
* Use Table Select (tableselect) displays Content type, Content label, Operations
  * Operations are only for the existing field (Edit, Delete)
  * Operations should open in a modal dialog. (@see /admin/structure/types/manage/article/fields)
  * Content types (aka bundle) with a field\_schemadotorg\_json are checked off and disabled.
* Use \#config\_target must ensure content types with field\_schemadotorg\_json are always set.
* On save for new node bundles calls AISchemaDotOrgJsonLdBuilder::addFieldToEntity
  * Creates field storage
  * Creates field instance
  * Creates AI automator
  * Set the form display component
  * Set the view display component

# Approach

* Manually set up an example of the configuration for Article
* `ddev drush export:config -y;`
* Run a git diff on `cd config/sync; git diff;`  and examine the changes.
* Automate these changes via `AISchemaDotOrgJsonLdBuilder` service
* Create settings page

# Manual Setup

Below are my manual steps for creating and configuring the article:field\_schemadotorg\_jsonld field.

- [ ] Manually alter \\Drupal\\ai\_automators\\Plugin\\FieldWidgetAction\\Json
  * `widget_types: ['json_textarea', 'json_editor'],`
- [ ] Install my AI recipe. The AI recipe basically installs the AI and AI Provider modules with keys and ai\_provider\_openai.settings
  * `ddev install ai`
- [ ] Install [JSON Field](https://www.drupal.org/project/json_field) with [AI Automators](https://project.pages.drupalcode.org/ai/1.3.x/modules/ai_automators/) , and [Field Widget Actions](https://www.drupal.org/project/field_widget_actions)
  * `ddev drush en -y ai_automators field_widget_actions json_field json_field_widget;`
- [ ] Export configuration and commit to local repos in @config/sync
  * `ddev config:export`
- [ ] Add 'JSON field' to Article content type
  * [https://drupal-playground.ddev.site/admin/structure/types/manage/article/fields](https://drupal-playground.ddev.site/admin/structure/types/manage/article/fields)
  * [https://drupal-playground.ddev.site/admin/structure/types/manage/article/fields/node.article.field\_schemadotorg\_jsonld](https://drupal-playground.ddev.site/admin/structure/types/manage/article/fields/node.article.field_schemadotorg_jsonld)
  * **Field label:** Schema.org JSON-LD
  * **Field name:** schemadotorg\_jsonld
  * **Field type:** JSON (raw)
  * **Enable AI Automator:** Yes
  * **Choose AI Automator Type:** LLM: JSON Field
  * **Automator Input Mode:** Advanced Mode (Token)
  * **Automator Prompt (Token):** {See prompt}
  * **Advanced Settings \> Automator Worker:** Field Widget
  * **AI Provider:** Default Advanced JSON model
- [ ] Configure the 'Schema.org JSON-LD' form display
  * [https://drupal-playground.ddev.site/admin/structure/types/manage/article/form-display](https://drupal-playground.ddev.site/admin/structure/types/manage/article/form-display)
  * **Widget:** JSON-specific WYSIWYG editor
  * **Field Widget Actions \> Add New Action:** Automator JSON
  * **Enable Automators:** Yes
  * **Button label:** Generate Schema.org JSON-LD
  * **Enable an Automator \> Automator to use for suggestions:** Schema.org JSON-LD Default
- [ ] Configure the 'Schema.org JSON-LD' view display
  * [https://drupal-playground.ddev.site/admin/structure/types/manage/article/display](https://drupal-playground.ddev.site/admin/structure/types/manage/article/display)
  * **Format:** Pretty

## **Manual Testing**

- [ ] Create Article
  * [https://drupal-playground.ddev.site/node/add/article](https://drupal-playground.ddev.site/node/add/article)
  * **Title:** History of Drupal
  * **Body:** [https://www.drupal.org/about/history](https://www.drupal.org/about/history)
- [ ] Check AI Logs
  * [https://drupal-playground.ddev.site/admin/config/ai/logging/collection](https://drupal-playground.ddev.site/admin/config/ai/logging/collection)

# Default Prompt

```
Generate valid Schema.org JSON-LD for the content below:

# Input

Type: [node:content-type]
URL: [node:url]
Title: [node:title]
Summary: [node:summary]
Image: [node:field_image]

Body:
[node:body]

Content:
[node:ai_schemadotorg_jsonld:content]

Current JSON-LD: (This will be omitted for new content)
[node:field_schemadotorg_json_ld]

# Requirements

## Response

- Return ONLY the JSON-LD object. No explanatory text, no markdown fences, no preamble.
- Output must begin with { and end with }.

## Schema.org JSON-LD
- Use only valid Schema.org types and properties (https://schema.org).
- Set @context to "https://schema.org".
- Set url to the canonical URL provided above.
- Choose the most specific applicable @type for the content type given.
- Use absolute URLs for all links and images.

## Schema.org properties and values

- Try to include all text in the 'Body' and 'Full Content'
- Do not fabricate values or URLs, only include text/values/urls that are in the content.

## Misc

- If Current JSON-LD is provided, preserve any manually curated properties and improve or extend — do not discard existing values without cause.
- Follow Google's Structured Data guidelines: https://developers.google.com/search/docs/appearance/structured-data/intro-structured-data

# Output format

{
  "@context": "https://schema.org",
  "@type": "WebPage",
  "url": "{url}",
  "name": "{title}",
  "description": "{summary}",
  "text": "{content}",
  "image": [
    {
    "@context": "https://schema.org",
    "@type": "ImageObject",
    "contentUrl": "{image:src}",
    "description": "{image:alt}"
    }
  ]
}
```
