# Drupal Playground CKEditor Recipe Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move the CKEditor sandbox recipe into Drupal Playground as the tracked `drupal_playground_ckeditor` recipe and verify it on a fresh install.

**Architecture:** Preserve the sandbox recipe's configuration, content, and documentation as a self-contained recipe under `recipes/`. Rename its public Composer and Drupal recipe identities for Drupal Playground, then remove the old ignored source path. A clean DDEV installation followed by recipe application provides the integration verification.

**Tech Stack:** Drupal Recipes, Composer metadata, DDEV, Drush.

---

### Task 1: Migrate the recipe source

**Files:**
- Create: `recipes/drupal_playground_ckeditor/` (all sandbox recipe files)
- Modify: `.gitignore`
- Remove: `recipes/ckeditor_recipe/`

- [ ] **Step 1: Confirm the ignored source recipe is available**

Run: `test -f recipes/ckeditor_recipe/recipe.yml && test -f recipes/ckeditor_recipe/composer.json`
Expected: exit code 0.

- [ ] **Step 2: Move the complete source tree to the new tracked name**

Run: `mv recipes/ckeditor_recipe recipes/drupal_playground_ckeditor`
Expected: the new directory contains `recipe.yml`, `composer.json`, configuration, content, and documentation.

- [ ] **Step 3: Rename the public recipe metadata**

Update `recipes/drupal_playground_ckeditor/composer.json` to name the package `drupal/drupal_playground_ckeditor`, and update `recipe.yml` and `README.md` to identify it as Drupal Playground CKEditor.

- [ ] **Step 4: Remove the obsolete ignore rule**

Delete `/recipes/ckeditor_recipe` from `.gitignore` so the renamed recipe is visible to Git.

- [ ] **Step 5: Check the migration**

Run: `git status --short -- .gitignore recipes/drupal_playground_ckeditor recipes/ckeditor_recipe && git diff --check`
Expected: `.gitignore` and the new recipe appear as intended with no whitespace errors.

### Task 2: Verify a clean installation and application

**Files:**
- Test: `recipes/drupal_playground_ckeditor/recipe.yml`

- [ ] **Step 1: Reinstall Drupal Playground**

Run: `ddev install`
Expected: Drupal is installed and the command prints a one-time login URL.

- [ ] **Step 2: Apply the migrated recipe**

Run: `ddev recipe-apply ../recipes/drupal_playground_ckeditor`
Expected: exit code 0 and no recipe import failures.

- [ ] **Step 3: Confirm core CKEditor recipe outcomes**

Run: `ddev drush pm:list --status=enabled --type=module --format=list | rg 'ckeditor5_plugin_pack|ckeditor_codemirror|embedded_content|entity_browser|linkit'`
Expected: the recipe's principal modules are enabled.

- [ ] **Step 4: Run final diff verification**

Run: `git diff --check && git status --short`
Expected: no whitespace errors; the unrelated pre-existing `composer.lock` modification remains separate.
