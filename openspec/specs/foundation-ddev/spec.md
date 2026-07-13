# Foundation: DDEV Specification

## Purpose

Define the shared local runtime and command surface used by every Drupal
Playground worktree.

## Requirements

### Requirement: Directory-derived project identity

The tracked DDEV configuration SHALL omit an explicit project name so DDEV can
derive a unique identity from each worktree directory.

#### Scenario: Start sibling worktrees

- **WHEN** DDEV starts from two differently named worktree directories
- **THEN** each directory receives its own DDEV project identity and routed URL
- **AND** the worktrees do not share a database or container set

### Requirement: Shared Drupal runtime

Every worktree SHALL inherit the tracked Drupal runtime configuration.

#### Scenario: Inspect the default runtime

- **WHEN** DDEV reads `.ddev/config.yaml`
- **THEN** the project type is Drupal
- **AND** the document root is `web`
- **AND** PHP 8.3, MariaDB 10.11, Nginx FPM, Composer 2, and Corepack are configured

### Requirement: Project command surface

DDEV SHALL expose the repository's host and web commands consistently from each
worktree.

#### Scenario: Use a shared development command

- **WHEN** a developer invokes a tracked command such as `ddev install`, `ddev phpunit`, `ddev code-review`, `ddev code-fix`, or `ddev worktree`
- **THEN** DDEV resolves the command from `.ddev/commands` in the active worktree

### Requirement: Optional ecosystem services

The DDEV foundation SHALL make tracked service definitions available to
ecosystems that need them without requiring every install preset to use them.

#### Scenario: Use search or browser-testing infrastructure

- **WHEN** an ecosystem workflow requires Elasticsearch, Milvus, or Selenium Chrome
- **THEN** the corresponding tracked DDEV service configuration is available to that worktree
