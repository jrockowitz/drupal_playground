---
name: webform-issue-maintenance
description: Use when working on public Drupal.org Webform module issues, including queue scouting, local issue tracking, issue-fork review, or scoped Webform contribution work.
---

# Webform Issue Maintenance

Use `drupalorg-issue-maintenance` for the shared public-issue workflow and its
matching reference. This profile supplies the Webform project defaults and
routes private work to the security skill.

## Webform project profile

| Input | Default |
|---|---|
| Drupal.org machine name | `webform` |
| Module path | `web/modules/sandbox/webform` |
| Target branch | `6.3.x`, unless the human specifies another version |
| Tracker path | `.agents/webform-issue-maintenance/` |

## Public-only boundary

Use `webform-security` instead for security-sensitive issues, private details,
exploit prose, or confidential Drupal.org/GitLab data.
