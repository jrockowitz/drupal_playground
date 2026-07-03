# Webform Issue Maintenance Agent Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extend the existing Webform issue maintenance skill with the approved on-demand scan, selection, local-work, and review-gated workflow.

**Architecture:** Keep `webform-issue-maintenance` as the single skill entry point. Add a committed public issue tracker under `.agents/webform-issue-maintenance/` using an index plus individual issue notes, mirroring the security notes pattern without private storage.

**Tech Stack:** Markdown skill instructions, committed repository docs, `drupalorg-cli`, DDEV, Git.

---

### Task 1: Update The Design Decision

**Files:**
- Modify: `docs/superpowers/specs/2026-06-12-webform-issue-worker-agent-design.md`

- [x] **Step 1: Replace the open implementation choice**

Update the spec so it recommends extending `webform-issue-maintenance` instead of creating a new skill.

- [x] **Step 2: Add the committed tracker decision**

Document that issue-in-progress notes live in a committed public directory and are updated issue note first, then index.

### Task 2: Add Public Issue Tracker

**Files:**
- Create: `.agents/webform-issue-maintenance/README.md`
- Create: `.agents/webform-issue-maintenance/index.md`
- Create: `.agents/webform-issue-maintenance/issues/README.md`
- Create: `.agents/webform-issue-maintenance/issues/_template.md`

- [x] **Step 1: Create tracker README**

Define purpose, layout, and update order.

- [x] **Step 2: Create tracker index**

Create a committed queue snapshot with empty active issue sections.

- [x] **Step 3: Create issue note template**

Create a reusable note format for selected public Webform issues.

### Task 3: Update Existing Skill

**Files:**
- Modify: `.agents/skills/webform-issue-maintenance/SKILL.md`

- [x] **Step 1: Add workflow gates**

Document scan, selection, local work, review, and approval-only publication.

- [x] **Step 2: Add tracker rules**

Document the committed tracker path and update order.

- [x] **Step 3: Preserve existing commands**

Keep current Drupal.org CLI, scoring, per-issue, verification, and output guidance intact.

### Task 4: Verify Documentation Changes

**Files:**
- Verify: `.agents/skills/webform-issue-maintenance/SKILL.md`
- Verify: `.agents/webform-issue-maintenance/index.md`
- Verify: `docs/superpowers/specs/2026-06-12-webform-issue-worker-agent-design.md`

- [x] **Step 1: Search for obsolete recommendation**

Run: `rg -n "webform-issue-worker|new skill|private|approval|commit|push" .agents/skills/webform-issue-maintenance/SKILL.md .agents/webform-issue-maintenance docs/superpowers/specs/2026-06-12-webform-issue-worker-agent-design.md`

- [x] **Step 2: Review git diff**

Run: `git diff -- .agents/skills/webform-issue-maintenance .agents/webform-issue-maintenance docs/superpowers/specs/2026-06-12-webform-issue-worker-agent-design.md docs/superpowers/plans/2026-06-12-webform-issue-maintenance-agent.md`
