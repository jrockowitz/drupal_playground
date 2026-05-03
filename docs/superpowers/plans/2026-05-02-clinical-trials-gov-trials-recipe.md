# ClinicalTrials.gov Trials Recipe Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a `drupal_playground_trials` recipe and a shared ClinicalTrials.gov setup workflow that recipes and Drush can both invoke.

**Architecture:** Extract the current Drush setup orchestration into a new `ClinicalTrialsGovSetupManager::setUp(array $overrides): array` service, add a `clinical_trials_gov.settings:setUp` config action plugin that delegates to it, then create a new recipe that installs the required modules and calls the config action with only a `query`. Cover the shared workflow with targeted kernel and unit tests.

**Tech Stack:** Drupal 11 services, config action plugins, recipes, Drush commands, PHPUnit unit and kernel tests

---

### Task 1: Lock in the shared setup workflow with failing tests

**Files:**
- Create: `web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovSetupManagerTest.php`
- Modify: `web/modules/custom/clinical_trials_gov/tests/src/Unit/ClinicalTrialsGovCommandsTest.php`
- Create: `web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovSettingsSetUpConfigActionTest.php`

- [ ] **Step 1: Write the failing kernel test for the setup manager**
- [ ] **Step 2: Run the kernel test to verify it fails**
- [ ] **Step 3: Write the failing unit test that Drush delegates to the setup manager**
- [ ] **Step 4: Run the unit test to verify it fails**
- [ ] **Step 5: Write the failing kernel test for the config action plugin requiring `query` and performing setup**
- [ ] **Step 6: Run the kernel test to verify it fails**

### Task 2: Implement the shared setup service and refactor Drush

**Files:**
- Create: `web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovSetupManager.php`
- Create: `web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovSetupManagerInterface.php`
- Modify: `web/modules/custom/clinical_trials_gov/clinical_trials_gov.services.yml`
- Modify: `web/modules/custom/clinical_trials_gov/src/Drush/Commands/ClinicalTrialsGovCommands.php`

- [ ] **Step 1: Add the setup manager interface and service class**
- [ ] **Step 2: Wire the service in `clinical_trials_gov.services.yml`**
- [ ] **Step 3: Refactor the Drush command to call the setup manager and print returned summary data**
- [ ] **Step 4: Run the new unit and kernel tests to verify they pass**

### Task 3: Add the config action plugin and recipe

**Files:**
- Create: `web/modules/custom/clinical_trials_gov/src/Plugin/ConfigAction/SetUpClinicalTrialsGovSettings.php`
- Create: `recipes/drupal_playground_trials/recipe.yml`
- Create: `recipes/drupal_playground_trials/composer.json`
- Create: `recipes/drupal_playground_trials/README.md`

- [ ] **Step 1: Add the config action plugin that validates `query` and delegates to the setup manager**
- [ ] **Step 2: Add the new recipe files with required modules, Composer dependencies, and `clinical_trials_gov.settings:setUp` action**
- [ ] **Step 3: Run the config action kernel test to verify the plugin and recipe-facing workflow pass**

### Task 4: Verify the full change set

**Files:**
- Modify: `web/modules/custom/clinical_trials_gov/README.md`
- Modify: `web/modules/custom/clinical_trials_gov/AGENTS.md`

- [ ] **Step 1: Update documentation references for the new setup manager and recipe where needed**
- [ ] **Step 2: Run focused PHPUnit and PHPCS commands for the touched files**
- [ ] **Step 3: Summarize results and any residual risks**
