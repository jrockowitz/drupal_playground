# Claude Code — Cheatsheet

---

## Installation & Auth

```bash
npm install -g @anthropic-ai/claude-code   # install
claude --version                            # verify
claude update                               # update
claude auth login                           # re-authenticate
```

---

## Starting Sessions

```bash
claude                        # interactive session
claude "prompt"               # seed with prompt
claude -p "query"             # non-interactive: print and exit
claude -c                     # continue last session
claude -r <session-id>        # resume specific session
claude --model claude-opus-4-6  # start with specific model
```

---

## Slash Commands

| Command | What it does |
|---|---|
| `/help` | Show all commands |
| `/clear` | Fresh context (use between unrelated tasks) |
| `/compact [instructions]` | Compress context |
| `/context` | Visualise context usage |
| `/cost` | Token usage and cost for this session |
| `/stats` | Usage patterns over time |
| `/model` | Switch model |
| `/plan` | Enter plan mode |
| `/rewind` | Restore code and conversation to a checkpoint |
| `/memory` | Browse/edit auto memory |
| `/init` | Generate or improve CLAUDE.md |
| `/permissions` | View and modify tool permissions |
| `/mcp` | Manage MCP server connections |
| `/plugin` | Browse, install, and manage plugins |
| `/doctor` | Diagnose installation issues |
| `/exit` | End the session |

---

## Keyboard Shortcuts

| Shortcut | Action |
|---|---|
| `Escape` | Stop Claude mid-task (session stays open) |
| `Escape, Escape` | Rewind to previous checkpoint |
| `Ctrl+C` | Cancel current input or generation |
| `Ctrl+D` | Exit session |
| `↑` | Command history |
| `Tab` | Accept suggestion |
| `!` (line prefix) | Run bash directly without Claude |

---

## Models

| Alias | Model ID | Use for |
|---|---|---|
| `sonnet` | `claude-sonnet-4-6` | Default — fast, capable, most tasks |
| `haiku` | `claude-haiku-4-5-20251001` | Simple/repetitive tasks |
| `opus` | `claude-opus-4-6` | Complex planning, deep reasoning |

```bash
/model sonnet    # switch inside session
/model opus      # upgrade for planning, switch back for implementation
```

---

## Permissions & Tools

```bash
# Per-session
claude --allowedTools "Read" "Grep" "Glob" "Edit" "Bash(git *)"
claude --dangerously-skip-permissions   # bypass all prompts (automation only)

# Inside a session
/permissions
/permissions add "Bash(git commit *)"
/permissions remove "Bash(rm *)"
```

**Common presets:**

```bash
# Read-only
claude --allowedTools "Read" "Grep" "Glob" "LS"

# Read + edit, no shell
claude --allowedTools "Read" "Grep" "Glob" "Edit" "MultiEdit" "Write"

# Standard dev
claude --allowedTools "Read" "Grep" "Glob" "Edit" "MultiEdit" "Write" "Bash(git *)"

# Full workflow
claude --allowedTools "Read" "Grep" "Glob" "Edit" "MultiEdit" "Write" \
  "Bash(git *)" "Bash(composer *)" "Bash(gh *)"
```

**Tool permission syntax:**

| Pattern | Meaning |
|---|---|
| `Bash(git *)` | All git subcommands |
| `Bash(git commit *)` | git commit with any message |
| `Read(./.env)` | Deny reading .env (in deny list) |

---

## CLAUDE.md Memory Files

| File | Scope | Shared? |
|---|---|---|
| `~/.claude/CLAUDE.md` | Global (all projects) | No |
| `./CLAUDE.md` | Project root | Yes — commit to git |
| `./CLAUDE.local.md` | Project (personal) | No — gitignore it |
| `.claude/rules/*.md` | Modular rules, load on demand | Yes |

- Target **50–100 lines** in the root CLAUDE.md
- Use `/init` to generate a starter, then cut ruthlessly
- Re-read mid-session: `@CLAUDE.md`
- Add corrections permanently: `"Add this to CLAUDE.md"`
- Auto memory: `/memory` to browse what Claude has saved

---

## MCP Servers

```bash
# Add servers
claude mcp add github -e GITHUB_PERSONAL_ACCESS_TOKEN=$TOKEN \
  -- npx -y @modelcontextprotocol/server-github
claude mcp add-json github '{"type":"http","url":"https://api.githubcopilot.com/mcp","headers":{"Authorization":"Bearer YOUR_PAT"}}'
claude mcp add context7 -- npx -y @upstash/context7-mcp@latest

# Manage
claude mcp list
claude mcp remove <name>
/mcp         # toggle servers on/off inside session
/context     # see per-server token consumption
```

**Scopes:** `--scope local` (default, personal) | `--scope project` (.mcp.json, git-tracked) | `--scope user` (all projects)

---

## Skills

Skills are `SKILL.md` files Claude auto-invokes when relevant.

| Location | Scope |
|---|---|
| `.claude/skills/` | Project — shared via git |
| `~/.claude/skills/` | Global — all projects |

```bash
# Install from skills.sh
npx skills add vercel-labs/agent-skills    # project
npx skills add --user anthropics/skills   # global

# Recommended Drupal skills
npx skills add omedia/drupal-skill
npx skills add grasmash/drupal-claude-skills
npx skills add kanopi/cms-cultivator
```

**SKILL.md frontmatter:**
```yaml
---
name: my-skill
description: When to use this skill — be specific for reliable auto-invocation
allowed-tools: Read, Grep
context: fork       # run in separate context window
---
```

---

## Plugins

Plugins bundle commands, skills, agents, MCP servers, and hooks into one install.

```bash
/plugin                                  # browse UI
/plugin install context7@claude-plugins-official
/plugin install superpowers@superpowers-marketplace
/plugin list
/plugin remove <name>
/plugin marketplace add obra/superpowers-marketplace

# Local development
claude --plugin-dir ./my-plugin
```

Plugin commands are namespaced: `/plugin-name:command`

---

## Headless / Scripting

```bash
# One-off query
claude -p "how many PHP files are in this project?"

# Pipe data in
cat error.log | claude -p "summarize the errors"

# JSON output
claude -p "list all routes" --output-format json

# Limit turns
claude --max-turns 5 -p "run tests and summarize failures"
```

---

## Token Saving Habits

1. `/clear` between unrelated tasks
2. `/compact` when sessions run long
3. Use Sonnet by default; switch to Opus only for planning
4. `/mcp` to disable unused MCP servers
5. Keep CLAUDE.md under 5k tokens — reference `docs/` instead of inlining
6. `/context` to see what's consuming your window

---

## Further Reading

- [docs/CLAUDE-CODE.md](CLAUDE-CODE.md) — Full CLI reference
- [docs/CLAUDE-CODE-TIPS.md](CLAUDE-CODE-TIPS.md) — Tips, token management, prompting
- [docs/CLAUDE-CODE-CLAUDE.md](CLAUDE-CODE-CLAUDE.md) — CLAUDE.md authoring guide
- [docs/CLAUDE-CODE-MCP.md](CLAUDE-CODE-MCP.md) — MCP setup and servers
- [docs/CLAUDE-CODE-SKILLS.md](CLAUDE-CODE-SKILLS.md) — Skills system
- [docs/CLAUDE-CODE-PLUGINS.md](CLAUDE-CODE-PLUGINS.md) — Plugin system
