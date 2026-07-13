# Foundation: Drupal Core Specification

## Purpose

Define the clean Drupal site baseline and the version-specific behavior shared by
all Drupal Playground ecosystems.

## Requirements

### Requirement: Destructive clean installation

`ddev install` SHALL replace the current database with a new standard-profile
Drupal site.

#### Scenario: Start an installation

- **WHEN** `ddev install` runs
- **THEN** it drops the existing Drupal database without preserving its contents
- **AND** installs the standard profile against the DDEV database
- **AND** creates the Drupal Playground site and administrator account using the tracked local credentials

### Requirement: Installed-version detection

The install command SHALL derive compatibility behavior from the Drupal version
that was installed, not from a separately supplied flag.

#### Scenario: Determine the major version

- **WHEN** site installation completes
- **THEN** Drush evaluates `Drupal::VERSION`
- **AND** the command derives the major version from that value

### Requirement: Drupal 10 administration baseline

Drupal 10 installs SHALL use the Gin administration theme and SHALL remove core
features that are not used by the demos.

#### Scenario: Configure Drupal 10

- **WHEN** the installed major version is 10 or lower
- **THEN** the Gin administration Recipe is applied
- **AND** Toolbar is uninstalled
- **AND** the Article comment field and Comment module are removed

### Requirement: Drupal 11 administration baseline

Drupal 11 installs SHALL use the core Default Admin theme.

#### Scenario: Configure Drupal 11

- **WHEN** the installed major version is greater than 10
- **THEN** the Default Admin Recipe is applied
- **AND** Claro is uninstalled after the new administration theme is selected

### Requirement: Shared site capabilities

Every clean installation SHALL receive the shared content, media,
administration, development, and AI foundation before ecosystem-specific work.

#### Scenario: Install without optional presets

- **WHEN** `ddev install` is invoked with no presets
- **THEN** the resulting site contains the base content and media Recipes, shared administration tools, Devel tooling, and the foundational AI configuration
