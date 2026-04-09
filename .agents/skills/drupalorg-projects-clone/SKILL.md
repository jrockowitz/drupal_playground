---
name: drupalorg-projects-clone
description: >
  Discovers all Drupal.org projects maintained by a given username and clones
  selected ones as git repositories into the local Composer sandbox. Use this
  skill when the starting point is a Drupal.org username, not a known project
  machine name. Fetches the maintainer's profile page, resolves each project's
  type and recommended branch from Drupal.org git-instructions, retrieves release
  info for each project, presents a numbered selection list, then delegates cloning
  to the drupalorg-project-clone skill with the resolved type.
---

# Drupal.org Projects Clone Skill

Discover all projects maintained by a Drupal.org user and clone selected ones as git-backed Composer repositories. If you already know the project machine name, use `drupalorg-project-clone` directly.

## Workflow

### 1. Resolve username

If not provided, ask: "What is the Drupal.org username whose maintained projects you want to clone?"

### 2. Validate username

Fetch `https://www.drupal.org/u/{username}`. Valid if the body is clearly a user profile page. On invalid (404, generic not-found body, no maintained-project area), report the problem and prompt once to retry.

### 3. Extract maintained projects

Parse the maintained-project section from the profile HTML. Extract links matching `/project/{machine_name}`. Collect display name, machine name, and project URL. If no maintained-project section can be isolated, report a parsing failure and stop.

### 4. Resolve type and releases for each project

Fetch in parallel for every project:
- `https://www.drupal.org/project/{machine_name}/git-instructions` — resolves project type, recommended branch, and install path
- `drupalorg project:releases {machine_name} --format=llm` — resolves version

Version precedence:
1. Most recent stable release (no `-dev`, `-alpha`, `-beta`, `-rc`)
2. Most recent pre-release, labelled `(pre-release)`
3. No releases → `—` with `(no releases — sandbox/dev only)`
4. Command fails → `—` with `(releases unavailable)`

Only default to module type if Drupal.org truly does not expose it.

### 5. [PAUSE] Present selection list

Display a table and wait for user input:

```
Projects maintained by {username}:

  #   Project                  Machine name         Type     Version
  1.  Display Name             machine_name         module   2.1.0
  2.  Another Project          another_project      theme    1.0.x-dev     (pre-release)
  3.  Sandbox Thing            sandbox_thing        recipe   —             (no releases — sandbox/dev only)

Enter numbers to clone (comma-separated), or "all" to clone everything:
```

Accept `all` or comma-separated integers. Re-prompt once on invalid input. Stop on `0`, `none`, or cancel.

### 6. Clone selected projects

Clone **one at a time** (Composer operations must not run in parallel — each updates shared dependency files and `composer.lock`).

Invoke `drupalorg-project-clone` with the machine name, resolved project type, and recommended branch if forcing explicitly.

### 7. Print summary

```
Cloned N project(s):
  - Display Name (machine_name, module) → web/modules/sandbox/machine_name/
```

## Dependencies

- `drupalorg-project-clone` — handles each individual project clone
- `drupalorg-cli` — provides `project:releases --format=llm`
