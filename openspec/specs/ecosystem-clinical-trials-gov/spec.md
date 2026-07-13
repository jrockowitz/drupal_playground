# Ecosystem: ClinicalTrials.gov Specification

## Purpose

Define the current ClinicalTrials.gov workspace, guided import workflow, API
explorer, two data models, and Elasticsearch, Milvus, and RAG discovery
variants.

## Requirements

### Requirement: ClinicalTrials.gov worktree identity

ClinicalTrials.gov ecosystem work SHALL use the `drupal_clinical_trials_gov`
worktree and the shared foundation specifications.

#### Scenario: Begin focused ClinicalTrials.gov work

- **WHEN** a change primarily targets ClinicalTrials.gov import, reporting, field generation, search, or AI discovery
- **THEN** the developer selects `drupal_clinical_trials_gov`

### Requirement: Base module preset

The `trials` preset SHALL enable the base ClinicalTrials.gov modules without
applying a data-model Recipe.

#### Scenario: Install the module baseline

- **WHEN** `ddev install trials` runs
- **THEN** Readonly Field Widget, ClinicalTrials.gov, and ClinicalTrials.gov Report are enabled
- **AND** the generated login URL targets `/admin/config/services/clinical-trials-gov`

### Requirement: Fields data model

The fields setup flow SHALL create a fixed trial content model with AI
automators and SHALL import studies through Drupal Migrate.

#### Scenario: Set up and import field-based trials

- **WHEN** `trials-fields-setup` runs
- **THEN** the fields setup Recipe receives the supported query input
- **AND** studies are imported using the configured limit
- **AND** the AI Automator field modifier queue is run

### Requirement: Flexible data model

The data setup flow SHALL configure the reusable ClinicalTrials.gov data model
and SHALL import studies through Drupal Migrate.

#### Scenario: Set up and import data-based trials

- **WHEN** `trials-data-setup` runs
- **THEN** the data setup Recipe receives the supported query input
- **AND** studies are imported using the configured limit

### Requirement: Elasticsearch discovery variant

Each data model SHALL support a tracked Elasticsearch Search API and Views
configuration after its setup stage has created the required content model.

#### Scenario: Index trials in Elasticsearch

- **WHEN** `trials-fields-elastic` or `trials-data-elastic` runs after its applicable setup
- **THEN** the corresponding Elasticsearch Recipe is applied
- **AND** the `trials-data-elastic` variant imports studies before indexing
- **AND** the `trials_elasticsearch` backend and Search API index are cleared
- **AND** all available items are indexed
- **AND** the generated login URL targets `/trials`

### Requirement: Milvus discovery variant

Each data model SHALL support a tracked AI Search and Milvus configuration.
The applicable setup stage SHALL create and populate the content model before
the Milvus variant indexes it.

#### Scenario: Index trials in Milvus

- **WHEN** `trials-fields-milvus` or `trials-data-milvus` runs after its applicable setup
- **THEN** the corresponding Milvus Recipe is applied
- **AND** the `trials_milvus` index is cleared
- **AND** indexing runs in five passes of at most 200 items with 30-second pauses between passes
- **AND** the generated login URL targets `/trials`

### Requirement: Convenience flows

The ecosystem SHALL provide complete fields and data convenience presets.

#### Scenario: Install all variants for a data model

- **WHEN** `trials-fields` or `trials-data` is supplied
- **THEN** the command expands it into setup, Elasticsearch, and Milvus stages in that order

### Requirement: ClinicalTrials.gov options

The ecosystem SHALL accept an optional API query and import limit for supported
setup and migration operations.

#### Scenario: Use default options

- **WHEN** no `--query` or `--limit` is supplied
- **THEN** Recipe-defined default query behavior is used
- **AND** migration imports use the default limit of 10

### Requirement: Guided import workflow

The ClinicalTrials.gov module SHALL provide a query-driven workflow for finding,
reviewing, configuring, importing, and managing trials.

#### Scenario: Complete the browser workflow

- **WHEN** a site builder uses the ClinicalTrials.gov administration pages
- **THEN** the workflow presents Find, Review, Configure, Import, and Manage stages in that order
- **AND** Settings provides advanced destination and display configuration
- **AND** Review provides study and metadata views for the saved query

### Requirement: Query-driven path discovery

The saved ClinicalTrials.gov query SHALL determine the study paths available for
destination field configuration.

#### Scenario: Save a query with matching studies

- **WHEN** the Find stage saves a query that returns studies
- **THEN** the raw query and discovered study paths are stored in configuration
- **AND** child paths inside repeated structured values participate in discovery
- **AND** Configure uses the discovered paths instead of a static allow-list

#### Scenario: No paths are available

- **WHEN** no query has been saved or the saved query discovers no study paths
- **THEN** Configure is blocked until a successful discovery operation supplies paths

### Requirement: Generated destination model

The Configure stage and Drush setup command SHALL use shared services to generate
the Drupal destination model and migration from saved configuration.

#### Scenario: Configure a trial model

- **WHEN** the required query, paths, content type, and field mapping are available
- **THEN** the module creates or reuses the destination content type
- **AND** it creates mapped fields and field groups for selected study paths
- **AND** it generates the `clinical_trials_gov` Migrate configuration
- **AND** structured values may be represented by Custom Field destinations while unsupported nested values use a YAML-backed long-text fallback

#### Scenario: Run command-line setup

- **WHEN** `clinical-trials-gov:setup` receives a supported query
- **THEN** it saves the query, discovers paths, derives the default field mapping, creates the destination model, and regenerates the migration

### Requirement: Migrate-based study import

Imported trial content SHALL be populated through the generated Drupal Migrate
workflow rather than manual node creation.

#### Scenario: Import configured studies

- **WHEN** the generated `clinical_trials_gov` migration runs
- **THEN** its source fetches matching NCT identifiers and loads each full study
- **AND** source data is flattened to dot-notation paths while parent structured values needed by Custom Field destinations are preserved
- **AND** the Import stage reports migration status and supports batch execution

#### Scenario: Protect import-managed content

- **WHEN** the destination ClinicalTrials.gov content type exists
- **THEN** users cannot create its nodes through the Drupal add-content interface
- **AND** Manage redirects to the content listing filtered to that content type

### Requirement: ClinicalTrials.gov token access

Imported trial nodes SHALL expose API-backed ClinicalTrials.gov values through
Drupal tokens.

#### Scenario: Resolve an imported study token

- **WHEN** a token identifies a metadata piece name or full metadata path
- **THEN** matching is case-insensitive and ignores hyphen and underscore differences
- **AND** the full study is loaded using the node's stored NCT identifier
- **AND** array and object values are returned as formatted structured text

### Requirement: Standalone API explorer

The module SHALL retain a stateless, server-side ClinicalTrials.gov API v2
explorer as a local development and research tool.

#### Scenario: Explore the API

- **WHEN** a developer opens `test/clinical_trials_gov.php`
- **THEN** the page lists supported API endpoints and provides parameter forms for study and field-statistics requests
- **AND** study searches support detail links and cursor pagination
- **AND** responses include formatted output and escaped raw JSON

#### Scenario: Handle an upstream error

- **WHEN** the upstream request fails, returns an unsuccessful HTTP status, or returns invalid JSON
- **THEN** the explorer presents an escaped error without evaluating input, writing user data, or using session state

### Requirement: Hybrid trial discovery

ClinicalTrials.gov discovery SHALL support keyword and faceted search through
Elasticsearch alongside semantic RAG retrieval through Milvus.

#### Scenario: Install the complete discovery experience

- **WHEN** a fields or data convenience flow applies both Elasticsearch and Milvus stages
- **THEN** Elasticsearch provides the tracked Search API, Views, and faceted trial listing
- **AND** Milvus provides the tracked vector index and AI assistant retrieval
- **AND** the `/trials` experience exposes the applicable listing and chat interfaces

### Requirement: Trial-focused RAG chat

The current ClinicalTrials.gov RAG chat integration SHALL adapt the contributed
AI RAG Search Chat interface to trial discovery.

#### Scenario: Open the trial chat

- **WHEN** a user with chat access opens the configured AI search chat page
- **THEN** the interface asks for trial-relevant details such as condition, age, location, prior treatments, biomarkers, and travel preferences
- **AND** actions, loading messages, history controls, and accessible labels use trial-search terminology
