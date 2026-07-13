# Foundation: Git Worktree Specification

## Purpose

Define how Drupal Playground creates isolated working directories and assigns
responsibility to its current worktrees.

## Requirements

### Requirement: Safe worktree target validation

The `ddev worktree` command SHALL require one target path, an existing parent
directory, a nonexistent target, and explicit confirmation before mutation.

#### Scenario: Reject an invalid target

- **WHEN** the target argument is missing, its parent does not exist, or the target already exists
- **THEN** the command exits before creating or copying a worktree

#### Scenario: Cancel creation

- **WHEN** the user does not confirm with an accepted affirmative response
- **THEN** the command reports cancellation and makes no worktree

### Requirement: Worktree creation and local-state transfer

The command SHALL create the Git worktree before copying reusable local checkout
state into it.

#### Scenario: Copy local state

- **WHEN** a target is confirmed
- **THEN** `git worktree add` creates the tracked checkout
- **AND** `rsync` copies local files while excluding Git metadata, installed Composer and Node dependencies, scaffolded core and contrib code, generated files, test artifacts, and Milvus volumes

### Requirement: Worktree bootstrap sequence

A new worktree SHALL be initialized through the shared local toolchain.

#### Scenario: Complete worktree setup

- **WHEN** the tracked checkout and local files are present
- **THEN** the command runs `ddev start`, `ddev composer install`, `ddev install`, and `npx skill update` in that order
- **AND** any failed command stops the sequence

### Requirement: Current worktree registry

The project SHALL recognize the current worktrees by their working purpose.

#### Scenario: Select a workspace

- **WHEN** work is general or cross-ecosystem, including ad hoc contributed-module enablement
- **THEN** the developer uses `main`

#### Scenario: Select the compatibility lane

- **WHEN** work targets Drupal 10 compatibility
- **THEN** the developer uses `drupal_10`

#### Scenario: Select an ecosystem worktree

- **WHEN** work targets Drupal AI, Webform, Schema.org, or ClinicalTrials.gov
- **THEN** the developer uses `drupal_ai`, `drupal_webform`, `drupal_schemadotorg`, or `drupal_clinical_trials_gov` respectively
