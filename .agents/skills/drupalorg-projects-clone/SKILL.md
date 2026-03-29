---
name: drupalorg-projects-clone
description: >
  Discovers all Drupal.org projects maintained by a given username and clones
  selected ones as git repositories into the local Composer sandbox. Use this
  skill when the starting point is a Drupal.org username, not a known project
  machine name. Fetches the maintainer's profile page, retrieves release info
  for each project, presents a numbered selection list, then delegates cloning
  to the drupalorg-project-clone skill.
---

# Drupal.org Projects Clone Skill

Discover all projects maintained by a Drupal.org user and clone selected ones as git-backed Composer repositories for local contribution work. Use this skill when the starting point is a **username**, not a project name. If you already know the project machine name, use `drupalorg-project-clone` directly.

## When to Use This Skill

Activate when the user:
- Says "clone all projects by {username}"
- Says "clone projects maintained by {username}"
- Says "set up local dev for {username}'s modules"
- Says "what projects does {username} maintain?"

**Do NOT use** when the user provides a specific project machine name (e.g. "clone webform") — use `drupalorg-project-clone` instead.

## Workflow

### 1. Resolve username

If the Drupal.org username is not provided in the prompt, ask:

> "What is the Drupal.org username whose maintained projects you want to clone?"

### 2. Validate username

Fetch `https://www.drupal.org/u/{username}`.

- **Valid**: HTTP 200 and page `<title>` contains the username
- **Invalid**: HTTP 404, redirect to the homepage, or page title does not contain the username (Drupal.org may return 200 for unknown users with a generic "not found" page)

On invalid input, report the problem and prompt the user to re-enter the username, then retry once.

### 3. Extract maintained projects

Parse the "Projects maintained" section from the profile HTML. Each entry has the form:

```html
<a href="/project/{machine_name}">Display Name</a>
```

Collect for each project:
- **Display name** — the link text
- **Machine name** — the `{machine_name}` path segment
- **Project URL** — `https://www.drupal.org/project/{machine_name}`

If no "Projects maintained" section is found, report this and stop.

### 4. Retrieve releases for each project

For every discovered project run:

```bash
drupalorg project:releases {machine_name} --format=llm
```

All `project:releases` calls may run in parallel. For each project, select a version using this precedence:

1. Most recent stable release (no `-dev`, `-alpha`, `-beta`, or `-rc` suffix)
2. Most recent release of any kind → append label `(pre-release)`
3. No releases found → display `—` with label `(no releases — sandbox/dev only)`
4. Command fails → display `—` with label `(releases unavailable)`

Collect all release data before presenting the selection list.

### 5. [PAUSE] Present selection list

Display a formatted table and wait for user input before proceeding:

```
Projects maintained by {username}:

  #   Project                  Machine name         Version
  1.  Display Name             machine_name         2.1.0
  2.  Another Project          another_project      1.0.x-dev     (pre-release)
  3.  Sandbox Thing            sandbox_thing        —             (no releases — sandbox/dev only)

Enter numbers to clone (comma-separated), or "all" to clone everything:
```

Accept:
- `all` — clone every project in the list
- Comma-separated integers — clone the listed items (e.g. `1,3`)

Re-prompt once on invalid input. Stop without cloning if the user enters `0`, `none`, or cancels.

### 6. Clone selected projects

For each selected project **in order, one at a time** — Composer operations must not run in parallel because each `ddev composer require` modifies `composer.sandbox.json` and `composer.lock`.

Invoke the `drupalorg-project-clone` skill with the machine name of each project.

### 7. Print summary

After all projects are processed:

```
Cloned N project(s):
  - Display Name (machine_name) → web/modules/sandbox/machine_name/
```

List the actual install path for each project as reported by `drupalorg-project-clone`.

## Key Design Decisions

| Decision | Rationale |
|---|---|
| WebFetch for profile, not `drupalorg-cli` | `maintainer:issues` lists issues, not maintained projects. The profile page is canonical. |
| Check HTTP status + title for validation | Drupal.org may return 200 for invalid users with a "page not found" body. |
| Collect all releases before showing list | Cleaner UX — the list appears once, complete, instead of building incrementally. |
| Delegate to `drupalorg-project-clone` | Already handles project type detection, branch resolution, composer.sandbox.json, installer paths, ddev composer require, and verification. No duplication. |
| Sequential (not parallel) cloning | Each `ddev composer require` modifies `composer.sandbox.json` and `composer.lock`. Parallel runs would conflict. |

## Dependencies

- `drupalorg-project-clone` skill — handles each individual project clone
- `drupalorg-cli` skill — provides `project:releases --format=llm`
