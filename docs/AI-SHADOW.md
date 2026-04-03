# AI-SHADOW.md

A guide for setting up AI agent configuration in a project while keeping all AI-related
files invisible to Git — no `.gitignore` pollution, no accidental commits of local AI
tooling, no divergence between collaborators who use different agents.

The "shadow" pattern uses `.git/info/exclude` (a per-clone, never-committed ignore file)
so that AI directories exist on disk but are completely invisible to the repository.

---

## Table of Contents

1. [Core Concepts](#core-concepts)
2. [Directory Structure](#directory-structure)
3. [Setup](#setup)
4. [Adding Skills](#adding-skills)

---

## Core Concepts

| Concern                                          | Solution |
|--------------------------------------------------|---|
| AI directories must not appear in `git status`   | `.git/info/exclude` (per-clone, never committed) |
| `CLAUDE.md` and `AGENTS.md` stay in sync         | `AGENTS.md` is canonical; `CLAUDE.md` symlinks to it |
| Claude and Codex share one skills library        | `.agents/skills/` is canonical; `.claude/skills` symlinks to it |
| Skills are versioned and shareable               | Downloaded via [skills.sh](https://skills.sh/) into `.agents/skills/` |

---

## Directory Structure

```
project-root/
│
├── AGENTS.md                         # Canonical agent instructions (Claude + Codex)
├── .agents/                          # Canonical AI configuration root (Git-excluded)
│   └── skills/                       # Skills downloaded via skills.sh
│
├── CLAUDE.md -> AGENTS.md            # Symlink — Claude reads this automatically
└── .claude/
    └── skills -> ../.agents/skills   # Symlink (Git-excluded)
```

Claude Code also reads `.claude/skills/` for its skills discovery. A symlink keeps
that pointing at the shared library:

## Setup

Run these commands once from the project root after cloning. They create the directory
structure, symlinks, and Git exclusions in one pass.

AI Agents (Codex)

```bash
# Create AGENTS.md (the canonical instruction file for both Claude and Codex)
touch AGENTS.md

# Create the shared agents skills directory
mkdir -p .agents/skills
```

Claude Code

```bash
# Symlink CLAUDE.md → AGENTS.md so Claude picks up the same instructions
ln -s AGENTS.md CLAUDE.md

# Create .claude/ and symlink its skills directory to the shared library
mkdir -p .claude
ln -s ../.agents/skills .claude/skills
```bash

Git exlcusions

```bash
# Exclude all AI directories from Git (local clone only — never committed)
cat >> .git/info/exclude << 'EOF'
AGENTS.md
.agents/
CLAUDE.md
.claude/
EOF
```

After running, confirm everything looks right:

```bash
git status             # none of the above should appear as untracked
ls -la CLAUDE.md       # CLAUDE.md -> AGENTS.md
ls -la .claude/skills  # .claude/skills -> ../.agents/skills
```

## Adding Skills

Skills are reusable `SKILL.md` instruction sets that extend agent capabilities.
The canonical source is [skills.sh](https://skills.sh/docs) — a directory of
community and official skills installable via the `npx skills` CLI (powered by
[vercel-labs/skills](https://github.com/vercel-labs/skills), no global install needed).

Skills installed into `.agents/skills/` are automatically available to both Claude
Code (via the `.claude/skills` symlink) and Codex, since `.agents/skills/` is the
universal shared location recognized by both agents.

### Install skills

Run each command interactively and select the skills you need from the prompt:

```bash
# CMS-focused skills for Drupal, WordPress, and related tooling
# https://github.com/kanopi/cms-cultivator
npx skills add kanopi/cms-cultivator

# Drupal-specific Claude Code skills
# https://github.com/grasmash/drupal-claude-skills
npx skills add grasmash/drupal-claude-skills

# Skills for installed Drupal.org modules (select from your project's modules)
# https://github.com/mglaman/drupalorg-cli
ddev drush drupalorg skill:install
```

Each command presents an interactive selector — choose only the skills relevant
to your project. You can always re-run a command to add more later.

### Re-install skills on a fresh clone

Because `.agents/` is Git-excluded, skills must be re-installed after each clone.
Keep a simple script to automate this:

```bash
#!/usr/bin/env bash
# scripts/install-skills.sh — run after setup to restore skills on a new clone

set -euo pipefail

npx skills add kanopi/cms-cultivator
npx skills add grasmash/drupal-claude-skills
ddev drush drupalorg skill:install
```

## Notes

- `.git/info/exclude` is **not committed** — every contributor must run the setup
  commands once per clone.
- If your team wants to commit `AGENTS.md` (so Codex and Claude pick it up without
  any local setup), remove it from the exclude block in step 5 of the setup.
- On Windows, symlinks require Developer Mode enabled or running the terminal as
  Administrator. WSL2 avoids this entirely.
- To opt out of anonymous telemetry from the skills CLI, set `DISABLE_TELEMETRY=1`
  before running any `npx skills` command.

## See Also

- [Drupal AI Best Practices — meta issue: a mega list of repos/projects/issues providing best practice Drupal guidance](https://www.drupal.org/project/ai_best_practices/issues/3581683)
- [kanopi/cms-cultivator](https://github.com/kanopi/cms-cultivator) — CMS-focused agent skills
- [grasmash/drupal-claude-skills](https://github.com/grasmash/drupal-claude-skills) — Drupal-specific Claude Code skills
- [mglaman/drupalorg-cli](https://github.com/mglaman/drupalorg-cli) — Drush `drupalorg` commands including `skill:install`
- [skills.sh docs](https://skills.sh/docs) — Agent skills directory and CLI reference
- [vercel-labs/skills](https://github.com/vercel-labs/skills) — The `npx skills` CLI
