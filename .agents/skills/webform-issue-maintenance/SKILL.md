---
name: webform-issue-maintenance
description: Use when asked to inspect, triage, prioritize, reproduce, fix, test, review, summarize, or draft comments for Drupal.org Webform module issues.
---

# Webform Issue Maintenance

## Overview

Use this skill to help maintain the Drupal Webform module issue queue with
`drupalorg-cli`, local Webform tests, and maintainer-safe guardrails. Agents
reduce queue friction; they do not replace maintainer judgment.

Primary reference: `/Users/rockowij/Sites/drupal_webform/docs/WEBFORM-AGENTS.md`.

## Required First Steps

Run these before Drupal.org issue work:

```bash
cd /Users/rockowij/Sites/drupal_webform
drupalorg --version
drupalorg skill:get drupalorg-cli
git -C web/modules/sandbox/webform status --short
git -C web/modules/sandbox/webform branch --show-current
```

Use `drupalorg-cli` before raw Drupal.org API calls. Pass `--format=llm` to
read commands and add `--no-cache` when recently changed issue data matters.

## Guardrails

Do not close issues, change issue status, post comments, assign users, create
labels, commit, push, open merge requests, or make security claims unless the
human explicitly asks in the current conversation.

Pause before changing public APIs, permissions, access policy, update hooks,
large generated config, or behavior that contradicts existing tests.

## Queue Scouting

Use this lane when the user asks what to work on or wants a maintainer digest.

```bash
drupalorg project:issues webform review --limit=25 --format=llm
drupalorg project:issues webform all --limit=25 --format=llm
drupalorg project:issues webform rtbc --limit=25 --format=llm
drupalorg issue:search webform "access" --status=open --limit=10 --format=llm
drupalorg issue:search webform "cache" --status=open --limit=10 --format=llm
drupalorg issue:search webform "Drupal 11" --status=open --limit=10 --format=llm
```

Recommend 3-5 issues, not a full queue dump. For each candidate include issue
URL, status, priority/category when available, likely work lane, why it matters,
why it is or is not suitable for agent work, and the first verification command.

Use these labels in recommendations:

- Best fix target
- Best test target
- Best review target
- Good reproduction target
- Needs maintainer decision
- Needs human reproduction
- Probably not worth agent time yet

## Scoring Heuristics

Prefer issues with high impact, clear reproduction, local testability, and narrow
scope. Good agent targets involve data loss, access/security-adjacent behavior,
Drupal compatibility, release blockers, broken submissions, or test failures.

Avoid or defer issues that depend on private sites, external services, broad
architecture, unclear product behavior, permission-policy decisions, public API
changes, or large stale patches without tests.

## Per-Issue Workflow

1. Load issue details and comments:

```bash
drupalorg issue:show <issue-id> --with-comments --format=llm
```

2. Load MR/fork context:

```bash
drupalorg mr:list project/webform --format=llm
drupalorg issue:get-fork <issue-id> --format=llm
```

Match the issue number in MR titles or source branches, then use the MR IID with
the quoted `project/webform!<merge-request-iid>` form:

```bash
drupalorg mr:files 'project/webform!<merge-request-iid>' --format=llm
drupalorg mr:diff 'project/webform!<merge-request-iid>' --format=llm
drupalorg mr:status 'project/webform!<merge-request-iid>' --format=llm
```

3. Classify the work as triage, reproduce, test, fix, review, summary update, or
maintainer comment draft.

4. Inspect local code with `rg` before editing:

```bash
rg -n "RelevantClass|relevant_method|config_name" web/modules/sandbox/webform
```

5. Reproduce the issue or state why reproduction is blocked.
6. Write a failing test before fixing when practical.
7. Make the smallest code/config change that satisfies the issue and tests.
8. Run targeted verification.
9. Summarize evidence and uncertainty.

## Working Examples

Use these current Webform examples as command patterns. Re-check live issue state
before acting.

Focused access fix:

```bash
drupalorg issue:show 3591835 --with-comments --format=llm --no-cache
drupalorg issue:get-fork 3591835 --format=llm --no-cache
drupalorg mr:files 'project/webform!870' --format=llm --no-cache
drupalorg mr:diff 'project/webform!870' --format=llm --no-cache
drupalorg mr:status 'project/webform!870' --format=llm --no-cache
rg -n "checkAccessRules|AccessResultInterface|#access" web/modules/sandbox/webform/src web/modules/sandbox/webform/tests
```

Drush Composer libraries bug:

```bash
drupalorg issue:show 3470339 --with-comments --format=llm --no-cache
drupalorg issue:get-fork 3470339 --format=llm --no-cache
rg -n "setComposerLibraries|webform:composer:update|repositories" web/modules/sandbox/webform
```

Access restriction policy review:

```bash
drupalorg issue:show 3463152 --with-comments --format=llm --no-cache
drupalorg issue:get-fork 3463152 --format=llm --no-cache
rg -n "webform submissions|webform_submission|access content|display_options" web/modules/sandbox/webform/config web/modules/sandbox/webform/tests
```

## Local Verification

Run commands from `/Users/rockowij/Sites/drupal_webform`.

```bash
ddev phpunit <file-or-directory>
ddev code-review <file-or-directory>
ddev code-fix <file-or-directory>
ddev drush config:import -y --partial --source=<directory>
```

Use targeted PHPUnit and `ddev code-review` commands first. Broaden verification
when touched code has broad impact.

## Output Formats

Queue scout report:

- commands used and scan date
- top 3-5 candidates
- work lane and suitability notes for each
- issues not worth agent time yet

Review findings:

- issue and MR inspected
- changed files and tests present/missing
- CI/pipeline status when available
- concrete findings with local file references
- suggested comment draft, but do not post it

Maintainer comment draft:

```markdown
I reviewed this locally against Webform 6.3.x.

What I checked:
- ...

Commands run:
- `ddev phpunit ...`
- `ddev code-review ...`

Result:
- ...

Remaining question:
- ...
```
