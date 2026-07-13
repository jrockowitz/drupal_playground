# Foundation: Drupal Recipe Specification

## Purpose

Define how Drupal Playground applies Drupal Recipes and composes optional install
presets into a known site state.

## Requirements

### Requirement: Recipe runner selection

The `recipe-apply` command SHALL apply a Recipe from the Drupal document root
using an available supported runner.

#### Scenario: Apply with the contributed runner

- **WHEN** `vendor/bin/dr` exists
- **THEN** the command executes its `recipe` operation with the supplied arguments and rebuilds Drupal caches

#### Scenario: Apply with the core runner

- **WHEN** `vendor/bin/dr` is absent and `core/scripts/drupal` exists
- **THEN** the command executes the core `recipe` operation and rebuilds Drupal caches

#### Scenario: No runner is available

- **WHEN** neither supported Recipe runner exists
- **THEN** the command reports both expected paths and exits unsuccessfully

### Requirement: Shared Recipe sequence

Every `ddev install` invocation SHALL apply the shared foundation Recipes before
optional presets.

#### Scenario: Build the shared site baseline

- **WHEN** Drupal core installation succeeds
- **THEN** the command applies the base and shared administration Recipes
- **AND** it applies the version-appropriate administration theme Recipe
- **AND** it applies the Devel, AI, and AI Devel Recipes before processing optional presets

### Requirement: Ordered preset composition

The install command SHALL process provider presets before other presets and SHALL
then process optional presets in the order supplied after convenience expansion.

#### Scenario: Configure an AI provider before an ecosystem

- **WHEN** `openai`, `anthropic`, or `gemini` is supplied with another preset
- **THEN** the corresponding provider Recipe is applied before the other preset Recipes

#### Scenario: Expand a ClinicalTrials.gov convenience preset

- **WHEN** `trials-data` or `trials-fields` is supplied
- **THEN** it expands to setup, Elasticsearch, and Milvus preset stages in that order

### Requirement: Recipe inputs and install options

The install command SHALL pass supported query input to ClinicalTrials.gov setup
Recipes and SHALL use the requested import limit for migration operations.

#### Scenario: Customize ClinicalTrials.gov discovery

- **WHEN** `--query` and `--limit` are supplied with a supported ClinicalTrials.gov preset
- **THEN** the query is passed as Recipe input to the applicable setup Recipe
- **AND** migration imports use the supplied limit

### Requirement: Installation completion

The install command SHALL rebuild caches and print a one-time login URL after all
selected preset operations succeed.

#### Scenario: Select a preset destination

- **WHEN** a preset assigns an administration or demonstration path
- **THEN** the generated login URL targets the last assigned path
- **AND** an install without such a preset targets `/`
