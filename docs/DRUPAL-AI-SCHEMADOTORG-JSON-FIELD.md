
Concept


- Content types get a field_schemadotorg field that is a json_field
- JSON field use automator to generate the Schema.org markup
- Field widget actions is use to generate the JSON-LD for review
- Schema.org JSON-LD can be validated manually
- Schemma.org JSON-LD is displayed to users who can update the content
- Schemma.org JSON-LD is interested at the top of the page.

Recipe

- Add field_schemadotorg to every content type as the last field.
- Provides a default prompt with a template for additional property mappings.

Module

Names
- AI: Schema.org JSON Field module
- ai_schemadotorg_json_field
- ai_schemadotorg_json_field_recipe

- Hide/show the JSON-LD field based on the user's role.
- Add a JSON-LD field to the page.
- Alters the JSON field to include validation links
- Add token to serialize the node's data as JSON for the prompt.
- [node:json]

TODO

- https://www.drupal.org/project/json_field
- https://project.pages.drupalcode.org/distributions_recipes/config_action_list.html#createforeach-createforeachifnotexists
