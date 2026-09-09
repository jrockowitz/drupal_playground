---
name: schemadotorg-issue-maintenance
description: Use when working on public Drupal.org Schema.org Blueprints module issues, including queue scouting, local issue tracking, issue-fork review, scoped contribution work, or assessing ecosystem impact.
---

# Schema.org Blueprints Issue Maintenance

Use `drupalorg-issue-maintenance` for the shared public-issue workflow and its
matching reference. This profile supplies Schema.org Blueprints defaults and
keeps ecosystem work visible in the Schema.org Blueprints queue.

## Schema.org Blueprints project profile

| Input | Default |
|---|---|
| Drupal.org machine name | `schemadotorg` |
| Module path | `web/modules/sandbox/schemadotorg` |
| Target branch | `1.0.x`, unless the human specifies another version |
| Tracker path | `.agents/schemadotorg-issue-maintenance/` |

## Experimental companion project

Manage the [Schema.org Blueprints Experimental project](https://git.drupalcode.org/project/schemadotorg_experimental)
alongside the main `schemadotorg` project whenever an issue produces a code
improvement that belongs in the experimental module. Inspect the experimental
project's relevant branch and issue-fork or merge-request state, and update its
code and tests as part of the same scoped work when applicable. Keep the main
Schema.org Blueprints queue as the canonical issue queue and
`.agents/schemadotorg-issue-maintenance/` as the sole local tracker; record the
experimental project, branch, and related merge request in the selected issue
note when they are involved.

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

Link to public evidence instead of copying ecosystem details.
