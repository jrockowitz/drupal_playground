# ClinicalTrials.gov Custom Field Manager Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extract `custom_field` struct-resolution logic from `ClinicalTrialsGovFieldManager` into a dedicated `ClinicalTrialsGovCustomFieldManager` without changing behavior.

**Architecture:** Keep `ClinicalTrialsGovFieldManager` as the top-level resolver for required fields, allow-list decoration, scalar mappings, and `field_group` fallback. Move `custom_field` support detection and definition building into a dedicated collaborator service, then split kernel coverage so the new service has focused tests while the field manager retains integration coverage.

**Tech Stack:** Drupal 11 custom services, PHP 8.3, PHPUnit kernel tests, DDEV

---

### Task 1: Add regression coverage for the extracted service seam

**Files:**
- Create: `web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovCustomFieldManagerTest.php`
- Modify: `web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovFieldManagerTest.php`

- [ ] **Step 1: Write the failing test**

Add a new kernel test class that fetches `clinical_trials_gov.custom_field_manager` and asserts:

- `protocolSection.sponsorCollaboratorsModule.responsibleParty` resolves to `custom`
- `protocolSection.eligibilityModule` includes formatted `string_long` for `eligibilityCriteria`
- `protocolSection.referencesModule.references` promotes `citation` to `string_long`

Also trim `ClinicalTrialsGovFieldManagerTest` so it keeps one integration assertion for supported structs and the existing field-group fallback assertion.

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovCustomFieldManagerTest.php`

Expected: FAIL because the custom field manager service and test target do not exist yet.

- [ ] **Step 3: Write minimal implementation**

Create the new service interface and class, then wire the service so the test target exists and can resolve structured custom fields.

- [ ] **Step 4: Run test to verify it passes**

Run: `ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovCustomFieldManagerTest.php`

Expected: PASS

- [ ] **Step 5: Re-run field manager integration coverage**

Run: `ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovFieldManagerTest.php`

Expected: PASS

### Task 2: Extract the custom-field service and delegate from the field manager

**Files:**
- Create: `web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovCustomFieldManager.php`
- Create: `web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovCustomFieldManagerInterface.php`
- Modify: `web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovFieldManager.php`
- Modify: `web/modules/custom/clinical_trials_gov/clinical_trials_gov.services.yml`

- [ ] **Step 1: Introduce the new interface and service class**

Move the custom-field-specific constants and helper methods into `ClinicalTrialsGovCustomFieldManager`, keeping helper methods protected and exposing only `resolveStructuredFieldDefinition(string $path): ?array`.

- [ ] **Step 2: Delegate from the existing field manager**

Inject `ClinicalTrialsGovCustomFieldManagerInterface` into `ClinicalTrialsGovFieldManager`, delegate `resolveStructuredFieldDefinition()`, and remove the moved constants and helper methods from the field manager.

- [ ] **Step 3: Verify the code shape stays narrow**

Check that:

- `ClinicalTrialsGovFieldManagerInterface` stays unchanged
- `ClinicalTrialsGovEntityManager` needs no behavior changes
- `ClinicalTrialsGovFieldManager` still owns unsupported and `field_group` fallback policy

### Task 3: Verify the refactor end to end

**Files:**
- Modify: `docs/superpowers/specs/2026-04-29-clinical-trials-gov-custom-field-manager-design.md`

- [ ] **Step 1: Update the design note status**

Change the design document status from `Draft` to `Implemented` if the refactor lands as designed.

- [ ] **Step 2: Run focused verification**

Run:

- `ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovCustomFieldManagerTest.php`
- `ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovFieldManagerTest.php`
- `ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovEntityManagerTest.php`

Expected: PASS

- [ ] **Step 3: Run module lint or broader regression coverage if needed**

Run: `ddev code-review web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovCustomFieldManager.php web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovFieldManager.php web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovCustomFieldManagerTest.php web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovFieldManagerTest.php`

Expected: exit 0
