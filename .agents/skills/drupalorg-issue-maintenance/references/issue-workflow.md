# Per-Issue Workflow

Use this workflow only after the human selects a public, non-security issue.
Read `drupalorg-cli` for current command syntax and `drupal-gitlab` for GitLab
and issue-fork behavior.

1. Load the issue, comments, applicable fork, merge request diff, changed files,
   and CI state.
2. Classify the work: triage, reproduce, regression test, scoped fix, review,
   summary update, or maintainer comment draft.
3. Inspect local code and existing tests before editing. Search from the module
   checkout, for example:

   ```bash
   rg -n "RelevantClass|relevant_method|config_name" <module-path>
   ```

4. Reproduce the reported behavior, or state the exact evidence that blocks
   reproduction.
5. For authorized code work, write a failing regression test when practical,
   then make the smallest change that satisfies the behavioral contract.
6. Run targeted tests and the project’s relevant lint or code-review command.
   Broaden verification only when the changed surface warrants it.
7. Update the issue note and index with evidence, uncertainty, and the next
   action. Stop for maintainer review before any commit or public write.

## Review findings

For a patch or merge-request review, report the issue and MR inspected, changed
files, tests present or missing, CI status, local behavior, concrete findings,
and remaining uncertainty. Tie findings to the issue’s stated problem; do not
suggest unrelated cleanup.

## Comment drafts

Draft comments only when requested. Begin every draft as follows and do not post
it:

```markdown
From [AI-agent]

I reviewed this locally against <target-branch>.

What I checked:
- ...

Commands run:
- `...`

Result:
- ...

Remaining question:
- ...
```

Describe a contribution as an RTBC candidate when evidence supports it; leave
the final status decision to the maintainer.

