# Claude Code — CLI Tips & Tricks

A practical reference for getting started with Claude Code, Anthropic's agentic AI coding assistant that lives in your terminal.

---

## What is Claude Code?

Claude Code (`claude`) is a command-line tool that understands your codebase, edits files directly, runs shell commands, and helps you code faster through natural language conversation. Unlike a chat interface, it operates directly inside your project — reading files, writing code, running tests, and managing Git, all without you leaving the terminal.

---

## Installation

### npm (cross-platform)
```bash
npm install -g @anthropic-ai/claude-code
```

### Homebrew (macOS)
```bash
brew install --cask claude-code
```
> Note: Homebrew does **not** auto-update. Run `brew upgrade claude-code` periodically.

### Verify installation
```bash
claude --version
```

---

## Authentication

Claude Code requires a Claude.ai subscription or API (Console) account.

```bash
claude          # First launch will prompt you to log in
claude auth login   # Re-authenticate or switch accounts from the terminal
```

Credentials are stored locally after first login — you won't need to log in again unless you explicitly log out or switch accounts.

---

## Starting a Session

```bash
# Start interactive session in current directory
cd /path/to/your/project
claude

# Seed a session with an initial prompt
claude "explain how the routing system works in this project"

# Continue your most recent conversation
claude -c
claude --continue

# Resume a specific previous session by ID
claude -r <session-id>
```

---

## Essential CLI Commands

| Command | What it does |
|---|---|
| `claude` | Start interactive session |
| `claude "task"` | Start session pre-seeded with a prompt |
| `claude -p "query"` | Non-interactive: run query, print result, exit |
| `claude -c` | Continue most recent conversation |
| `claude -r <id>` | Resume specific session by ID |
| `claude update` | Manually update Claude Code to latest version |

---

## Slash Commands (Inside a Session)

Type `/` to see all available slash commands. Most important ones:

| Slash Command | What it does |
|---|---|
| `/help` | Show all available commands |
| `/clear` | Clear conversation history (fresh context) |
| `/compact` | Compress context to reduce token usage |
| `/compact [instructions]` | Compact with specific focus instructions |
| `/plan` | Enter plan mode directly from the prompt |
| `/model` | Switch Claude model for this session |
| `/memory` | Edit CLAUDE.md memory files |
| `/rewind` | Restore code and conversation to a previous checkpoint |
| `/context` | Visualise context usage as a coloured grid |
| `/mcp` | Manage MCP server connections |
| `/doctor` | Diagnose installation and setup issues |
| `/cost` | Show token usage and cost for the current session |
| `/stats` | Show usage patterns over time |
| `/permissions` | View and modify tool permissions |
| `/exit` | End the session |

---

## Keyboard Shortcuts (Inside a Session)

| Shortcut | Action |
|---|---|
| `Escape` | Stop Claude mid-task (does NOT exit — session stays open) |
| `Ctrl+C` | Cancel current input or generation |
| `Ctrl+D` | Exit Claude Code session |
| `Escape, Escape` | Rewind/restore code and conversation to a previous checkpoint |
| `↑` (up arrow) | Navigate command history |
| `Tab` | Accept the current prompt suggestion |
| `!` (line prefix) | Run a bash command directly without Claude interpreting it |

> **Common gotcha:** `Ctrl+D` exits the whole program. Use `Escape` to stop Claude mid-task while staying in the session.

---

## Headless / Scripting Mode

Use `-p` (print mode) for non-interactive use — Claude outputs the result and exits. Great for piping and automation.

```bash
# One-off query
claude -p "how many PHP files are in this project?"

# Pipe data into Claude
cat error.log | claude -p "summarize the errors"

# Pipe with JSON output for scripting
claude -p "list all routes in this project" --output-format json

# Limit agentic turns (useful for automation)
claude --max-turns 5 -p "run the tests and summarize failures"
```

---

## Working with Files

You don't need to manually add context. Claude reads your project files as needed.

```bash
# Reference specific files in your prompt
claude "explain src/Controller/HomeController.php"

# Run a bash command directly without Claude interpreting it
! find src/ -name "*.php" | wc -l

# Drag a file into the terminal to reference it
# (Hold Shift while dragging in some terminals to reference rather than open)
```

---

## Permissions & Settings

Claude always asks before modifying files. You can control permissions explicitly.

### How tool names work

| Pattern | Meaning |
|---|---|
| `ToolName` | Permit every action for that tool |
| `ToolName(*)` | Permit any argument (explicit wildcard) |
| `ToolName(filter)` | Permit only matching calls |
| `Bash(git *)` | Allow all git subcommands |
| `Bash(git commit *)` | Allow git commit with any message |

Deny rules always override allow rules.

### Common `--allowedTools` combinations

```bash
# Read-only exploration — safe for auditing an unfamiliar codebase
claude --allowedTools "Read" "Grep" "Glob" "LS"

# Read + edit, no shell access — controlled refactoring
claude --allowedTools "Read" "Grep" "Glob" "Edit" "MultiEdit" "Write"

# Standard development — read, write, and safe git operations
claude --allowedTools "Read" "Grep" "Glob" "Edit" "MultiEdit" "Write" "Bash(git *)"

# Full dev workflow — includes package manager and GitHub CLI
claude --allowedTools "Read" "Grep" "Glob" "Edit" "MultiEdit" "Write" "Bash(git *)" "Bash(composer *)" "Bash(gh *)"

# Testing only — run tests but nothing else
claude --allowedTools "Read" "Bash(npm test)" "Bash(php bin/phpunit *)"

# With web access — fetch docs or search while coding
claude --allowedTools "Read" "Edit" "Write" "WebFetch" "WebSearch"
```

### Available built-in tools

| Tool | What it does |
|---|---|
| `Read` | Read file contents |
| `Write` | Create or overwrite files |
| `Edit` | Make targeted edits to existing files |
| `MultiEdit` | Multiple edits to a file in one operation |
| `Bash(...)` | Run shell commands (scope with filters) |
| `Glob` | Find files by pattern |
| `Grep` | Search file contents |
| `LS` | List directory contents |
| `WebFetch` | Fetch a URL |
| `WebSearch` | Search the web |
| `TodoWrite` | Manage task lists |
| `Task` | Launch a sub-agent for complex work |

### Per-session permission flags
```bash
# Allow only specific tools for this session
claude --allowedTools "Read" "Grep" "Bash(git *)"

# Bypass all permission prompts (use carefully — good for trusted automation)
claude --dangerously-skip-permissions
```

### Manage permissions interactively (inside a session)
```bash
/permissions              # View current permissions
/permissions add Edit     # Allow a tool
/permissions add "Bash(git commit *)"
/permissions remove "Bash(rm *)"  # Deny a tool
```

### Project-level settings: `.claude/settings.json`
```json
{
  "model": "claude-sonnet-4-6",
  "permissions": {
    "allow": ["Read", "Write", "Edit", "Bash(git *)", "Bash(composer *)"],
    "deny": [
      "Read(./.env)",
      "Read(./.env.*)",
      "Write(./production.config.*)"
    ]
  }
}
```

---

## Model Selection

Claude Code gives you access to multiple Claude models.

```bash
# Switch model inside a session
/model

# Start a session with a specific model
claude --model claude-opus-4-6
```

**Model IDs:**
- `/model sonnet` — `claude-sonnet-4-6` — Best default. Fast, capable, efficient for most tasks.
- `/model haiku` — `claude-haiku-4-5-20251001` — Faster and cheaper. Good for simpler, repetitive tasks.
- `/model opus` — `claude-opus-4-6` — Most powerful. Use for complex planning, architecture decisions, or multi-step reasoning.

---

## Common Workflow Examples

```bash
# Explore a new codebase
claude "explain how this project is structured and what each major directory does"

# Add a feature
claude "add a basic console command example to the playground following the pattern in CLAUDE.md"

# Debug an error
claude "I'm getting a 'Service not found' error when visiting /examples/di — debug it"

# Write tests
claude "write a functional test for the routing examples controller"

# Code review
claude "review my recent changes and flag anything that breaks Symfony best practices"

# Update docs
claude "update the homepage controller with the new examples I just added"
```

---

## Quick Reference

```bash
# Installation
npm install -g @anthropic-ai/claude-code
claude --version

# Session management
claude                    # start interactive
claude "prompt"          # seed with prompt
claude -p "query"        # non-interactive, print and exit
claude -c                # continue last session
/clear                   # fresh context
/compact                 # compress context
/rewind                  # restore to a previous checkpoint

# Settings
/model                   # switch model
/help                    # show all commands
/doctor                  # diagnose install issues
claude --version         # check version
claude update            # update to latest
```

---

## Further Reading

### General
- [Claude Code Quickstart](https://code.claude.com/docs/en/quickstart)
- [Common Workflows](https://code.claude.com/docs/en/common-workflows)
- [CLI Reference](https://code.claude.com/docs/en/cli-reference)
- [Configuration & Settings](https://code.claude.com/docs/en/settings)
- [GitHub Actions Integration](https://code.claude.com/docs/en/github-actions)
- [Anthropic Discord](https://www.anthropic.com/discord)

### Drupal
- [Claude Code meets Drupal | Dries Buytaert](https://dri.es/claude-code-meets-drupal)
- [Mastering Claude Code for Drupal Development](https://bonnici.co.nz/blog/mastering-claude-code-drupal-development) — five practical tips from real Drupal project experience
- [I Tried Claude Code for a Month on Drupal](https://tresbien.tech/blog/i-tried-claude-code-month-drupal/)
- [Claude Code for Symfony and PHP — The Setup That Actually Works](https://dev.to/javiereguiluz/claude-code-for-symfony-and-php-the-setup-that-actually-works-1che)
- [Claude Code | Drupal.org](https://www.drupal.org/project/claude_code)
- [FreelyGive/ddev-claude-code](https://github.com/FreelyGive/ddev-claude-code) — DDEV add-on for Claude Code
- [Mastering Drupal AI: Drupal CMS + AI + Claude Code on Drupal Forge (YouTube)](https://www.youtube.com/watch?v=pHvZa8G2dx0)

### CLAUDE.md (AGENTS.md)
- [CLAUDE.md Memory Files](https://code.claude.com/docs/en/memory)
- [agents.md specification](https://agents.md/)
- [amazeeio/drupal-agents-md](https://github.com/amazeeio/drupal-agents-md)
- [Drupal agents.md | jrockowitz.com](https://www.jrockowitz.com/blog/drupal-agents-md.amp)
- [Drupal CMS AGENTS.md issue](https://www.drupal.org/project/drupal_cms/issues/3569529)
- [Drupal agents.md governance debate | The Drop Times](https://www.thedroptimes.com/66452/drupal-agents-md-governance-debate)

### Skills
- [Claude Code Skills](https://code.claude.com/docs/en/skills)
- [Agent Skills Specification | agentskills.io](https://agentskills.io/specification)
- [Claude Skill — Drupal and DDEV Site Setup](https://www.drupal.org/project/claude_skill_drupal_ddev_site_setup)
- [grasmash/drupal-claude-skills](https://github.com/grasmash/drupal-claude-skills) — community Drupal skill collection (50+ topics)

### MCP
- [MCP Server Integration](https://code.claude.com/docs/en/mcp)
- [Model Context Protocol — Getting Started](https://modelcontextprotocol.io/docs/getting-started/intro)
- [MCP Server module | Drupal.org](https://www.drupal.org/project/mcp_server) — exposes Drupal as an MCP server for Claude Code
- [drupal-modules-mcp](https://github.com/Cleversoft-IT/drupal-modules-mcp) — MCP server for querying Drupal.org module info
