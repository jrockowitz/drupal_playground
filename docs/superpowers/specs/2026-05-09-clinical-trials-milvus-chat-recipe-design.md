# Clinical Trials Milvus Chat Recipe Design

## Goal

Split the Olivero DeepChat UI from `clinical_trials_gov_recipe_milvus` into a dedicated `clinical_trials_gov_recipe_milvus_chat` recipe and expose it through `ddev install trials-chat`.

## Scope

This design covers:

- Creating a new `clinical_trials_gov_recipe_milvus_chat` recipe package.
- Moving the Olivero chat asset and block configuration into that new recipe.
- Updating the Milvus recipe so it keeps backend search and assistant setup only.
- Adding a `trials-chat` DDEV install preset that assumes `trials-milvus` is already installed.
- Updating recipe and preset documentation to explain the new split.

This design does not cover:

- Changing Milvus indexing behavior.
- Changing the AI assistant, Search API, or vector database configuration.
- Adding a new combined preset that installs both backend and chat UI together.
- Supporting themes other than Olivero.

## Current State

`clinical_trials_gov_recipe_milvus` currently mixes two responsibilities:

- Backend Milvus and AI configuration:
  - AI assistant configuration
  - AI Search and Search API configuration
  - Milvus provider settings
  - Milvus recipe content
- Olivero-specific chat presentation:
  - `asset_injector.css.trials_milvus_olivero.yml`
  - `asset_injector.js.trials_milvus_olivero.yml`
  - `block.block.olivero_trials_milvus_chat.yml`

That makes the recipe less composable than it needs to be. A site that wants Milvus indexing and assistant behavior but does not yet want the Olivero chat block cannot install those concerns separately.

## Proposed Architecture

Introduce a dedicated `clinical_trials_gov_recipe_milvus_chat` recipe with a narrow responsibility: add the Olivero chat UI on top of an existing Milvus-backed clinical trials setup.

The split will be:

- `clinical_trials_gov_recipe_milvus`
  - Owns backend Milvus search and assistant setup only.
  - Continues to install the required modules and import non-UI configuration.
  - No longer imports the Olivero asset injector config or block placement config.
- `clinical_trials_gov_recipe_milvus_chat`
  - Owns only the Olivero chat UI layer.
  - Imports the moved asset injector and block configuration.
  - Assumes the Milvus backend recipe has already been applied.

## New Recipe Structure

Create a new recipe directory:

- `recipes/clinical_trials_gov_recipe_milvus_chat/composer.json`
- `recipes/clinical_trials_gov_recipe_milvus_chat/recipe.yml`
- `recipes/clinical_trials_gov_recipe_milvus_chat/README.md`
- `recipes/clinical_trials_gov_recipe_milvus_chat/config/asset_injector.css.trials_milvus_olivero.yml`
- `recipes/clinical_trials_gov_recipe_milvus_chat/config/asset_injector.js.trials_milvus_olivero.yml`
- `recipes/clinical_trials_gov_recipe_milvus_chat/config/block.block.olivero_trials_milvus_chat.yml`

The package name should be:

- `drupal/clinical_trials_gov_recipe_milvus_chat`

The root Composer recipe metadata should register this recipe as a path package alongside the existing clinical trials recipes.

## Recipe Responsibilities

### Backend Milvus Recipe

`clinical_trials_gov_recipe_milvus` should continue to own:

- `ai_agents.ai_agent.trials_milvus_agent.yml`
- `ai_assistant_api.ai_assistant.trials_milvus_assistant.yml`
- `ai_search.index.trials_milvus.yml`
- `ai_vdb_provider_milvus.settings.yml`
- `search_api.index.trials_milvus.yml`
- `search_api.server.trials_milvus.yml`
- Any recipe content already used to demonstrate Milvus-backed trial discovery

It should stop importing:

- `asset_injector.css.trials_milvus_olivero.yml`
- `asset_injector.js.trials_milvus_olivero.yml`
- `block.block.olivero_trials_milvus_chat.yml`

### Chat UI Recipe

`clinical_trials_gov_recipe_milvus_chat` should own only:

- `asset_injector.css.trials_milvus_olivero.yml`
- `asset_injector.js.trials_milvus_olivero.yml`
- `block.block.olivero_trials_milvus_chat.yml`

Its README should clearly state that this recipe is additive and assumes:

- `clinical_trials_gov_recipe_milvus` is already installed.
- The active site is using Olivero where the block placement and assets make sense.

## DDEV Install Command Changes

Add a new preset to `.ddev/commands/web/install`:

- `trials-chat`

Behavior:

- Print a message that the Clinical Trials chat recipe is being applied.
- Run `drush recipe ../recipes/clinical_trials_gov_recipe_milvus_chat`.
- Assume `trials-milvus` has already been installed.
- Do not reapply the AI recipe, the ClinicalTrials.gov setup recipe, or the Milvus backend recipe.
- Do not clear or rebuild the Milvus index.

The install command help text should also list `ddev install trials-chat` in the examples.

## Documentation Changes

Update the recipe READMEs so the split is obvious:

- `clinical_trials_gov_recipe_milvus/README.md`
  - Reframe this as the Milvus backend and assistant recipe.
  - Remove wording that implies the Olivero chat UI always ships with this recipe.
  - Explain that the visible chat block now comes from `clinical_trials_gov_recipe_milvus_chat`.
- `clinical_trials_gov_recipe_milvus_chat/README.md`
  - Explain that this recipe adds the Olivero DeepChat interface.
  - Document `ddev install trials-chat`.
  - State the prerequisite that `trials-milvus` must already be installed.

## Data Flow and Dependencies

The intended layering after the split is:

1. `ddev install trials-milvus`
   - Provides the AI prerequisites already wired in the preset.
   - Applies `clinical_trials_gov_recipe_setup`.
   - Applies `clinical_trials_gov_recipe_milvus`.
   - Builds the Milvus index.
2. `ddev install trials-chat`
   - Applies `clinical_trials_gov_recipe_milvus_chat`.
   - Adds the Olivero chat assets and block placement.

This keeps the data and assistant setup independent from the frontend presentation layer while preserving the current ability to add chat after backend setup is complete.

## Error Handling and Assumptions

This split intentionally relies on one explicit assumption: `trials-chat` is not a bootstrap preset. It is a layering preset.

If someone runs `ddev install trials-chat` on a site without the Milvus backend already present, recipe import may fail because the block and supporting configuration expect the Milvus assistant stack to exist. That is acceptable for this change because the preset contract will document the prerequisite clearly instead of trying to duplicate backend setup.

## Testing Strategy

Verification for the change should include:

- Search verification that the three Olivero chat config files exist only under `recipes/clinical_trials_gov_recipe_milvus_chat`.
- Recipe YAML review confirming:
  - `clinical_trials_gov_recipe_milvus` no longer imports the three chat UI config items.
  - `clinical_trials_gov_recipe_milvus_chat` does import them.
- Shell validation:
  - `bash -n .ddev/commands/web/install`
- Composer validation:
  - `composer validate --no-check-publish`

If implementation adds or updates automated tests around install presets or recipe expectations, those tests should focus on recipe ownership and documented behavior rather than exact prose in README files.

## Files Expected To Change

- `.ddev/commands/web/install`
- `composer.recipes.json`
- `composer.lock`
- `recipes/clinical_trials_gov_recipe_milvus/recipe.yml`
- `recipes/clinical_trials_gov_recipe_milvus/README.md`
- `recipes/clinical_trials_gov_recipe_milvus_chat/composer.json`
- `recipes/clinical_trials_gov_recipe_milvus_chat/recipe.yml`
- `recipes/clinical_trials_gov_recipe_milvus_chat/README.md`
- `recipes/clinical_trials_gov_recipe_milvus_chat/config/asset_injector.css.trials_milvus_olivero.yml`
- `recipes/clinical_trials_gov_recipe_milvus_chat/config/asset_injector.js.trials_milvus_olivero.yml`
- `recipes/clinical_trials_gov_recipe_milvus_chat/config/block.block.olivero_trials_milvus_chat.yml`

## Recommendation

Implement the split as a clean ownership change:

- Keep `clinical_trials_gov_recipe_milvus` focused on backend Milvus functionality.
- Move the Olivero chat UI into `clinical_trials_gov_recipe_milvus_chat`.
- Add `trials-chat` as an additive DDEV preset that assumes the backend recipe is already present.

This is the smallest change that creates a reusable UI layer without introducing special-case config import behavior or blurring recipe responsibilities.
