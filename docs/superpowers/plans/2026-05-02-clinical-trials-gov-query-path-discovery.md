# ClinicalTrials.gov Query Path Discovery Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Simplify query path discovery so setup uses the first 250 most recently updated studies from `/studies` instead of batching through per-study detail requests.

**Architecture:** Refactor `ClinicalTrialsGovPathsManager` to discover paths from one sorted `/studies` response, remove the batch wrapper and request delay, and update the Find form and Drush command to use the shared inline discovery flow. Replace the old batch-oriented tests with unit and kernel coverage for the new request shape and saved config behavior.

**Tech Stack:** Drupal 11 config forms, Drush commands, PHPUnit unit and kernel tests

---

### Task 1: Lock in the new discovery contract with tests

**Files:**
- Modify: `web/modules/custom/clinical_trials_gov/tests/src/Unit/ClinicalTrialsGovPathsManagerTest.php`
- Modify: `web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovFindFormTest.php`
- Delete: `web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovPathDiscoveryBatchTest.php`

- [ ] Add a unit test that expects `discoverQueryPaths()` to call `getStudies()` once with `pageSize=250` and `sort=LastUpdatePostDate:desc`, and to discover normalized paths directly from the returned studies without calling `getStudy()`.
- [ ] Update the Find form kernel test so submit saves discovered `query_paths` immediately instead of registering a batch.
- [ ] Run the targeted tests and confirm they fail against the old batch-based implementation.

### Task 2: Refactor discovery and inline submit behavior

**Files:**
- Modify: `web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovPathsManager.php`
- Modify: `web/modules/custom/clinical_trials_gov/src/Form/ClinicalTrialsGovFindForm.php`
- Delete: `web/modules/custom/clinical_trials_gov/src/Batch/ClinicalTrialsGovPathDiscoveryBatch.php`

- [ ] Rewrite `discoverQueryPaths()` to fetch the first 250 studies sorted by most recent update and flatten paths from that payload.
- [ ] Remove the request delay constant and helper since no per-study requests remain.
- [ ] Update Find form submit handling to save `query` and discovered `query_paths` inline, keep migration updates, and remove batch scheduling.

### Task 3: Align Drush output and verify the shared workflow

**Files:**
- Modify: `web/modules/custom/clinical_trials_gov/src/Drush/Commands/ClinicalTrialsGovCommands.php`

- [ ] Update Drush setup messaging so it documents the new “first 250 most recently updated studies” discovery behavior.
- [ ] Run targeted unit and kernel tests for the paths manager and Find form, plus a relevant kernel workflow test, and report the exact results.
