# CLAUDE-CODE-PLUGINS.md: Plugin System & Marketplaces

Plugins are shareable packages that extend Claude Code with new capabilities. A single plugin can bundle **slash commands**, **subagents**, **MCP servers**, **hooks**, **skills**, and **LSP servers** into one installable unit. Plugins solve the distribution problem — install once, get everything, share with your team.

---

## Core Concepts

Before plugins, extending Claude Code meant manually configuring MCP servers in `.mcp.json`, writing skills in `.claude/skills/`, and copying hook configurations between projects. Plugins package all of these into a single installable unit.

### Plugin Components

A plugin can contain any combination of:

| Component | Purpose |
|---|---|
| Commands | Slash commands (`/plugin-name:command`) |
| Skills | Auto-invoked expertise (`SKILL.md` files) |
| Agents | Custom subagents (`.md` files with frontmatter) |
| MCP Servers | External tool connections |
| Hooks | Lifecycle event handlers |
| LSP Servers | Language Server Protocol for code intelligence |

### Plugin Structure

```
my-plugin/
├── .claude-plugin/
│   └── plugin.json          # Manifest
├── commands/                 # Slash commands
│   └── review.md
├── skills/                   # Auto-invoked skills
│   └── my-skill/
│       └── SKILL.md
├── agents/                   # Custom subagents
│   └── reviewer.md
├── hooks/                    # Lifecycle hooks
│   └── pre-commit.sh
└── .mcp.json                 # MCP server definitions
```

---

## Marketplaces

Marketplaces are catalogs of plugins you can browse and install. Adding a marketplace gives you access to browse — no plugins are installed until you choose them.

### Official Marketplace

The Anthropic official marketplace (`claude-plugins-official`) is **pre-configured** and available when you start Claude Code. No setup needed.

```bash
# Browse and install from official marketplace
/plugin install context7@claude-plugins-official
```

### Community Marketplaces

Add third-party marketplaces for more options:

```bash
# Add a community marketplace
/plugin marketplace add anthropics/claude-code-plugins
/plugin marketplace add ComposioHQ/awesome-claude-plugins
/plugin marketplace add wshobson/agents

# Browse after adding
/plugin
# Go to "Discover" tab
```

### Notable Marketplaces

- [anthropics/claude-plugins-official](https://github.com/anthropics/claude-plugins-official) — Official, Anthropic-curated
- [anthropics/claude-code-plugins](https://github.com/anthropics/claude-code-plugins) — Anthropic demo/example plugins
- [Chat2AnyLLM/awesome-claude-plugins](https://github.com/Chat2AnyLLM/awesome-claude-plugins) — 43 marketplaces, 834+ plugins
- [wshobson/agents](https://github.com/wshobson/agents) — 72 plugins, 112 agents, 146 skills
- [buildwithclaude.com](https://buildwithclaude.com) — 487+ extensions
- [claudecodemarketplace.com](https://claudecodemarketplace.com) — Aggregator

---

## Installing Plugins

### Interactive UI

```bash
/plugin
# Tab through: Discover → Installed → Config → Errors
# Select a plugin and press Enter
# Choose installation scope
```

### Command Line

```bash
# Install from official marketplace
/plugin install context7@claude-plugins-official

# Install from a community marketplace (after adding it)
/plugin install connect-apps@ComposioHQ/awesome-claude-plugins

# Test a local plugin during development
claude --plugin-dir ./my-plugin

# Load multiple local plugins
claude --plugin-dir ./plugin-one --plugin-dir ./plugin-two
```

### Installation Scopes

| Scope | Location | Shared? | Use case |
|---|---|---|---|
| User (default) | `~/.claude/` | No | Personal across all projects |
| Project | `.claude/settings.json` | Yes (git-tracked) | Team-wide standards |
| Local | Current repo only | No | Personal for this repo |
| Managed | Admin-deployed | Read-only | Enterprise policies |

---

## Using Installed Plugins

Plugin slash commands are **namespaced** with the plugin name:

```bash
# Plugin command format
/plugin-name:command

# Examples
/commit-commands:conventional-commit
/python-development:python-scaffold fastapi-microservice
/conductor:setup
```

Skills from plugins activate automatically based on context — no explicit invocation needed.

MCP servers from plugins start automatically when the plugin is enabled. Restart Claude Code after enabling/disabling plugins to apply MCP changes.

---

## Notable Plugins

### Code Intelligence (LSP)

LSP plugins give Claude jump-to-definition, find-references, and type-error detection. These require the language server binary on your system.

```bash
# If you have a language server installed, Claude may prompt
# you to install the corresponding plugin automatically
```

### Development Workflow

- **commit-commands** — Git workflow commands (conventional commits, etc.)
- **superpowers** — Enhanced UI output with distinctive design
- **context7** — Up-to-date, version-specific library documentation
- **repomix** — Pack codebase into AI-friendly formats

### Multi-Agent Orchestration

- **conductor** — Context-driven development with structured workflows
- **agent-teams** — Parallel multi-agent orchestration

---

## Creating Plugins

### Minimal Plugin

Create a directory with a manifest:

```bash
mkdir -p my-plugin/.claude-plugin
```

```json
// my-plugin/.claude-plugin/plugin.json
{
  "name": "my-plugin",
  "description": "What this plugin does",
  "version": "1.0.0"
}
```

Add components as needed (commands, skills, agents, hooks, MCP servers).

### Plugin with MCP Server

Define MCP servers in `.mcp.json` at the plugin root:

```json
{
  "my-tool": {
    "command": "${CLAUDE_PLUGIN_ROOT}/servers/my-server",
    "args": ["--config", "${CLAUDE_PLUGIN_ROOT}/config.json"],
    "env": {
      "API_KEY": "${API_KEY}"
    }
  }
}
```

Or inline in `plugin.json`:

```json
{
  "name": "my-plugin",
  "mcpServers": {
    "plugin-api": {
      "command": "${CLAUDE_PLUGIN_ROOT}/servers/api-server",
      "args": ["--port", "8080"]
    }
  }
}
```

Use `${CLAUDE_PLUGIN_ROOT}` for plugin-relative paths.

### Local Testing

```bash
claude --plugin-dir ./my-plugin
```

### Distribution

Submit to the official marketplace via:

- In-app: `/plugin` → submission form
- Console: [platform.claude.com/plugins/submit](https://platform.claude.com/plugins/submit)

Or create your own marketplace as a Git repository.

---

## Troubleshooting

**Plugin not loading:** Clear cache and reinstall:
```bash
rm -rf ~/.claude/plugins/cache/marketplace-name
rm ~/.claude/plugins/installed_plugins.json
```

**"Plugin not found":** Use plugin names, not agent/command names. Add the marketplace suffix:
```bash
# Wrong — agent name
/plugin install typescript-pro

# Right — plugin name with marketplace
/plugin install javascript-typescript@marketplace-name
```

**Version requirement:** Plugins require Claude Code 1.0.33 or later:
```bash
claude --version
```

---

## Plugins vs. Standalone Configuration

| | Plugins | Standalone (`.claude/` directory) |
|---|---|---|
| Distribution | One install command | Manual copy/setup |
| Bundling | All components together | Each configured separately |
| Team sharing | Install by name | Copy files + docs |
| Best for | Community sharing, team standards | Personal workflows, quick experiments |

---

## Resources

- [Claude Code Plugin Docs](https://code.claude.com/docs/en/discover-plugins)
- [anthropics/claude-plugins-official](https://github.com/anthropics/claude-plugins-official) — Official marketplace
- [awesome-claude-plugins](https://github.com/Chat2AnyLLM/awesome-claude-plugins) — Curated list
- [Plugin Guide (Morph)](https://www.morphllm.com/claude-code-plugins) — Comprehensive walkthrough
- [buildwithclaude.com](https://buildwithclaude.com) — Plugin browser
