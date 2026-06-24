---
name: webform-security-gitlab
description: Use when working on private Webform security issues in Drupal GitLab.
---

# Webform Security GitLab

Before task actions, read and follow `../webform-security/SKILL.md` for shared Webform security guardrails.

## Browser

Use the Codex in-app Browser before CLI discovery. Open:

```text
https://git.drupalcode.org/search?group_id=183118&scope=issues&search=%22Project-webform%22
```

Pause for human login before inspecting private data. Prefer dedicated Browser controls. If only Playwright-backed controls exist and the human asked not to use Playwright, report that limitation and pause.

Never click `Comment`; draft or place text only, then stop for human action.

## Private Notes

Use the GitLab index and issue-note paths from the shared skill.

```markdown
# GitLab Security Issue <security-id>

- GitLab issue/work item:
- Drupal.org note: ../drupalorg/<node-id>.md
- Security remote:
- Local branch:
- Merge request:
- Status:
- Priority / advisory state:
- Risk area:
- Latest evidence:
- Verification:
- Next action:
```

## Branching

Do Webform code work inside `web/modules/sandbox/webform`. Before switching security issues, return to the public base:

```bash
git -C web/modules/sandbox/webform fetch origin <target-version>
git -C web/modules/sandbox/webform switch <target-version>
git -C web/modules/sandbox/webform pull --ff-only origin <target-version>
```

Use one branch and one private remote per issue:

```text
codex/<security-id>-<short-slug>
security-<security-id> -> git@git.drupal.org:security/<security-id>-webform-security.git
```

Start from the security fork base branch when it exists, otherwise
`origin/<target-version>`. Never push security work to public `origin`. When
creating MRs with `git push -o merge_request.*`, keep each push option value on
one line; use a short description and edit longer Markdown later.

## New Issue Reproduction

When no Codex branch, MR, or other code work is visible, create a reproduction branch before fixing:

```text
codex/<security-id>-<short-slug>-test
```

First add a focused failing Functional, Kernel, or Browser test. Prefer Functional tests for access-control and route-level issues. Run it and confirm it fails for the expected reason before drafting a GitLab comment for the human to submit.

Comment drafts must start with `From [AI name]`, explain the test at a high level, reference the remote security fork branch or MR, summarize verification without local paths or local-only commands, recommend a likely fix direction without public/final security claims, and avoid exploit prose, secrets, private data, or unnecessary detail. Do not submit the comment.

## Security Advisory Drafts

Use the shared advisory drafting rules. When asked to create a Drupal.org SA from GitLab work, identify the related Drupal.org security issue from visible links, notes, MR metadata, or the human-provided node id, then use `webform-security-drupalorg` for the create advisory form workflow. By default, open the form and return copy-paste-ready field text; only place text in the form when explicitly asked in the current conversation. Stop before `Save`, `Preview`, publish, submit, or other final action.
