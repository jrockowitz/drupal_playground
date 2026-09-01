---
name: schemadotorg-module-audit
description: Audit one Schema.org Blueprints base module or submodule, connect the work to an evidence-backed finding in docs/schemadotorg-audit.md, and prepare one coherent Drupal.org issue and issue-fork merge request with mandatory maintainer approval gates. Use for module-by-module Drupal best-practice review, implementing a selected audit finding, or carrying an approved finding through local tests, issue drafting, and MR preparation.
---

# Schema.org module audit

Audit one module and advance at most one coherent finding per run. Produce no
issue or merge request when the evidence does not support a change.

## Load the project workflows

1. Read `docs/schemadotorg-audit.md`, especially the selected `AUD-###`
   finding, best-practice checklist, module matrix, and issue grouping.
2. Read [references/audit-checklist.md](references/audit-checklist.md).
3. Read
   [references/finding-and-publication.md](references/finding-and-publication.md)
   before drafting an issue or making a code change.
4. Use `schemadotorg-issue-maintenance` and
   `drupalorg-issue-maintenance` for tracker and public-issue policy.
5. Before any `drupalorg` command, read the current CLI guidance with
   `drupalorg skill:get drupalorg-cli --full` and use `--format=llm` for reads.
   If served guidance and the installed command disagree, inspect the relevant
   `--help`, report the mismatch, and do not guess a replacement command.

Do not copy the shared issue-maintenance procedures into this skill. Treat
their current instructions as authoritative when they are stricter.

## Establish scope

Run the read-only inventory script from the project root:

```bash
.agents/skills/schemadotorg-module-audit/scripts/inspect-module.sh <base|submodule>
```

Confirm all of the following before editing:

- project `schemadotorg`, checkout `web/modules/sandbox/schemadotorg`;
- target branch `1.0.x`, unless the maintainer specifies another branch;
- one base module or `schemadotorg_<name>` submodule;
- clean module checkout and understood parent-workspace changes;
- an existing audit ID, or a proposed draft finding to add to the audit;
- one behavioral or documentation outcome, not a general cleanup bundle.

If the module checkout is dirty, restrict work to read-only analysis until the
maintainer resolves or explicitly scopes the existing changes.

## Audit one module

Inspect source, metadata, services, routes, permissions, configuration,
libraries, README, API surfaces, and relevant tests. Trace dependencies into
the base module only when necessary to assess the selected submodule.

Record evidence before proposing a fix. Assign priority and confidence using
the audit report. Distinguish a confirmed defect from a hardening opportunity,
tooling noise, environmental failure, or product decision.

Search the public queue for duplicates before selecting work. If an existing
issue covers the finding, stop creating a new issue and continue only through
that issue after maintainer selection.

For a new finding that is absent from `docs/schemadotorg-audit.md`, draft a
complete finding using the reference template. Ask the maintainer to review
the finding and approve adding it to the report before public issue work. Give
it the next stable `AUD-###` identifier only after that approval.

Stop and use the private Drupal security process when evidence could describe
an exploitable access bypass, sensitive-data exposure, code execution, SQL
injection, cross-site scripting, CSRF, or another security vulnerability. Do
not put sensitive reproduction details in the public tracker or issue queue.

## Select one finding

Present the module, audit ID, evidence, priority/confidence, proposed issue
boundary, expected files, and verification plan. Ask the maintainer to approve
that exact finding before editing code.

Do not force one issue per module. A run may end with no finding. Split
unrelated findings; combine only changes that share one cause and one
verifiable outcome.

## Implement locally

After approval:

1. Inspect applicable tests and extension contracts.
2. Add a failing regression test when practical.
3. Make the smallest compatible change.
4. Update documentation only when required by the behavioral contract.
5. Run the narrow PHPUnit and file-type-specific review targets first.
6. Broaden verification in proportion to access, cache, API, install/update,
   or cross-module risk.
7. Recheck both parent and module working trees and report every changed file.

Use DDEV commands from the project instructions. Do not use the all-purpose
review wrapper's Stylelint output against non-CSS files as evidence.

## Pass the approval gates

Never infer approval for a later gate. Pause independently before:

1. adding or changing a public issue;
2. editing local code;
3. creating an issue fork or remote branch;
4. committing;
5. pushing;
6. opening or updating a merge request;
7. posting a public comment or changing issue/MR status.

For public forms, use the in-app browser, populate the reviewed draft, and let
the maintainer click Save or Submit. Start every AI-prepared issue, comment,
and MR description with `AI-assisted by Codex`. End every AI-assisted commit
message with `AI-assisted by Codex`.

Before commit approval, show the complete diff, commands and results,
deprecations/environment limitations, and proposed commit message. Before push
or MR approval, refresh public issue state and confirm the issue-fork target
and branch using the current Drupal.org workflow guidance.

## Finish the run

Update the local issue note and dashboard only for a selected public issue.
Include the private local audit reference (`AUD-###`) in the local note; do not
publish a meaningless local filesystem link on Drupal.org.

Report the audited module, selected audit ID, public issue/MR URLs when they
exist, changed files, test results, remaining uncertainty, current approval
gate, and next action. Confirm whether the module checkout is clean.
