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

## Setup

Run these commands once from the project root after cloning. They create the directory
structure, symlinks, and Git exclusions in one pass.

### OPTION 1: Create Agents and Claude directories

Directory structure:

```
project-root/
│
├── AGENTS.md                         # Agent instructions
├── .agents/                          # Agent configuration
│   └── skills/                       # Agent configuration skills
│
├── CLAUDE.md -> AGENTS.md            # Symlink — Agent instructions
└── .claude/
    └── skills -> ../.agents/skills   # Symlink — Agent configuration skills
```

```bash
# Create AGENTS.md (the canonical instruction file for both Claude and Codex)
touch AGENTS.md

# Create the shared agents skills directory
mkdir -p .agents/skills

# Symlink CLAUDE.md → AGENTS.md so Claude picks up the same instructions
ln -s AGENTS.md CLAUDE.md

# Create .claude/ and symlink its skills directory to the shared library
mkdir -p .claude
ln -s ../.agents/skills .claude/skills

# List the files and directories.
ls -al AGENTS.md .agents CLAUDE.md .claude

# PHPStorm - Junie AI Guidelines
mkdir -p .junie
ln -sf AGENTS.md .junie/guidelines.md

# Exclude all AI directories from Git (local clone only — never committed)
cat >> .git/info/exclude << 'EOF'
/AGENTS.md
/.agents
/CLAUDE.md
/.claude
/.junie
/skills-lock.json
EOF
```

### OPTION 2: Symlink Agents and Claude directories

Directory structure:

```
project-root/
│
├── AGENTS.md -> ../drupal_playground/AGENTS.md     # Symlink — Agents instructions
├── .agents -> ../drupal_playground/.agents         # Symlink — Agents configuation
│
├── CLAUDE.md -> ../drupal_playground/AGENTS.md     # Symlink — Claude instructions
└── .claude -> ../drupal_playground/.claude         # Symlink — Claude configuration
```

```bash
# Symlink all files and directories to the drupal_playground directory.
ln -sf ../drupal_playground/AGENTS.md AGENTS.md
ln -sf ../drupal_playground/.agents .agents
ln -sf ../drupal_playground/CLAUDE.md CLAUDE.md
ln -sf ../drupal_playground/.claude .claude

# List the files and directories.
ls -al AGENTS.md .agents CLAUDE.md .claude

# PHPStorm - Junie AI Guidelines
mkdir -p .junie
ln -sf ../drupal_playground/AGENTS.md .junie/guidelines.md
ls -al .junie/guidelines.md

# Exclude all syminks
cat >> .git/info/exclude << 'EOF'
/AGENTS.md
/.agents
/CLAUDE.md
/.claude
/.junie
EOF
```

---

## PHPStorm - Junie AI Guidelines

AGENTS.md is not picked up automatically by Junie. To enable it, you must set the path in settings:

Reference: [JUNIE-618: Support AGENTS.md](https://youtrack.jetbrains.com/issue/JUNIE-618/Support-AGENTS.md)

---

## MCP servers

```bash
# List the MCP servers for both Codex and Claude
codex mcp list
claude mcp list
```

---

## Adding Skills

Skills are reusable `SKILL.md` instruction sets that extend agent capabilities.
The canonical source is [skills.sh](https://skills.sh/docs) — a directory of
community and official skills installable via the `npx skills` CLI (powered by
[vercel-labs/skills](https://github.com/vercel-labs/skills), no global install needed).

Skills installed into `.agents/skills/` are automatically available to both Claude
Code (via the `.claude/skills` symlink).

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

---

## See Also

- [Drupal AI Best Practices — meta issue: a mega list of repos/projects/issues providing best practice Drupal guidance](https://www.drupal.org/project/ai_best_practices/issues/3581683)
- [kanopi/cms-cultivator](https://github.com/kanopi/cms-cultivator) — CMS-focused agent skills
- [grasmash/drupal-claude-skills](https://github.com/grasmash/drupal-claude-skills) — Drupal-specific Claude Code skills
- [mglaman/drupalorg-cli](https://github.com/mglaman/drupalorg-cli) — Drush `drupalorg` commands including `skill:install`
- [skills.sh docs](https://skills.sh/docs) — Agent skills directory and CLI reference
- [vercel-labs/skills](https://github.com/vercel-labs/skills) — The `npx skills` CLI
