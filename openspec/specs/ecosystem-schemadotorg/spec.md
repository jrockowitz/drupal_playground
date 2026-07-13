# Ecosystem: Schema.org Specification

## Purpose

Define the current Schema.org workspace and the Recipe-driven Schema.org
Blueprints development site.

## Requirements

### Requirement: Schema.org worktree identity

Schema.org ecosystem work SHALL use the `drupal_schemadotorg` worktree and the
shared foundation specifications.

#### Scenario: Begin focused Schema.org work

- **WHEN** a change primarily targets Schema.org Blueprints, its integrations, or its Recipes
- **THEN** the developer selects `drupal_schemadotorg`

### Requirement: Schema.org preset

The `schemadotorg` preset SHALL apply the Drupal Playground Schema.org
Blueprints Recipe.

#### Scenario: Install Schema.org Blueprints

- **WHEN** `ddev install schemadotorg` runs
- **THEN** the Recipe composes the Drupal Playground base and core editorial workflow Recipes
- **AND** installs Schema.org core, content-model, translation, development, JSON:API, JSON-LD, Recipe, and contributed-integration modules
- **AND** imports the tracked supporting configuration
- **AND** the generated login URL targets `/admin/config/schemadotorg`

### Requirement: Schema.org dependency composition

The ecosystem SHALL compose the checked-out Schema.org sandbox projects,
starter kits, external Recipe repository, and project-owned library manifests.

#### Scenario: Resolve Schema.org development dependencies

- **WHEN** Composer installs the Schema.org worktree
- **THEN** the foundation manifests provide the configured Schema.org sandbox packages and Recipes
- **AND** the Drupal-version manifest merges applicable Schema.org library manifests

### Requirement: Drupal-version compatibility

The ecosystem SHALL inherit the foundation's Drupal 10 and Drupal 11 dependency
and installation lanes.

#### Scenario: Work on a version-specific Schema.org issue

- **WHEN** compatibility must be evaluated against Drupal 10
- **THEN** the developer uses the `drupal_10` lane and its version manifest rather than changing the Schema.org ecosystem identity
