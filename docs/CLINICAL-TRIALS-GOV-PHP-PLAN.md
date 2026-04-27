# ClinicalTrials.gov Module — Planning Document

## Overview

The `clinical_trials_gov` module provides a four-step wizard for importing clinical trial data from ClinicalTrials.gov into Drupal using the Migrate framework. An existing module already handles querying the ClinicalTrials.gov API and displaying results; this module builds on top of that to provide a structured import workflow.

### Terminology

- **Study** — The source entity on ClinicalTrials.gov. Trials are called "studies" in the API.
- **Trial** — The destination entity in Drupal. Using "trial" in Drupal helps distinguish source from destination.

### Architecture Notes

- Configuration is stored in `clinical_trials_gov.settings.yml`
- Import uses Drupal's Migrate framework
- Reuses existing render element that builds API queries and displays trial data
- Each step has an index page, a keyword, a description, and one goal for the user to accomplish

---

## Step 1: Find

**Keyword:** Find
**Description:** Find clinical trials from ClinicalTrials.gov by selecting your query criteria.
**Goal:** Save the search query into Drupal's configuration system.

### Details

- The user selects query criteria (conditions, locations, keywords, etc.) using the existing query builder element
- On save, the query parameters are stored in `clinical_trials_gov.settings.yml`
- The query labels should also be stored (needed later for the import summary display)

---

## Step 2: Review

**Keyword:** Review
**Description:** Review selected clinical trials for import into Drupal.
**Goal:** Review the selected trials and confirm they are the ones you want to import.

### Details

- Reuse the existing report/display functionality that shows trials based on query parameters
- Each trial should link to an individual trial detail view
- Supports sorting and browsing so the user can verify the result set

---

## Step 3: Configure

**Keyword:** Configure
**Description:** Configure the trial content type to map clinical trial data fields.
**Goal:** Configure and create the trial content type that will store the imported data.

### Details

#### Part 1 — Content Type Configuration

The form collects:

- **Label** — Human-readable name for the content type
- **Machine name** — Stored in `clinical_trials_gov.settings.yml` as `type`
- **Description** — Description of the content type

On save:

- The content type is generated/created in Drupal
- Only the machine name (`type`) is saved to config; label and description live on the content type itself and can be pulled from Drupal when needed

**Default values:**

- Machine name defaults to `trial`

#### Part 2 — Field Mapping

A `tableselect` element displays fields available from ClinicalTrials.gov (queried from the metadata endpoint). For each field, the user maps it to a target Drupal field type.

**Target field types:**

- String
- Long text
- HTML
- Number
- Boolean
- Custom field — for complex structured data where individual properties can be mapped (like an associative array)
- JSON field — for very complex data that can't be broken down into custom field properties

**Field grouping:**

- Fields should be grouped by category (e.g., summary, detailed, location, etc.)
- Exact groupings TBD — need to review the API metadata to determine appropriate categories

**Required/default fields:**

- NCT Number (National Clinical Trial Number) — always required
- Title — always required
- Description — always required
- Additional default fields TBD as development progresses

**TODO:** Consider additional configuration options for fields (to be revisited later).

---

## Step 4: Import

**Keyword:** Import
**Description:** Import studies from ClinicalTrials.gov into Drupal as [content type label] nodes.
**Goal:** Review the import summary and trigger the migration.

### Details

#### Import Summary

Before the user triggers the import, display:

1. **Query summary** — A themed table showing only the query parameters that are set, using stored labels from the query builder element (e.g., "Condition: Breast Cancer", "Location: New York")
2. **Trial counts:**
   - Total number of trials selected
   - Number of trials to be **created** (new)
   - Number of trials to be **updated** (existing, changed)
   - Number of trials to be **deleted** (no longer in the query result set / out of scope)

#### Migration Execution

**TODO:** Determine the import trigger mechanism. Options to evaluate:

- Synchronous form submit (batch API)
- Drupal Queue system
- Drush command
- Combination of the above

The migration should handle create, update, and delete operations for trial nodes based on the current query results vs. existing imported data.

---

## Index Page

The module provides an index page that displays all four steps:

- Each step is numbered (1–4)
- Each step shows its keyword and description
- Steps indicate completion status (TBD)
- Serves as the landing page for the import workflow

---

## Open Questions

- Exact field grouping categories for the field mapping table
- Additional default/required fields beyond NCT Number, title, and description
- Migration trigger mechanism (batch, queue, drush, or hybrid)
- Step completion tracking and whether users can revisit/edit previous steps
- How to handle migration rollback or re-import scenarios
