---
name: schemadotorg-issue-maintenance
description: Use when working on public Drupal.org Schema.org Blueprints module issues, including queue scouting, local issue tracking, issue-fork review, scoped contribution work, or assessing ecosystem impact.
---

# Schema.org Blueprints Issue Maintenance

Use `drupalorg-issue-maintenance` for the shared issue-maintenance workflow.
This profile supplies Schema.org Blueprints defaults and keeps ecosystem work
visible in the Schema.org Blueprints queue.

## Schema.org Blueprints project profile

| Input | Default |
|---|---|
| Drupal.org machine name | `schemadotorg` |
| Workspace | `/Users/rockowij/Sites/drupal_playground` |
| Module path | `web/modules/contrib/schemadotorg` |
| Target branch | `1.0.x`, unless the human specifies another version |
| Tracker path | `.agents/schemadotorg-issue-maintenance/` |

When a target version is named, use its branch for local checkout, patch
testing, merge-request review, comment drafts, and backport work. Report any
mismatch between that branch and the issue or merge-request target before
editing or posting a draft.

## Route the task

Load the corresponding parent reference before proceeding:

| Task | Read |
|---|---|
| Scout or traverse the Schema.org Blueprints queue | `drupalorg-issue-maintenance/references/queue-traversal.md` |
| Review, reproduce, test, fix, or draft a comment | `drupalorg-issue-maintenance/references/issue-workflow.md` |
| Create or update Schema.org Blueprints issue notes | `drupalorg-issue-maintenance/references/local-tracker.md` |

Before Drupal.org work, confirm the CLI and local checkout state:

```bash
drupalorg --version
drupalorg skill:get drupalorg-cli
git status --short
if [ -d web/modules/contrib/schemadotorg/.git ]; then
  git -C web/modules/contrib/schemadotorg status --short
  git -C web/modules/contrib/schemadotorg branch --show-current
  git -C web/modules/contrib/schemadotorg remote -v
else
  printf '%s\n' 'Schema.org Blueprints is not installed at web/modules/contrib/schemadotorg.'
fi
```

Run these commands from `/Users/rockowij/Sites/drupal_playground`. When the
module is absent, report that local patch work cannot proceed until the recipe
installs it; continue with read-only queue research if requested. When the
module has uncommitted changes, determine whether they belong to the selected
public issue before continuing.

## Ecosystem context

Use the [Schema.org Blueprints ecosystem page](https://www.drupal.org/project/schemadotorg/ecosystem)
as contextual evidence for issues in the `schemadotorg` queue. Keep that queue
as the sole issue queue and `.agents/schemadotorg-issue-maintenance/` as the
sole local tracker; do not create or maintain ecosystem-project trackers.

For an issue that affects ecosystem projects, such as removing starter-kit
support, add this optional section to its selected issue note after the parent
tracker template's evidence section:

```markdown
## Ecosystem impact
- Affected projects: <project names and links>
- Schema.org queue follow-up: <public issue URL, if applicable>
- Impact: <code, configuration, documentation, test, or release effect>
```

Link to public evidence instead of copying ecosystem details. Do not create
separate ecosystem issues, change statuses, or post comments without the
parent skill's explicit approval gates.

## Public-only boundary

Do not place security-sensitive, exploit, or confidential information in the
local tracker. Pause and seek maintainer direction for non-public issue work.
For authorized local code work, use relevant process skills such as
`systematic-debugging`, `test-driven-development`, and
`verification-before-completion`.
