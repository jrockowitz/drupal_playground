# Finding and publication workflow

## Local audit finding

Require this information in `docs/schemadotorg-audit.md` before creating a new
public issue:

```markdown
### AUD-### — <concise outcome-oriented title>

- **Priority / confidence:** <P0-P3> / <High|Medium|Low>.
- **Evidence:** <paths, lines, behavior, commands, and authoritative sources>.
- **Impact:** <user, maintainer, compatibility, access, performance, or DX>.
- **Recommendation:** <smallest outcome; avoid prescribing uncertain design>.
- **Verification:** <regression test and targeted checks>.
- **Suggested issue:** <one coherent boundary or existing issue reference>.
```

For a newly discovered finding, use a temporary descriptive label until the
maintainer approves editing the report and assigning the next `AUD-###` ID.

## Selection summary

Before local edits, present:

```markdown
Module: <machine name>
Audit finding: AUD-###
Priority/confidence: <...>
Confirmed evidence: <...>
Existing Drupal.org coverage: <none or links>
Proposed issue boundary: <...>
Expected files: <...>
Verification: <...>
Approval requested: implement this finding locally
```

## Public issue draft

Do not include private paths or a local audit link. Translate the evidence into
a self-contained public report:

```markdown
AI-assisted by Codex

## Problem/Motivation
<observable problem and affected users>

## Steps to reproduce
1. <minimal reproducible steps>

## Proposed resolution
<behavioral outcome, compatibility constraints, and non-goals>

## Remaining tasks
- [ ] Add or update regression coverage.
- [ ] Implement the scoped fix.
- [ ] Run targeted code review and tests.

## User interface changes
<None or exact change>

## API changes
<None or exact compatibility impact>
```

Search for duplicates and review the complete draft with the maintainer. Open
the issue form in the in-app browser, populate it, and let the maintainer click
Save. After creation, put `AUD-###` and the public URL in the local tracker note.

## Commit and merge request

Follow the current `drupalorg-work-on-issue` guidance for issue forks and MR
mechanics. Use the issue number in the branch/MR convention it specifies.

Propose a focused commit message whose final paragraph is:

```text
AI-assisted by Codex
```

Start the MR description with `AI-assisted by Codex`, link the issue, summarize
the fix and tests, and disclose remaining limitations. Populate the form in the
in-app browser and let the maintainer submit it. Never change issue status or
post a follow-up comment without another explicit review gate.
