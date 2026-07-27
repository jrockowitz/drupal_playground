# Queue Traversal

Load `drupalorg-cli` guidance first, then scan the public queue using the
project machine name. Start broad enough to understand the queue, then narrow
with a small number of relevant searches.

```bash
drupalorg project:issues <project> review --limit=25 --format=llm
drupalorg project:issues <project> all --limit=25 --format=llm
drupalorg project:issues <project> rtbc --limit=25 --format=llm
drupalorg issue:search <project> "<topic>" --status=open --limit=10 --format=llm
```

Use `--no-cache` for decisions that depend on recent comments, status, or CI.
Do not treat a requested count (for example, “find five candidates”) as
permission to begin work on those candidates.

## Shortlist candidates

Recommend three to five issues, not a full queue dump. For each, report:

- Issue URL and current status; priority/category when available.
- A primary work lane: queue intelligence, review, reproduction, regression
  test, scoped fix, or comment draft.
- Why it matters, whether it is suitable for agent work, and the first useful
  verification command.

Prefer clear reproductions, narrow scope, local testability, supported-version
compatibility, data integrity, access behavior, broken submissions, release
blockers, and existing focused contributions with tests. Defer issues that need
private sites, external services, broad architecture, product-policy decisions,
public API changes, or large stale/testless patches.

Use concise recommendation labels when useful: **Best fix target**, **Best test
target**, **Best review target**, **Good reproduction target**, **Needs
maintainer decision**, **Needs human reproduction**, or **Defer**.

## Inspect a selected issue

After the human selects issue IDs or URLs, load the complete public context:

```bash
drupalorg issue:show <issue-id> --with-comments --format=llm
drupalorg issue:get-fork <issue-id> --format=llm
```

If a merge request is relevant, identify its IID and inspect it through
`drupalorg-cli` using the quoted `project/<project>!<merge-request-iid>` form:

```bash
drupalorg mr:files 'project/<project>!<merge-request-iid>' --format=llm
drupalorg mr:diff 'project/<project>!<merge-request-iid>' --format=llm
drupalorg mr:status 'project/<project>!<merge-request-iid>' --format=llm
```

Read issue details and comments rather than relying on titles. Record selected,
public, non-security issues in the local tracker before beginning local work.

