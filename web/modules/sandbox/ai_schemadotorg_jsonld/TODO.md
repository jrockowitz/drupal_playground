Improve tests

- Add // Check that... comments tests to describe the assertion blocks.

Improve test coverage for the prompt form.
- \Drupal\Tests\ai_schemadotorg_jsonld\Functional\AiSchemaDotOrgJsonLdPromptFormTest
- Don't check that the route exists.
- Don't check that the token link is present.
- Check that only user with 'administer site configuration' permission can access the form.
- Check that prompt form loads with the expected values.
- Check that submiting the prompt form updates the automator values.
- Check that 'edit_prompt' setting with hide/show the link to node edit form.

Simplify \Drupal\Tests\ai_schemadotorg_jsonld\Functional\AiSchemaDotOrgJsonLdSettingsFormTest

- Only test node and media entity types.
- Remove unneeded modules
- Remove extraneous assertions like checking table widths and messages.
- We want to test the functionality of the form and make it easy to update minor things like labels without breaking tests.
- // Check that enabling entity types works as expected.
- // Check that entity types with field_ai_schemadotorg_jsonld field are disabled via 'Enabled entity types'.
- // Check that checking off node bundles works as expected.


Testing

- Improve test performance.

Copy review.

- Improve code.


Improve prompt management

- Create a shared `[ai_schemadotorg_jsonld:requirements]`

JSON-LD

- Create a single @graph block for all JSON-LD output.
- Default JSON per bundle.

Hooks

- hook_ai_schemadotorg_jsonld_prompt_alter()
