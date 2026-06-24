---
name: webform-security-drupalorg
description: Use when working on Webform security issues or advisory forms on security.drupal.org.
---

# Webform Security Drupal.org

Before task actions, read and follow `../webform-security/SKILL.md` for shared Webform security guardrails.

## Browser

Use the Codex in-app Browser for Drupal.org security queue work. If the Browser is already on the queue, inspect the current page without reloading unless needed. Otherwise open:

```text
https://security.drupal.org/project/issues/webform
```

Pause for human login before inspecting private data. Prefer dedicated Browser controls. If only Playwright-backed controls exist and the human asked not to use Playwright, report that limitation and pause.

## Private Notes

Use the Drupal.org index and issue-note paths from the shared skill.

```markdown
# Drupal.org Security Issue <node-id>

- Drupal.org issue: https://security.drupal.org/node/<node-id>
- GitLab note: ../gitlab/<security-id>.md
- Status:
- Priority / advisory state:
- Component:
- Risk area:
- Latest visible evidence:
- Verification:
- Next action:
```

## Queue Triage

Use visible queue filters/table data. Useful statuses include Needs triage/work/review/maintainer response/team response/reporter response/public followup, Reviewed & tested by the community, Ready for SA to be Published, No maintainer response, Postponed, and Closed states. Useful priority/advisory filters include Highly Critical, Critical, Moderately Critical, Less Critical, Not Critical, and No Draft SA.

When reporting findings, recommend a small actionable set. Include issue URL, status, priority, component, work lane, why it matters, agent suitability, and first verification command when applicable.

## Security Advisory Forms

Use the shared advisory drafting rules. When asked to create an SA for an issue with no draft, open the issue link:

```text
Create Advisory for this issue
```

It should lead to:

```text
https://security.drupal.org/node/add/advisory?field_sa_for=<node-id>
```

Verify `SA For` matches the issue. By default, inspect the form and return copy-paste-ready text grouped by visible field labels, including title, project/module, affected versions, risk scoring, description, mitigation, credit, and other required fields. Only place text into the form when explicitly asked in the current conversation. Stop before any final action.
