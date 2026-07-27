# Local Markdown Tracker

Use a tracker inside the project workspace so queue research is understandable
to both people and agents and can be committed when maintainers choose to keep
it. Create it only for selected public, non-security issues.

```text
.agents/<project>-issue-maintenance/
  README.md
  index.md
  issues/
    <drupalorg-node-id>.md
```

## Update order

1. Create or update `issues/<drupalorg-node-id>.md`.
2. Update `index.md` to reflect the issue’s current state and link to its note.
3. Change `README.md` only when the tracker purpose, layout, or maintenance
   procedure changes.

## Required contents

`README.md` states the project name, tracker purpose, directory layout, and
update order.

`index.md` is the current dashboard. For each selected issue, include its ID,
short title, Drupal.org URL, current status, work lane, tracker-note link, and
next action. Add a dated queue-scout summary only when it helps explain why the
current set was selected.

Each issue note includes:

- Public issue URL, status, selected work lane, target branch, and relevant
  fork or merge request.
- Selection rationale and the public evidence inspected.
- Local reproduction or review evidence, commands run, results, and changed
  files when applicable.
- Current approval gate, suggested draft comment if requested, uncertainty, and
  next action.

Use headings and short lists; link to public sources instead of copying their
full contents. Never place private data, secrets, tokens, exploit prose,
confidential GitLab material, or security-sensitive issue details in this
tracker.

## Minimal templates

```markdown
# <Project> issue maintenance

Public issue research and selected-work notes. Update the issue note first,
then the index.
```

```markdown
# Selected issues

| Issue | Status | Lane | Next action |
|---|---|---|---|
| [#<id>](<public-url>) | <status> | <lane> | <next-action> |
```

```markdown
# #<id>: <title>

## Context
- URL: <public-url>
- Status: <status>
- Lane: <lane>
- Target branch: <branch>

## Evidence
- Inspected: <issue, comments, MR/fork>
- Commands and results: <concise evidence>

## Next action
<action and required approval gate>
```

