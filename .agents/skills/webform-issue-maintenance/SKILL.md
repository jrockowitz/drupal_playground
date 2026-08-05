---
name: webform-issue-maintenance
description: Use when working on public Drupal.org Webform module issues, including queue scouting, local issue tracking, issue-fork review, or scoped Webform contribution work.
---

# Webform Issue Maintenance

Use `drupalorg-issue-maintenance` for the shared issue-maintenance workflow.
This profile supplies the Webform project defaults and public-issue boundary.

## Webform project profile

| Input | Default                                             |
|---|-----------------------------------------------------|
| Drupal.org machine name | `webform`                                           |
| Workspace | `~/Sites/<project>`              |
| Module path | `web/modules/sandbox/webform`                       |
| Target branch | `6.3.x`, unless the human specifies another version |
| Tracker path | `.agents/webform-issue-maintenance/`                |

When a target version is named, use its branch for local checkout, patch
testing, merge-request review, comment drafts, and backport work. Report any
mismatch between that branch and the issue or merge-request target before
editing or posting a draft.

## Route the task

Load the corresponding parent reference before proceeding:

| Task | Read |
|---|---|
| Scout or traverse the Webform queue | `drupalorg-issue-maintenance/references/queue-traversal.md` |
| Review, reproduce, test, fix, or draft a comment | `drupalorg-issue-maintenance/references/issue-workflow.md` |
| Create or update Webform issue notes | `drupalorg-issue-maintenance/references/local-tracker.md` |

Run these commands from `~/Sites/<project>`. If the module
has uncommitted changes, decide whether they belong to the selected public issue
before continuing.

## Public-only boundary

Use `webform-security` instead of this skill for security-sensitive issues,
private details, exploit prose, or confidential Drupal.org/GitLab data. Do not
place that material in `.agents/webform-issue-maintenance/`.

The parent skill’s explicit-approval gates apply to every public action. For
authorized local code work, use relevant process skills such as
`systematic-debugging`, `test-driven-development`, and
`verification-before-completion`.

