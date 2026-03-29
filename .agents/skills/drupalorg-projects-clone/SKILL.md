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

Discover all projects maintained by a Drupal.org user and clone selected ones as git-backed Composer repositories for local contribution work. Use this skill when the starting point is a **username**, not a project name. If you already know the project machine name, use `drupalorg-project-clone` directly.

## When to Use This Skill

Activate when the user:
- Says "clone all projects by {username}"
- Says "clone projects maintained by {username}"
- Says "set up local dev for {username}'s modules"
- Says "what projects does {username} maintain?"

**Do NOT use** when the user provides a specific project machine name such as "clone webform". Use `drupalorg-project-clone` instead.

## Workflow

### 1. Resolve username

If the Drupal.org username is not provided in the prompt, ask:

> "What is the Drupal.org username whose maintained projects you want to clone?"

### 2. Validate username

Fetch `https://www.drupal.org/u/{username}`.

- **Valid**: the canonical profile URL resolves and the body is clearly a user profile page
- **Invalid**: HTTP 404, redirect to the homepage, generic "page not found" body, or no maintained-project area can be isolated

On invalid input, report the problem and prompt the user to re-enter the username, then retry once.

### 3. Extract maintained projects

Parse the maintained-project area from the profile HTML. Each entry has the form:

```html
<a href="/project/{machine_name}">Display Name</a>
```

Parsing guidance:
- Prefer the profile content region first
- Look for the maintained-project section or equivalent heading
- Extract links that match `/project/{machine_name}`
- If the exact heading changes, use nearby profile structure to isolate the maintained-project list
- If multiple plausible sections exist, fail clearly instead of guessing

Collect for each project:
- **Display name** — the link text
- **Machine name** — the `{machine_name}` path segment
- **Project URL** — `https://www.drupal.org/project/{machine_name}`

If no maintained-project section can be isolated, report a parsing failure and stop. Do not silently return an empty list.

### 4. Resolve type and retrieve releases for each project

For every discovered project fetch:
- `https://www.drupal.org/project/{machine_name}/git-instructions`
- `drupalorg project:releases {machine_name} --format=llm`

All metadata and `project:releases` calls may run in parallel.

From `git-instructions` and related Drupal.org page metadata, resolve:
- **Project type** — module, theme, or recipe
- **Recommended branch**
- **Expected install path**

Only default to module if Drupal.org truly does not expose the type.

For each project, select a version using this precedence:

1. Most recent stable release with no `-dev`, `-alpha`, `-beta`, or `-rc` suffix.
2. Most recent release of any kind, labelled `(pre-release)`.
3. No releases found, displayed as `—` with label `(no releases — sandbox/dev only)`.
4. Command fails, displayed as `—` with label `(releases unavailable)`.

Collect all project metadata and release data before presenting the selection list.

### 5. [PAUSE] Present selection list

Display a formatted table and wait for user input before proceeding:

```
Projects maintained by {username}:

  #   Project                  Machine name         Type     Version
  1.  Display Name             machine_name         module   2.1.0
  2.  Another Project          another_project      theme    1.0.x-dev     (pre-release)
  3.  Sandbox Thing            sandbox_thing        recipe   —             (no releases — sandbox/dev only)

Enter numbers to clone (comma-separated), or "all" to clone everything:
```

If useful, append the expected install path for each row or include it in a follow-up summary before asking for selection.

Accept:
- `all` — clone every project in the list
- Comma-separated integers — clone the listed items, for example `1,3`

Re-prompt once on invalid input. Stop without cloning if the user enters `0`, `none`, or cancels.

### 6. Clone selected projects

For each selected project **in order, one at a time**. Composer operations must not run in parallel because each clone updates shared dependency files and `composer.lock`.

Invoke the `drupalorg-project-clone` skill with:
- the project machine name
- the resolved project type
- the recommended branch only if you want to force the discovered branch explicitly

Do not delegate using machine name alone when the type has already been resolved.

### 7. Print summary

After all projects are processed:

```
Cloned N project(s):
  - Display Name (machine_name, module) → web/modules/sandbox/machine_name/
```

List the actual install path for each project as reported by `drupalorg-project-clone`.

## Key Design Decisions

| Decision | Rationale |
|---|---|
| WebFetch for profile, not `drupalorg-cli` | `maintainer:issues` lists issues, not maintained projects. The profile page is canonical. |
| Use `/git-instructions` for per-project metadata | It is the authoritative Drupal.org source for clone URL and recommended branch, and supports type/path resolution when combined with project page metadata. |
| Check canonical profile body, not just `<title>` | Drupal.org may return 200 for invalid users with a generic not-found body. |
| Collect all releases before showing list | Cleaner UX. The list appears once, complete, instead of building incrementally. |
| Delegate to `drupalorg-project-clone` with resolved type | Avoids silent misclassification of themes or recipes as modules. |
| Sequential cloning | Each clone updates shared dependency files and `composer.lock`. Parallel runs would conflict. |

## Dependencies

- `drupalorg-project-clone` skill — handles each individual project clone
- `drupalorg-cli` skill — provides `project:releases --format=llm`
