# Ecosystem: ClinicalTrials.gov Specification

## Purpose

Define the current ClinicalTrials.gov workspace, its base module mode, two data
models, and Elasticsearch and Milvus discovery variants.

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
