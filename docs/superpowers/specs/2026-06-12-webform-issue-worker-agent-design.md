# Webform Issue Worker Agent Design

**Date:** 2026-06-12
**Status:** Draft for review

## Context

The Webform project already has local maintenance guidance in `docs/WEBFORM-AGENTS.md` and a `webform-issue-maintenance` skill. Those workflows describe how an agent can inspect Drupal.org issues, load issue forks and merge requests, reproduce bugs, write regression tests, make narrow fixes, and stop before public maintainer actions.

The missing piece is an on-demand agent workflow that turns those instructions into a repeatable loop:

1. Scan the Webform issue queue.
2. Present a short list of candidate issues.
3. Let the maintainer choose which issues to work on.
4. Work locally on the selected issues.
5. Stop for maintainer code review before commit, push, or Drupal.org updates.

This agent should reduce queue friction without becoming an autonomous maintainer.

## Goals

- Create an on-demand Webform issue worker workflow.
- Scan Webform issues for good test, fix, review, or reproduction targets.
- Rank candidates using Webform-specific suitability criteria.
- Let the maintainer select issues before local code work begins.
- Work locally on selected issues using Drupal.org issue forks or merge request context when available.
- Prefer regression tests before implementation when practical.
- Run targeted local verification before presenting work for review.
- Require explicit maintainer approval before commit, push, issue comments, status changes, labels, assignments, or merge request updates.

## Non-Goals

- No unattended recurring monitor.
- No automatic selection of top issues without maintainer confirmation.
- No automatic Drupal.org posting, status changes, labels, assignments, commits, pushes, or merge request updates.
- No broad refactoring unrelated to the selected issue.
- No security claims beyond issue text and local evidence.
- No work on issues that require product, permission, public API, or release-policy decisions unless the maintainer explicitly asks for evidence gathering only.

## Workflow

### 1. Enable And Scan

The maintainer asks the agent to scan, optionally including a target count:

```text
Scan Webform issues and find 5 good candidates.
```

The scan uses `drupalorg-cli` first:

```bash
drupalorg project:issues webform review --limit=25 --format=llm
drupalorg project:issues webform all --limit=25 --format=llm
drupalorg project:issues webform rtbc --limit=25 --format=llm
drupalorg issue:search webform "test" --status=open --limit=10 --format=llm
drupalorg issue:search webform "regression" --status=open --limit=10 --format=llm
drupalorg issue:search webform "fatal error" --status=open --limit=10 --format=llm
```

The exact searches can vary based on the maintainer's prompt, but every read command should use `--format=llm`.

### 2. Candidate Report

The agent presents a short ranked list, not a full queue dump. Each candidate includes:

- Issue URL.
- Issue status, category, and priority when available.
- Suggested lane: test, fix, review, reproduce, or maintainer decision.
- Why the issue appears suitable for agent work.
- Why it may be risky or unsuitable.
- First likely local verification command or reproduction step.

The report uses the existing labels from `webform-issue-maintenance`:

- Best fix target.
- Best test target.
- Best review target.
- Good reproduction target.
- Needs maintainer decision.
- Needs human reproduction.
- Probably not worth agent time yet.

### 3. Selection Gate

The agent stops after the candidate report and asks the maintainer to select issue numbers or URLs.

No local issue branch checkout or code edits happen before selection.

### 4. Issue Preparation

For each selected issue, the agent loads the full public context:

```bash
drupalorg issue:show <issue-id> --with-comments --format=llm --no-cache
drupalorg issue:get-fork <issue-id> --format=llm --no-cache
drupalorg mr:list project/webform --format=llm --no-cache
```

When a matching merge request exists, the agent also inspects:

```bash
drupalorg mr:files 'project/webform!<merge-request-iid>' --format=llm --no-cache
drupalorg mr:diff 'project/webform!<merge-request-iid>' --format=llm --no-cache
drupalorg mr:status 'project/webform!<merge-request-iid>' --format=llm --no-cache
```

Before touching local code, the agent checks the sandbox state:

```bash
git -C web/modules/sandbox/webform status --short
git -C web/modules/sandbox/webform branch --show-current
```

If unrelated local changes are present, the agent must preserve them and avoid overwriting them.

### 5. Local Work

The agent works one selected issue at a time.

For each issue, it should:

1. Classify the issue as test, fix, review, reproduce, or evidence-only.
2. Search local code with `rg`.
3. Reproduce the issue when practical.
4. Write a failing regression test before changing implementation code when practical.
5. Make the smallest code or config change needed.
6. Run targeted verification.
7. Record evidence and uncertainty.

Targeted commands should use the existing local wrappers:

```bash
ddev phpunit <file-or-directory>
ddev code-review <file-or-directory>
```

If config changes are involved, the agent should test partial config import:

```bash
ddev drush config:import -y --partial --source=<directory>
```

### 6. Review Gate

After local work, the agent stops before commit or push and presents:

- Issue worked on.
- Branch or fork used.
- Changed files.
- Summary of test or fix behavior.
- Commands run and results.
- Remaining uncertainty.
- Suggested commit message.
- Suggested Drupal.org comment draft.

The maintainer reviews the local code before any publication action.

### 7. Approval-Only Publication

Only after explicit maintainer approval in the current conversation may the agent:

- Commit local changes.
- Push to a Drupal.org issue fork.
- Create or update a merge request.
- Post a Drupal.org comment.
- Change issue status.
- Assign users.
- Add or remove labels.

AI-created commits must end with:

```text
AI-assisted by Codex
```

## Suitability Scoring

The agent should prefer issues with:

- Clear reproduction steps.
- Narrow affected code.
- Existing nearby test patterns.
- Deterministic expected behavior.
- High user impact such as submission failure, data loss, access-adjacent behavior, Drupal compatibility, or test failures.
- Existing merge requests that are small and missing only tests or review.

The agent should avoid or classify as evidence-only issues with:

- Private-site-only reproduction.
- External service dependencies.
- Broad product decisions.
- Permission policy decisions.
- Public API changes.
- Large stale patches without clear expected behavior.
- Security-sensitive claims that need maintainer or security-team judgment.

## Safety Rules

- Work from issue details and comments, not titles alone.
- Use `drupalorg-cli` before raw API calls.
- Use `--format=llm` for Drupal.org read commands.
- Use `--no-cache` when recent issue data matters.
- Preserve dirty local worktrees.
- Do not overwrite maintainer changes.
- Do not publish externally without explicit approval.
- Prefer small, reviewable changes.
- Prefer tests over speculative fixes.
- Stop when an issue requires maintainer judgment instead of code.

## Expected Agent Command Shape

The first implementation can be a documented agent workflow rather than a separate executable. The maintainer-facing prompts should be simple:

```text
Scan Webform issues and suggest 5 candidates.
```

```text
Work on issues 3593810 and 3576355 locally.
```

```text
Commit and push the fix for 3593810.
```

Each prompt maps to a different approval stage. The agent should never infer approval for a later stage from approval of an earlier one.

## Open Implementation Choice

There are two reasonable implementation paths:

1. Extend the existing `webform-issue-maintenance` skill with this staged workflow.
2. Create a new `webform-issue-worker` skill that calls into the existing maintenance guidance.

The recommended path is to create a new `webform-issue-worker` skill. That keeps queue scouting and per-issue maintenance reusable while giving this on-demand batch workflow a clear trigger and explicit approval gates.
