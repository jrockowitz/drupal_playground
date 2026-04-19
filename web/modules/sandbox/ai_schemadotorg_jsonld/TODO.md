Move 'copy_jsonld' button.
from \Drupal\ai_schemadotorg_jsonld\Hook\AiSchemaDotOrgJsonLdFieldHooks::fieldWidgetSingleElementFormAlter
to \Drupal\ai_schemadotorg_jsonld\Hook\AiSchemaDotOrgJsonLdFieldHooks::fieldWidgetCompleteFormAlter

Change label from 'Copy Schema.org JSON-LD' to 'Copy JSON-LD'

Separate the description and message from the button so that the button can appear before 'Edit prompt'
The description should be before all buttons and the message should be after all buttons.

Testing

- Improve test performance.

Bugs

- Fix prompt dialog closing

Improve prompt management

- Create a shared `[ai_schemadotorg_jsonld:requirements]`

JSON-LD

- Create a single @graph block for all JSON-LD output.
- Default JSON per bundle.

Hooks

- hook_ai_schemadotorg_jsonld_prompt_alter()
