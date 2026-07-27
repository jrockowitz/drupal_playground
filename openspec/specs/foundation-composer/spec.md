# Foundation: Composer Specification

## Purpose

Define how Drupal Playground composes a reproducible codebase from focused
Composer manifests for Drupal versions, Recipes, libraries, and sandbox work.

## Requirements

### Requirement: Root dependency orchestration

The root `composer.json` SHALL own project-wide requirements, installer paths,
patches, and the ordered Composer Merge Plugin include list.

#### Scenario: Resolve the default Drupal 11 codebase

- **WHEN** Composer resolves dependencies from `main` or a Drupal 11 ecosystem worktree
- **THEN** it includes `composer.drupal_11.json`, `composer.lenient.json`, `composer.libraries.json`, `composer.recipes.json`, and `composer.drupal_11.sandbox.json`
- **AND** it merges the Webform library manifest

#### Scenario: Resolve the Drupal 10 codebase

- **WHEN** Composer resolves dependencies from `drupal_10`
- **THEN** the root include list selects `composer.drupal_10.json` and `composer.drupal_10.sandbox.json` instead of their Drupal 11 counterparts

### Requirement: Focused manifest responsibilities

Dependencies SHALL be separated by responsibility so that version constraints,
Recipe packages, external libraries, compatibility exceptions, and sandbox
projects can evolve independently.

#### Scenario: Locate a dependency category

- **WHEN** a developer or AI agent inspects dependency composition
- **THEN** Drupal-version requirements are found in `composer.drupal_10.json` or `composer.drupal_11.json`
- **AND** Recipe packages are found in `composer.recipes.json`
- **AND** browser libraries are found in `composer.libraries.json`
- **AND** lenient compatibility configuration is found in `composer.lenient.json`
- **AND** sandbox project packages are found in the matching version-specific sandbox manifest: `composer.drupal_10.sandbox.json` on `drupal_10` and `composer.drupal_11.sandbox.json` on `main` and Drupal 11 ecosystem worktrees

### Requirement: Local and nested project dependencies

Composer SHALL support local Recipe path repositories, Drupal.org sandbox
package definitions, and dependency manifests owned by checked-out projects.

#### Scenario: Compose checked-out project requirements

- **WHEN** an included Drupal module owns a `composer.json` or `composer.libraries.json`
- **THEN** the applicable version or root manifest merges that file into the project dependency graph

### Requirement: Reproducible installation

The committed `composer.lock` SHALL record the resolved dependency graph used by
worktrees on its branch, including that branch's version-specific sandbox
manifest.

#### Scenario: Bootstrap a worktree

- **WHEN** `ddev composer install` runs in a new worktree
- **THEN** Composer installs the versions recorded by that branch's lockfile and matching sandbox manifest into the configured Drupal installer paths
