# ClinicalTrials.gov Trials Recipe Design

## Goal

Add a `drupal_playground_trials` recipe that can be installed through `ddev install trials-setup` and provisions the ClinicalTrials.gov workflow by invoking a new `clinical_trials_gov.settings:setUp` config action.

## Scope

The recipe should:

- Install `readonly_field_widget`
- Install `clinical_trials_gov`
- Install `clinical_trials_gov_report`
- Carry the required Composer dependencies in its `composer.json`
- Invoke a `clinical_trials_gov.settings:setUp` config action with recipe-provided overrides

The config action should:

- Require `query`
- Allow recipe input to override writable `clinical_trials_gov.settings` values
- Run the full ClinicalTrials.gov setup workflow

## Architecture

Introduce a new shared service named `ClinicalTrialsGovSetupManager` with one public method:

```php
public function setUp(array $overrides): array
```

This service will own the imperative setup workflow currently embedded in the Drush command. It will:

1. Validate that `query` is present and non-empty
2. Apply supported config overrides to `clinical_trials_gov.settings`
3. Discover query paths from the configured query
4. Save `query_paths`
5. Build default field mappings
6. Save field mappings
7. Create the configured content type
8. Create the configured fields and displays
9. Update the migration definition
10. Return summary data for callers

Both the Drush command and the new config action plugin will call this service.

## Config Override Rules

The config action should accept arbitrary keys from the recipe payload, but only persist known writable settings intentionally supported by setup.

Accepted overrides:

- `query`
- `type`
- `field_prefix`
- `readonly`
- `title_path`
- `required_paths`

Internally managed values that should be recomputed during setup instead of trusted from recipe input:

- `query_paths`
- `fields`

This keeps the recipe flexible without allowing stale derived data to bypass setup logic.

## Recipe Design

Create a new recipe directory:

- `recipes/drupal_playground_trials/recipe.yml`
- `recipes/drupal_playground_trials/composer.json`
- `recipes/drupal_playground_trials/README.md`

The recipe will declare the required modules and invoke the config action using data shaped like:

```yaml
config:
  actions:
    clinical_trials_gov.settings:
      setUp:
        query: 'query.cond=Cancer&query.locn=New%20York&filter.overallStatus=RECRUITING'
```

The recipe Composer file should include the same package requirements needed to ensure the recipe can install the required modules in a fresh site.

## Refactor Impact

`ClinicalTrialsGovCommands::setup()` should be reduced to:

1. Build the overrides array from CLI input
2. Call `ClinicalTrialsGovSetupManager::setUp()`
3. Print the returned summary

This avoids duplicating setup orchestration in the new config action plugin.

## Error Handling

- Missing `query` should throw a clear exception
- Unknown override keys should be ignored rather than written blindly
- Setup should continue to rely on existing managers for discovery, entity creation, and migration generation

## Testing

Add coverage for:

- The new setup manager service
- The config action plugin requiring `query`
- The config action applying overrides and running full setup
- The Drush command delegating to the setup manager
- The new recipe structure and expected config action wiring where practical

## Notes

- Do not trust recipe-provided `query_paths` or `fields`
- Keep all setup orchestration in the new service, not split between Drush and the config action
- Preserve current generated-field behavior, readonly handling, and migration generation semantics
