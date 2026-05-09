# Clinical Trials Milvus Chat Recipe Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Split the Olivero chat UI from `clinical_trials_gov_recipe_milvus` into a dedicated `clinical_trials_gov_recipe_milvus_chat` recipe and add a `ddev install trials-chat` preset that layers on top of an existing `trials-milvus` installation.

**Architecture:** Keep `clinical_trials_gov_recipe_milvus` focused on backend Milvus search and assistant setup, and move the three Olivero-specific chat configuration entities into a new `clinical_trials_gov_recipe_milvus_chat` recipe. Wire the new recipe into Composer recipe metadata and the DDEV install command so chat can be applied independently after the backend setup already exists.

**Tech Stack:** Drupal 11 recipes, DDEV custom install command, Composer path repositories, YAML configuration entities

---

### Task 1: Create The Chat Recipe Package

**Files:**
- Create: `recipes/clinical_trials_gov_recipe_milvus_chat/composer.json`
- Create: `recipes/clinical_trials_gov_recipe_milvus_chat/recipe.yml`
- Create: `recipes/clinical_trials_gov_recipe_milvus_chat/README.md`

- [ ] **Step 1: Create the new recipe Composer manifest**

Use this file content:

```json
{
  "name": "drupal/clinical_trials_gov_recipe_milvus_chat",
  "description": "Installs the Olivero DeepChat UI for the Clinical Trials Milvus experience.",
  "type": "drupal-recipe",
  "license": "GPL-2.0-or-later",
  "version": "1.0.0",
  "require": {
    "drupal/core": "^11"
  }
}
```

- [ ] **Step 2: Create the new recipe definition**

Use this file content:

```yaml
name: 'Clinical Trials Recipe Milvus Chat'
description: 'Adds the Olivero DeepChat UI for the Clinical Trials Milvus experience.'
type: 'Site'
config:
  strict: false
  import:
    asset_injector:
      - asset_injector.css.trials_milvus_olivero
      - asset_injector.js.trials_milvus_olivero
    block:
      - block.block.olivero_trials_milvus_chat
```

- [ ] **Step 3: Create the new recipe README**

Use this file content:

```md
# Clinical Trials Recipe Milvus Chat

Adds the Olivero DeepChat user interface for the Clinical Trials Milvus experience.

## Prerequisite

This recipe assumes `clinical_trials_gov_recipe_milvus` has already been installed.

Apply the backend setup first:

```bash
ddev install trials-milvus
```

Then apply the chat UI:

```bash
ddev install trials-chat
```

## What This Recipe Adds

- Olivero-specific Asset Injector CSS for the chat experience
- Olivero-specific Asset Injector JavaScript for the chat experience
- The Olivero DeepChat block placement for the Clinical Trials Milvus assistant

## Notes

- This recipe is additive and does not rebuild the Milvus index.
- This recipe is intended for Olivero and imports Olivero-specific block configuration.
```

- [ ] **Step 4: Run a focused diff review**

Run: `git diff -- recipes/clinical_trials_gov_recipe_milvus_chat`
Expected: The new recipe package contains only the three scaffold files added in this task.

### Task 2: Move The Olivero Chat Config Into The New Recipe

**Files:**
- Modify: `recipes/clinical_trials_gov_recipe_milvus/recipe.yml`
- Create: `recipes/clinical_trials_gov_recipe_milvus_chat/config/asset_injector.css.trials_milvus_olivero.yml`
- Create: `recipes/clinical_trials_gov_recipe_milvus_chat/config/asset_injector.js.trials_milvus_olivero.yml`
- Create: `recipes/clinical_trials_gov_recipe_milvus_chat/config/block.block.olivero_trials_milvus_chat.yml`
- Delete: `recipes/clinical_trials_gov_recipe_milvus/config/asset_injector.css.trials_milvus_olivero.yml`
- Delete: `recipes/clinical_trials_gov_recipe_milvus/config/asset_injector.js.trials_milvus_olivero.yml`
- Delete: `recipes/clinical_trials_gov_recipe_milvus/config/block.block.olivero_trials_milvus_chat.yml`

- [ ] **Step 1: Copy the three existing config files into the new recipe**

Move these files without changing their YAML content:

```text
recipes/clinical_trials_gov_recipe_milvus/config/asset_injector.css.trials_milvus_olivero.yml
recipes/clinical_trials_gov_recipe_milvus/config/asset_injector.js.trials_milvus_olivero.yml
recipes/clinical_trials_gov_recipe_milvus/config/block.block.olivero_trials_milvus_chat.yml
```

Destination:

```text
recipes/clinical_trials_gov_recipe_milvus_chat/config/asset_injector.css.trials_milvus_olivero.yml
recipes/clinical_trials_gov_recipe_milvus_chat/config/asset_injector.js.trials_milvus_olivero.yml
recipes/clinical_trials_gov_recipe_milvus_chat/config/block.block.olivero_trials_milvus_chat.yml
```

- [ ] **Step 2: Remove the chat UI imports from the Milvus backend recipe**

Update `recipes/clinical_trials_gov_recipe_milvus/recipe.yml` so these lines are removed from its config import section:

```yaml
      - asset_injector.css.trials_milvus_olivero
      - asset_injector.js.trials_milvus_olivero
```

and:

```yaml
      - block.block.olivero_trials_milvus_chat
```

- [ ] **Step 3: Run a targeted search to confirm ownership changed**

Run: `rg -n "asset_injector\\.css\\.trials_milvus_olivero|asset_injector\\.js\\.trials_milvus_olivero|block\\.block\\.olivero_trials_milvus_chat" recipes/clinical_trials_gov_recipe_milvus recipes/clinical_trials_gov_recipe_milvus_chat`
Expected: The file references appear in the new chat recipe and no longer appear in the Milvus backend recipe import list.

### Task 3: Register The New Recipe In Composer Metadata

**Files:**
- Modify: `composer.recipes.json`
- Modify: `composer.lock`

- [ ] **Step 1: Add the new path repository and package requirement**

Update `composer.recipes.json` to add:

```json
{
  "type": "path",
  "url": "recipes/clinical_trials_gov_recipe_milvus_chat"
}
```

and add this requirement entry:

```json
"drupal/clinical_trials_gov_recipe_milvus_chat": "*"
```

- [ ] **Step 2: Refresh the lock metadata for the new recipe package**

Run: `composer update --lock --no-install`
Expected: `composer.lock` gains the new `drupal/clinical_trials_gov_recipe_milvus_chat` path package entry without installing dependencies.

- [ ] **Step 3: Review the Composer diff**

Run: `git diff -- composer.recipes.json composer.lock`
Expected: Only the new recipe repository and package metadata are added for the chat recipe.

### Task 4: Add The DDEV Trials Chat Preset

**Files:**
- Modify: `.ddev/commands/web/install`

- [ ] **Step 1: Add the new preset to the install help text**

Update the example block near the top of `.ddev/commands/web/install` so it includes:

```bash
ddev install trials-chat
```

- [ ] **Step 2: Add the `trials-chat` preset branch**

Add a new branch to the preset loop with this behavior:

```bash
  elif [ "$PRESET" = "trials-chat" ]; then

    echo "Applying Clinical Trials chat recipe..."
    drush recipe ../recipes/clinical_trials_gov_recipe_milvus_chat
```

Do not add AI setup, ClinicalTrials.gov setup, Milvus indexing, or other side effects to this branch.

- [ ] **Step 3: Verify shell syntax**

Run: `bash -n .ddev/commands/web/install`
Expected: No output and exit code `0`.

### Task 5: Update The Milvus Documentation

**Files:**
- Modify: `recipes/clinical_trials_gov_recipe_milvus/README.md`
- Modify: `recipes/clinical_trials_gov_recipe_milvus_chat/README.md`

- [ ] **Step 1: Rewrite the Milvus recipe README to describe backend ownership**

Update `recipes/clinical_trials_gov_recipe_milvus/README.md` so it:

- Describes the recipe as the Milvus backend and assistant layer.
- Stops implying that the Olivero DeepChat UI always ships as part of `trials-milvus`.
- Explains that the visible chat UI now comes from `clinical_trials_gov_recipe_milvus_chat`.

- [ ] **Step 2: Expand the new chat recipe README with the layering guidance**

Update `recipes/clinical_trials_gov_recipe_milvus_chat/README.md` so it:

- States that `ddev install trials-milvus` must run first.
- Documents `ddev install trials-chat`.
- Clarifies that the recipe is additive and Olivero-specific.

- [ ] **Step 3: Review the documentation diff**

Run: `git diff -- recipes/clinical_trials_gov_recipe_milvus/README.md recipes/clinical_trials_gov_recipe_milvus_chat/README.md`
Expected: The split between backend Milvus setup and Olivero chat UI is clearly documented.

### Task 6: Validate The Split End To End

**Files:**
- Modify: `composer.recipes.json`
- Modify: `composer.lock`
- Modify: `.ddev/commands/web/install`
- Modify: `recipes/clinical_trials_gov_recipe_milvus/recipe.yml`
- Modify: `recipes/clinical_trials_gov_recipe_milvus/README.md`
- Modify: `recipes/clinical_trials_gov_recipe_milvus_chat/composer.json`
- Modify: `recipes/clinical_trials_gov_recipe_milvus_chat/recipe.yml`
- Modify: `recipes/clinical_trials_gov_recipe_milvus_chat/README.md`
- Modify: `recipes/clinical_trials_gov_recipe_milvus_chat/config/asset_injector.css.trials_milvus_olivero.yml`
- Modify: `recipes/clinical_trials_gov_recipe_milvus_chat/config/asset_injector.js.trials_milvus_olivero.yml`
- Modify: `recipes/clinical_trials_gov_recipe_milvus_chat/config/block.block.olivero_trials_milvus_chat.yml`

- [ ] **Step 1: Run Composer validation**

Run: `composer validate --no-check-publish`
Expected: `./composer.json is valid, but with a few warnings` and no validation errors.

- [ ] **Step 2: Confirm the moved config exists only in the new recipe**

Run: `rg -n --files recipes/clinical_trials_gov_recipe_milvus recipes/clinical_trials_gov_recipe_milvus_chat | rg "asset_injector\\.css\\.trials_milvus_olivero|asset_injector\\.js\\.trials_milvus_olivero|block\\.block\\.olivero_trials_milvus_chat"`
Expected:

```text
recipes/clinical_trials_gov_recipe_milvus_chat/config/asset_injector.css.trials_milvus_olivero.yml
recipes/clinical_trials_gov_recipe_milvus_chat/config/asset_injector.js.trials_milvus_olivero.yml
recipes/clinical_trials_gov_recipe_milvus_chat/config/block.block.olivero_trials_milvus_chat.yml
```

- [ ] **Step 3: Review the final workspace diff**

Run: `git diff -- .ddev/commands/web/install composer.recipes.json composer.lock recipes/clinical_trials_gov_recipe_milvus recipes/clinical_trials_gov_recipe_milvus_chat`
Expected: The diff shows a clean split between backend Milvus setup and the new chat UI recipe.
