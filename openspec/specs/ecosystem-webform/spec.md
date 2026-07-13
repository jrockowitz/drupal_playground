# Ecosystem: Webform Specification

## Purpose

Define the current Webform workspace and its setup and test installation modes.

## Requirements

### Requirement: Webform worktree identity

Webform ecosystem work SHALL use the `drupal_webform` worktree and the shared
foundation specifications.

#### Scenario: Begin focused Webform work

- **WHEN** a change primarily targets Webform or its bundled integrations and examples
- **THEN** the developer selects `drupal_webform`

### Requirement: Webform setup preset

The `webform` and `webform-setup` presets SHALL apply the Webform setup Recipe.

#### Scenario: Install the Webform development site

- **WHEN** `ddev install webform` or `ddev install webform-setup` runs
- **THEN** Webform, its UI and integration modules, development tooling, templates, examples, and supporting modules are installed with their tracked configuration
- **AND** public Webform-managed files are enabled
- **AND** the generated login URL targets `/admin/structure/webform`

### Requirement: Webform test preset

The `webform-test` preset SHALL layer test dependencies and fixtures on top of
the setup Recipe.

#### Scenario: Install Webform test coverage

- **WHEN** `ddev install webform-test` runs
- **THEN** the setup Recipe runs before the Webform Test Recipe
- **AND** tracked Webform test modules and their configuration are installed
- **AND** `webform_node_test_multiple` and `webform_test_submissions` are enabled after Recipe application because their install hooks require existing entities

### Requirement: Sandbox dependency composition

The Webform worktree SHALL use the foundation Composer manifests to develop the
checked-out Webform project and its library requirements.

#### Scenario: Install Webform dependencies

- **WHEN** Composer resolves the worktree
- **THEN** the configured Webform sandbox package and Webform-owned Composer manifests participate in dependency resolution
