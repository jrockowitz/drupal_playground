# AI-Assisted Maintenance Plan for the Webform Module

This document describes how a human maintainer and an AI agent can work together on the Drupal Webform module issue queue. It is intended to be operational: read it, open the tools, scout the queue, pick an issue, and produce useful evidence or a small contribution.

The central idea is simple: agents should reduce queue friction, not replace maintainer judgment.

## 1. Purpose

Use AI agents to help maintain the Webform module by:

- finding issues worth attention
- summarizing queue state
- reproducing bugs
- writing regression tests
- reviewing patches and merge requests
- making narrow, evidence-backed fixes
- drafting maintainer-ready issue comments

Do not use agents as autonomous maintainers. They should not close issues, change statuses, post comments, commit, push, or make release/security decisions unless a human explicitly asks them to.

## 2. Operating Principles

- Agents support maintainers; they do not act as maintainers.
- Evidence matters more than confidence.
- Prefer regression tests over speculative fixes.
- Prefer small, reviewable changes.
- Prefer existing Webform and Drupal patterns.
- Work from issue details and comments, not issue titles.
- Stop before irreversible external actions unless the human asks.
- Always record what was checked, what passed, what failed, and what remains uncertain.

## 3. Local Environment

Primary working repository:

`/Users/rockowij/Sites/drupal_webform`

Webform sandbox checkout:

`/Users/rockowij/Sites/drupal_webform/web/modules/sandbox/webform`

Observed Webform branch:

`6.3.x`

Confirm the checkout before working:

```bash
cd /Users/rockowij/Sites/drupal_webform
composer show drupal/webform --all
git -C web/modules/sandbox/webform status --short
git -C web/modules/sandbox/webform branch --show-current
```

Local project assumptions:

- PHP: 8.3
- DDEV project: `drupal-webform`
- DDEV URL: `https://drupal-webform.ddev.site`
- Drupal docroot: `web/`

Common verification commands:

```bash
ddev phpunit web/modules/sandbox/webform/tests/src/Functional/WebformLibrariesTest.php
ddev phpunit web/modules/sandbox/webform/tests/src/Kernel
ddev code-review web/modules/sandbox/webform/src/Plugin/WebformElementBase.php
ddev code-review web/modules/sandbox/webform/tests/src/Functional/WebformLibrariesTest.php
ddev code-fix web/modules/sandbox/webform/src/Plugin/WebformElementBase.php
```

Use targeted tests first. Broaden only when the touched code has broad impact.

## 4. Tooling Stack

### Drupal.org CLI

Use `mglaman/drupalorg-cli` as the first tool for Drupal.org and GitLab issue data.

Project:

https://github.com/mglaman/drupalorg-cli

Installed locally:

- Current executable: `/opt/homebrew/bin/drupalorg`
- Current observed version: `0.10.2`
- Older shadowed executable: `/usr/local/bin/drupalorg` at `0.8.1`

Verify:

```bash
command -v drupalorg
drupalorg --version
```

Important behavior:

- Prefer `--format=llm` for agent-readable structured output.
- Use `--with-comments` when issue history matters.
- Use `--no-cache` when a recent Drupal.org change is missing.
- Use `drupalorg skill:get drupalorg-cli` to load version-matched command guidance.

Core queue commands:

```bash
drupalorg project:issues webform all --limit=25 --format=llm
drupalorg project:issues webform review --limit=25 --format=llm
drupalorg project:issues webform rtbc --limit=25 --format=llm
drupalorg issue:search webform "access" --format=llm
```

Use `issue:search` for title keyword searches. The installed `0.10.2` command
accepts `--status=all|open|closed|rtbc|review` and `--limit=<count>`:

```bash
drupalorg issue:search webform "access" --status=open --limit=10 --format=llm
```

Core issue commands:

```bash
drupalorg issue:show 3591835 --format=llm
drupalorg issue:show 3591835 --with-comments --format=llm
drupalorg issue:get-fork 3591835 --format=llm
drupalorg issue:branch 3591835
```

Use `issue:apply <issue-id>` only for classic patch-based issues that have an
attached patch. For GitLab MR issues, use `issue:get-fork`,
`issue:setup-remote`, and `issue:checkout` instead.

Core merge request commands:

```bash
drupalorg mr:list project/webform --format=llm
drupalorg mr:files 'project/webform!870' --format=llm
drupalorg mr:diff 'project/webform!870' --format=llm
drupalorg mr:status 'project/webform!870' --format=llm
drupalorg mr:logs 'project/webform!870'
```

For Webform GitLab merge requests, prefer the quoted `project/webform!<iid>`
form after discovering the IID from `mr:list`, an MR URL, or issue comments.
This avoids ambiguity when an issue search returns project-level MR data. Quote
the argument in zsh because `!` triggers shell history expansion.

Skill commands:

```bash
drupalorg skill:get drupalorg-cli
drupalorg skill:get drupalorg-cli --full
drupalorg skill:get drupalorg-work-on-issue
drupalorg skill:get drupalorg-issue-search
drupalorg skill:get drupalorg-issue-summary-update
```

The bundled discovery skill was installed to:

`/Users/rockowij/Sites/drupal_webform/.agents/skills/drupalorg-cli/SKILL.md`

That path is ignored by the `drupal_webform` repository. Restart the agent app if the skill is not visible in the active skill list.

### Drupal.org Web UI and API

Use the Web UI for human inspection:

https://www.drupal.org/project/issues/webform

Use Drupal.org API only when `drupalorg-cli` does not expose the needed slice.

Useful facts:

- Webform project node: `7404`
- Issue API base: `https://www.drupal.org/api-d7/node.json`
- Issue type: `project_issue`

Example fallback API query:

```bash
curl -sS \
  -H 'Accept: application/json' \
  -H 'User-Agent: Webform issue maintenance' \
  'https://www.drupal.org/api-d7/node.json?type=project_issue&field_project=7404&field_issue_status=8&limit=10&sort=changed&direction=DESC'
```

### Git and Issue Forks

Use the Webform sandbox checkout for code work:

```bash
cd /Users/rockowij/Sites/drupal_webform/web/modules/sandbox/webform
git status --short
git branch --show-current
```

Use `drupalorg-cli` for issue fork operations where possible:

```bash
drupalorg issue:get-fork 3591835 --format=llm
drupalorg issue:setup-remote 3591835
drupalorg issue:checkout 3591835 3591835-webformelementbasecheckaccessrules-does-not
```

Pass the branch name explicitly in agent/non-interactive work. Use
`issue:get-fork` first and copy the relevant branch from the `<branches>` list.

Do not commit or push unless the human explicitly asks. If commits are requested, AI-created commits should end with:

`AI-assisted by Codex`

### DDEV, PHPUnit, and Code Review

Run commands from `/Users/rockowij/Sites/drupal_webform`.

Use:

```bash
ddev phpunit <file-or-directory>
ddev code-review <file-or-directory>
ddev code-fix <file-or-directory>
```

Good verification sequence for a narrow bugfix:

```bash
ddev phpunit web/modules/sandbox/webform/tests/src/Functional/SpecificTest.php
ddev code-review web/modules/sandbox/webform/src/SpecificFile.php
ddev code-review web/modules/sandbox/webform/tests/src/Functional/SpecificTest.php
```

If an issue changes config, test partial import with:

```bash
ddev drush config:import -y --partial --source=<directory>
```

### Agent Skills

Useful local skills already present in `/Users/rockowij/Sites/drupal_webform/.agents/skills`:

- `drupalorg-cli`: Drupal.org CLI discovery skill installed from `drupalorg-cli`
- `drupal-at-your-fingertips`: Drupal API patterns
- `drupal-ddev`: DDEV local development patterns
- `drupal-contrib-mgmt`: Composer/contrib project handling
- `test-driven-development`: write tests before implementation
- `systematic-debugging`: reproduce and isolate bugs before fixing
- `verification-before-completion`: verify before claiming completion
- `requesting-code-review`: use when work is ready for review

Proposed new skill:

`webform-issue-maintenance`

This planned skill should combine Webform-specific issue scoring with `drupalorg-cli` data collection.

## 5. Issue Queue Scouting Workflow

Run this workflow when the goal is to find issues worth addressing.

1. Refresh tool context:

```bash
drupalorg --version
drupalorg skill:get drupalorg-cli
```

2. Pull current candidate lists:

```bash
drupalorg project:issues webform review --limit=25 --format=llm
drupalorg project:issues webform all --limit=25 --format=llm
drupalorg project:issues webform rtbc --limit=25 --format=llm
```

3. Search by common high-value themes:

```bash
drupalorg issue:search webform "access" --format=llm
drupalorg issue:search webform "cache" --format=llm
drupalorg issue:search webform "Drupal 11" --format=llm
drupalorg issue:search webform "PHPStan" --format=llm
drupalorg issue:search webform "test" --format=llm
```

Use `--status=open` when you only want actionable open issues. Use
`--status=review` when looking for review targets and `--status=rtbc` when
checking whether anything is ready for maintainer attention.

4. Build a short candidate list grouped by likely action:

- fix target
- test target
- review target
- reproduction target
- needs maintainer decision
- stale cleanup candidate
- probably not worth agent time yet

5. For each serious candidate, inspect details:

```bash
drupalorg issue:show <issue-id> --with-comments --format=llm
drupalorg mr:list project/webform --format=llm
```

6. Recommend 3-5 issues, not 20. For each, include:

- issue link
- status, priority, category
- likely work lane
- why it matters
- why it is suitable or unsuitable for agent work
- first verification command or reproduction step

## 6. Issue Scoring Model

Use this rubric to compare issues. Scores are guidance, not math.

### Impact

High impact:

- data loss
- access/security-adjacent behavior
- Drupal 11 compatibility
- release blockers
- broken submissions
- test suite failures

Lower impact:

- niche UI polish
- unclear feature requests
- support questions

### Reproducibility

Good agent target:

- clear steps to reproduce
- small fixture setup
- deterministic behavior
- existing test module can model it

Poor agent target:

- depends on a private site
- requires many contrib modules without a minimal case
- "sometimes" or race-only behavior without evidence

### Local Testability

Good agent target:

- Kernel, Functional, Browser, or FunctionalJavascript test can cover it
- expected assertion is behavioral
- can fail before fix and pass after fix

Poor agent target:

- requires external service
- requires manual visual judgment only
- requires browser/network timing that is hard to stabilize

### Scope and Risk

Good agent target:

- one class, plugin, route, config file, or test area
- existing pattern nearby
- narrow behavior contract

Poor agent target:

- broad architecture change
- feature design debate
- many subsystems
- unclear backward compatibility

### Existing Patch or MR Quality

Good review target:

- MR exists
- patch applies
- changes are small
- tests exist or can be added

Needs caution:

- large patch without tests
- stale patch
- issue summary and MR disagree
- many unresolved comments

### Maintainer Judgment Needed

Agents can prepare evidence, but should pause when an issue needs:

- choosing a product behavior
- deciding permission policy
- accepting backward compatibility breakage
- changing public API
- making security claims

### Suggested Labels

Use these labels in scouting output:

- Best fix target
- Best test target
- Best review target
- Good reproduction target
- Needs maintainer decision
- Needs human reproduction
- Probably not worth agent time yet

## 7. Work Lanes

### Queue Intelligence

Goal: create a digest that helps a maintainer choose where to spend time.

Output:

- recent Major/Critical issues
- stale Needs review issues
- issues with MRs but no tests
- Needs work issues with clear next actions
- likely duplicates or obsolete issues
- suggested top 3 agent targets

### Patch and MR Review

Goal: validate existing work.

Steps:

```bash
drupalorg issue:show <issue-id> --with-comments --format=llm
drupalorg mr:list project/webform --format=llm
drupalorg mr:files 'project/webform!<merge-request-iid>' --format=llm
drupalorg mr:diff 'project/webform!<merge-request-iid>' --format=llm
drupalorg mr:status 'project/webform!<merge-request-iid>' --format=llm
```

Then check locally:

- Does it apply?
- Does it match the issue summary?
- Are tests included?
- Do tests fail before and pass after?
- Are there missing edge cases?
- Are comments or docs needed?

Output:

- review summary
- exact concerns with file references
- suggested issue comment
- whether it is an RTBC candidate, not an RTBC claim

### Regression Test Writing

Goal: turn a bug report into durable coverage.

Steps:

- identify the smallest behavior contract
- find an existing test class with similar setup
- write one test method when a single bootstrap can cover the scenario
- watch it fail
- only then implement the fix

Follow local Webform conventions:

- Browser, Functional, and Kernel tests should prefer one test method when scenarios share setup.
- Assertion blocks should use comments beginning with `// Check that ...`.
- Avoid exact labels/markup unless the issue is specifically about labels/markup.
- Avoid nullsafe operator in tests.

### Small Scoped Fixes

Goal: make minimal changes that satisfy the issue and tests.

Good examples:

- respecting `AccessResultInterface`
- normalizing array/object handling
- fixing config defaults
- updating a route access check
- resolving a deprecation
- fixing PHPStan output

Avoid unrelated refactoring.

### Issue Comment Drafting

Goal: make it easy for a human to post a useful comment.

Draft should include:

- what was tested
- local environment
- reproduction result
- patch/MR result
- commands run
- failures or uncertainty
- recommended next status, if any

Do not post the comment unless explicitly asked.

## 8. Per-Issue Workflow

Use this checklist when the human selects an issue.

1. Load the issue:

```bash
drupalorg issue:show <issue-id> --with-comments --format=llm
```

2. Load MR/patch context:

```bash
drupalorg mr:list project/webform --format=llm
drupalorg issue:get-fork <issue-id> --format=llm
```

In the `mr:list` output, match the issue number in the MR title or source
branch, then use that MR IID with `project/webform!<merge-request-iid>`.

3. Classify the work:

- triage
- reproduce
- test
- fix
- review
- summary/comment draft

4. Inspect local code with `rg`:

```bash
rg -n "RelevantClass|relevant_method|config_name" web/modules/sandbox/webform
```

5. Reproduce or state why reproduction is blocked.

6. Write a failing test when practical.

7. Make the smallest code/config change.

8. Run targeted verification.

9. Summarize evidence.

10. Stop before external action unless the human asks.

## 9. Working CLI Examples for Current Issues

These examples were checked against `Drupal.org CLI 0.10.2` on June 1, 2026.
Issue state changes over time, so re-run the discovery commands before acting.

### Example A: Review a Focused Access Fix

Issue:

- https://www.drupal.org/project/webform/issues/3591835
- `WebformElementBase::checkAccessRules() does not handle AccessResult objects`
- Current observed status: Needs Review
- Current observed MR: `project/webform!870`
- Current observed pipeline: passed
- Changed file observed from the MR: `src/Plugin/WebformElementBase.php`

Use this when looking for a narrow test/fix or MR review target:

```bash
cd /Users/rockowij/Sites/drupal_webform
drupalorg issue:show 3591835 --with-comments --format=llm --no-cache
drupalorg issue:get-fork 3591835 --format=llm --no-cache
drupalorg mr:files 'project/webform!870' --format=llm --no-cache
drupalorg mr:diff 'project/webform!870' --format=llm --no-cache
drupalorg mr:status 'project/webform!870' --format=llm --no-cache
```

Notes:

- Use `issue:show` first to understand the bug and the public security-team
  note. Do not make security claims beyond the issue text and local evidence.
- Use `issue:get-fork` to discover the issue fork remote and branch. On the
  current scan, the relevant branch was
  `3591835-webformelementbasecheckaccessrules-does-not`.
- Use the MR IID `870` with `project/webform!870` for file, diff, and pipeline
  commands. The IID comes from the MR URL or `mr:list project/webform`.
- After inspecting the diff, search locally before changing anything:

```bash
rg -n "checkAccessRules|AccessResultInterface|#access" web/modules/sandbox/webform/src web/modules/sandbox/webform/tests
```

### Example B: Investigate a Drush Composer Library Bug

Issue:

- https://www.drupal.org/project/webform/issues/3470339
- `Error: Attempt to assign property on array in WebformLibrariesCommands->setComposerLibraries()`
- Current observed status: Needs Review

Use this when looking for a reproduction or regression-test target:

```bash
cd /Users/rockowij/Sites/drupal_webform
drupalorg issue:show 3470339 --with-comments --format=llm --no-cache
drupalorg issue:get-fork 3470339 --format=llm --no-cache
rg -n "setComposerLibraries|webform:composer:update|repositories" web/modules/sandbox/webform
```

Notes:

- Start read-only with `issue:show` and `issue:get-fork`; the current observed
  fork includes branch `3470339-error-attempt-to`.
- Confirm `git -C web/modules/sandbox/webform status --short` before checking
  out an issue branch or applying a patch so user work is not mixed with issue
  testing.
- If you need to review the issue fork branch locally, use:

```bash
cd /Users/rockowij/Sites/drupal_webform/web/modules/sandbox/webform
drupalorg issue:setup-remote 3470339
drupalorg issue:checkout 3470339 3470339-error-attempt-to
```

- The local reproduction target is the Drush command described by the issue:

```bash
ddev drush webform:composer:update
```

- If you write a test, prefer a focused test around the Composer repositories
  array/object behavior rather than asserting exact prompt text.

### Example C: Review an Access Restriction Policy Issue

Issue:

- https://www.drupal.org/project/webform/issues/3463152
- `"Webform submissions" view default display does not have any access restriction`
- Current observed status: Needs Review

Use this when looking for a review target that may require maintainer judgment:

```bash
cd /Users/rockowij/Sites/drupal_webform
drupalorg issue:show 3463152 --with-comments --format=llm --no-cache
drupalorg issue:get-fork 3463152 --format=llm --no-cache
rg -n "webform submissions|webform_submission|access content|display_options" web/modules/sandbox/webform/config web/modules/sandbox/webform/tests
```

Notes:

- This is access-policy-adjacent. Agents can verify the current Views config,
  inspect a patch or MR, and propose a test, but should pause before deciding
  which permission is correct.
- The current observed fork includes branch
  `3463152-webform-submissions-view`.
- If config changes are involved, verify with a partial config import from the
  directory containing changed config:

```bash
ddev drush config:import -y --partial --source=<directory>
```

### Example D: Scout Current Review Targets

Use this when preparing a short maintainer digest:

```bash
cd /Users/rockowij/Sites/drupal_webform
drupalorg project:issues webform review --limit=10 --format=llm --no-cache
drupalorg project:issues webform all --limit=10 --format=llm --no-cache
drupalorg issue:search webform "access" --status=open --limit=10 --format=llm --no-cache
drupalorg mr:list project/webform --format=llm --no-cache
```

Notes:

- Keep the output small. Agents should recommend 3-5 candidates with evidence,
  not paste an entire queue dump.
- For each candidate, include the issue URL, observed status, likely work lane,
  first local verification command, and why maintainer judgment may or may not
  be needed.

## 10. Verification Standards

Before saying work is complete, provide evidence.

Minimum evidence for code changes:

- changed files
- issue behavior before/after
- targeted PHPUnit command and result
- targeted `ddev code-review` command and result
- known gaps

Minimum evidence for review-only work:

- issue and MR inspected
- files changed by MR
- tests present/missing
- CI or pipeline status if available
- concrete review findings

Minimum evidence for queue scouting:

- commands used
- time/date of scan
- top candidates
- why each candidate was selected
- what not to work on yet

Suggested final issue-comment format:

```markdown
I reviewed this locally against Webform 6.3.x.

What I checked:
- ...

Commands run:
- `ddev phpunit ...`
- `ddev code-review ...`

Result:
- ...

Remaining question:
- ...
```

## 11. Guardrails and Permissions

Agents must not:

- close Drupal.org issues
- change Drupal.org issue status
- post Drupal.org comments
- assign/reassign issues
- create labels
- commit
- push
- open merge requests
- make security claims beyond evidence

Exceptions require explicit human instruction in the current conversation.

Agents should pause and ask before:

- changing public APIs
- changing permissions
- changing access policies
- changing update hooks
- touching large generated config
- working around unclear requirements
- accepting behavior that contradicts existing tests

## 12. Proposed `webform-issue-maintenance` Skill

Purpose:

Guide agents through scouting and working on Drupal.org Webform module issues using `drupalorg-cli`, local Webform tests, and project-specific guardrails.

Trigger:

Use when asked to inspect, triage, prioritize, reproduce, fix, test, review, summarize, or comment on Drupal.org Webform module issues.

Required first steps:

```bash
drupalorg --version
drupalorg skill:get drupalorg-cli
```

Skill should require:

- `drupalorg-cli` before raw API calls
- `--format=llm` for read commands
- issue details and comments before code edits
- local checkout inspection before recommendations
- tests before fixes when practical
- verification before completion

Skill output modes:

- Queue scout report
- Issue work plan
- Review findings
- Regression test proposal
- Maintainer comment draft

The first version of the skill should focus on read-only scouting and issue recommendation. Add hands-on fix workflows after the scouting output proves useful.

## 13. Candidate Automation

Useful future helpers:

- weekly Webform queue digest
- Needs review ranking
- Major/Critical issue monitor
- MR without tests report
- stale Needs work report
- "good first agent issue" finder
- release-blocking issue list
- Markdown report generated from `drupalorg-cli`

Prefer simple Markdown reports before dashboards.

## 14. Initial Pilot Plan

Run a small pilot before automating heavily.

1. Run a read-only queue scouting pass.
2. Pick three candidate issues:
   - one test-only or test-first issue
   - one MR review issue
   - one narrow fix issue
3. Work each issue using the per-issue workflow.
4. Draft issue comments but do not post automatically.
5. Review whether the agent output was useful.
6. Update the `webform-issue-maintenance` skill based on what worked.

Suggested first pilot candidates from the initial scan:

- https://www.drupal.org/project/webform/issues/3591835
  - `WebformElementBase::checkAccessRules() does not handle AccessResult objects`
  - Good focused test/fix target.

- https://www.drupal.org/project/webform/issues/3470339
  - `Error: Attempt to assign property on array in WebformLibrariesCommands->setComposerLibraries()`
  - Good test target around Composer repositories as array vs object.

- https://www.drupal.org/project/webform/issues/3463152
  - `"Webform submissions" view default display does not have any access restriction`
  - Good review/test target, but permission policy may need maintainer judgment.

## 15. Historical Notes and Current Findings

This section preserves useful details from the initial conversation. Treat them as a snapshot, not live truth.

### Queue Snapshot

The Webform project page reported:

- 248 open issues
- 16,025 total issues
- 176 open bug reports

The issue queue table showed a nearby count of 247 open-filtered issues, likely due to Drupal.org cache/timing.

Observed API breakdown:

- Active: 80
- Fixed: 5
- Postponed: 16
- Needs review: 46
- Needs work: 100
- RTBC: 0
- Patch to be ported: 0
- Postponed, maintainer needs info: 7

Open issue categories:

- Bug report: 182
- Task: 37
- Feature request: 40
- Support request: 2
- Plan: 5

Major/Critical counts:

- Critical total: 6
- Major total: 27

### Existing Skills Found

Potentially related skills:

- `scottfalconer/drupal-issue-queue@drupal-issue-queue`
- `kanopi/cms-cultivator@drupalorg-issue-helper`
- `kanopi/cms-cultivator@drupalorg-contribution-helper`
- `mindrally/skills@drupal-development`

These may be useful references, but none appeared to provide the specific "find worthwhile Webform issues and rank them by fixability" workflow.

### Matt Glaman Blog Post

Relevant post:

https://mglaman.dev/blog/drupalorg-cli-080-gitlab-issue-fork-and-merge-request-commands

Key points:

- `drupalorg-cli 0.8.0` aimed to make the CLI useful for developers using AI agents to assist with Drupal.org issues.
- `issue:show --with-comments --format=llm` gives agents enough context for bounded work.
- The CLI ships agent skills:
  - `drupalorg-cli`
  - `drupalorg-issue-summary-update`
  - `drupalorg-work-on-issue`
- The CLI exposes an MCP stdio server:

```bash
drupalorg mcp:serve
```

No separate deeper blog post solely about AI agents and `drupalorg-cli` was found during the initial search. The `0.8.0` release post appears to be the primary explanation.
