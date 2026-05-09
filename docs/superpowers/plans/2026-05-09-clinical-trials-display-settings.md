# ClinicalTrials.gov Display Settings Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the legacy `readonly` setting with pre-creation form/view display settings for ClinicalTrials.gov trial fields and field groups, and enforce `visible_update` through field access.

**Architecture:** Extend the existing `ClinicalTrialsGovEntityManager` display-creation responsibility instead of introducing a new manager service. Store display choices in `clinical_trials_gov.settings`, have setup and settings forms persist those choices, and update the entity hook class so readonly title behavior and `visible_update` access decisions are driven by the new config.

**Tech Stack:** Drupal 11 config schema, ConfigFormBase, OOP hooks, entity form/view displays, field_group third-party settings, PHPUnit kernel and functional tests

---

### Task 1: Add failing coverage for the new settings contract

**Files:**
- Modify: `web/modules/custom/clinical_trials_gov/tests/src/Kernel/Config/ClinicalTrialsGovSettingValidationTest.php`
- Modify: `web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovSetupManagerTest.php`

- [ ] **Step 1: Write the failing tests**
- [ ] **Step 2: Run the focused kernel tests to verify they fail**
- [ ] **Step 3: Update install config, schema, and setup overrides**
- [ ] **Step 4: Re-run the focused kernel tests to verify they pass**

### Task 2: Add failing coverage for display component and field group creation

**Files:**
- Modify: `web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovEntityManagerTest.php`
- Modify: `web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovEntityManager.php`

- [ ] **Step 1: Write the failing entity manager assertions for visible, hidden, readonly, and field-group format mapping**
- [ ] **Step 2: Run the focused entity manager kernel test to verify it fails**
- [ ] **Step 3: Implement config-driven form/view component and field-group creation in the entity manager**
- [ ] **Step 4: Re-run the focused entity manager kernel test to verify it passes**

### Task 3: Replace the old readonly runtime hook behavior with the new hook behavior

**Files:**
- Modify: `web/modules/custom/clinical_trials_gov/src/Hook/ClinicalTrialsGovEntityHooks.php`
- Modify: `web/modules/custom/clinical_trials_gov/tests/src/Kernel/Hook/ClinicalTrialsGovFieldAccessHooksTest.php`
- Modify: `web/modules/custom/clinical_trials_gov/tests/src/Functional/ClinicalTrialsGovReadonlyTest.php`

- [ ] **Step 1: Rewrite the hook tests to fail against the current readonly toggle behavior and to cover `visible_update` field access**
- [ ] **Step 2: Run the focused hook and functional tests to verify they fail**
- [ ] **Step 3: Implement the new title-hide logic and `hook_entity_field_access()` behavior, and remove the old form-display mutation logic**
- [ ] **Step 4: Re-run the focused hook and functional tests to verify they pass**

### Task 4: Update the settings form to expose only valid choices

**Files:**
- Modify: `web/modules/custom/clinical_trials_gov/src/Form/ClinicalTrialsGovSettingsForm.php`
- Modify: `web/modules/custom/clinical_trials_gov/tests/src/Unit/TestClinicalTrialsGovSettingsForm.php`

- [ ] **Step 1: Add failing coverage where helpful for choice normalization helpers**
- [ ] **Step 2: Run the relevant unit tests to verify they fail if coverage was added**
- [ ] **Step 3: Replace the old checkbox with select fields that respect installed modules**
- [ ] **Step 4: Re-run the relevant unit or kernel coverage to verify it passes**

### Task 5: Clean up legacy references and verify the module

**Files:**
- Modify: `web/modules/custom/clinical_trials_gov/AGENTS.md`
- Modify: `web/modules/custom/clinical_trials_gov/config/install/clinical_trials_gov.settings.yml`
- Modify: `web/modules/custom/clinical_trials_gov/config/schema/clinical_trials_gov.schema.yml`
- Modify: any remaining tests or docs that mention the removed `readonly` setting directly

- [ ] **Step 1: Remove stale references to the removed setting and old behavior**
- [ ] **Step 2: Run `ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Kernel/Config/ClinicalTrialsGovSettingValidationTest.php web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovSetupManagerTest.php web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovEntityManagerTest.php web/modules/custom/clinical_trials_gov/tests/src/Kernel/Hook/ClinicalTrialsGovFieldAccessHooksTest.php web/modules/custom/clinical_trials_gov/tests/src/Functional/ClinicalTrialsGovReadonlyTest.php`**
- [ ] **Step 3: Run `ddev code-review web/modules/custom/clinical_trials_gov`**
- [ ] **Step 4: Record any remaining risks if a dependency-specific path could not be exercised locally**
