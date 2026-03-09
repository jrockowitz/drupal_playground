# CLAUDE-CODE-MCP.md: Model Context Protocol (MCP) in Claude Code

MCP (Model Context Protocol) is an open standard that connects Claude Code to external tools, databases, and APIs through a unified protocol. Each MCP server gives Claude new capabilities — query a database, manage GitHub issues, automate a browser, search the web — without leaving your terminal session.

---

## Core Concepts

Claude Code acts as an **MCP client** that connects to **MCP servers** exposing tools and data. Think of MCP servers as connectors: each one extends Claude's reach into a different part of your stack.

Claude Code is also **dual-natured** — it can simultaneously act as an MCP client (consuming servers you configure) and as an MCP server (exposing its own tools to other clients).

### What Claude Code Already Does Without MCP

File editing, code search, Bash execution, and codebase navigation are built-in. MCP extends beyond these into external services and APIs.

---

## Transport Types

### HTTP (Recommended for Remote)

The recommended transport for cloud-based services.

```bash
# Add an HTTP MCP server
claude mcp add --transport http notion https://mcp.notion.com/mcp

# With authentication
claude mcp add --transport http secure-api https://api.example.com/mcp \
  --header "Authorization: Bearer your-token"
```

### stdio (Local Processes)

Runs local processes on your machine. Best for tools needing direct system access.

```bash
# Add a stdio server
claude mcp add my-server -- npx -y @some/mcp-package

# With environment variable
claude mcp add github -e GITHUB_PERSONAL_ACCESS_TOKEN=$GITHUB_TOKEN \
  -- npx -y @modelcontextprotocol/server-github
```

### SSE (Deprecated)

Server-Sent Events transport. Use HTTP instead where available.

```bash
claude mcp add --transport sse asana https://mcp.asana.com/sse
```

---

## Adding MCP Servers

### CLI Commands

```bash
# Add a server
claude mcp add <name> -- <command> [args...]

# Add with JSON (Claude Code 2.1.1+)
claude mcp add-json github '{"type":"http","url":"https://api.githubcopilot.com/mcp","headers":{"Authorization":"Bearer YOUR_PAT"}}'

# List configured servers
claude mcp list

# Remove a server
claude mcp remove <name>

# Test a server
claude mcp get <name>

# Authenticate with OAuth servers
/mcp
```

### JSON Configuration (Direct Edit)

Edit `~/.claude.json` directly for full control:

```json
{
  "mcpServers": {
    "github": {
      "command": "npx",
      "args": ["-y", "@modelcontextprotocol/server-github"],
      "env": {
        "GITHUB_PERSONAL_ACCESS_TOKEN": "ghp_your_token"
      }
    },
    "context7": {
      "command": "npx",
      "args": ["-y", "@upstash/context7-mcp@latest"]
    },
    "sequential-thinking": {
      "command": "npx",
      "args": ["-y", "mcp-sequentialthinking-tools"]
    }
  }
}
```

After editing, restart Claude Code for changes to take effect.

---

## Configuration Scopes

```bash
# Scope flag when adding
claude mcp add --scope <scope> <name> -- <command>
```

| Scope | Location | Shared? | Use case |
|---|---|---|---|
| `local` (default) | `~/.claude.json` | No | Personal, per-project |
| `project` | `.mcp.json` | Yes (git-tracked) | Team standards |
| `user` | `~/.claude.json` | No | Personal, all projects |

### Team Configuration Pattern

Share server config in `.mcp.json` (without secrets), and let each developer add auth tokens locally:

```json
// .mcp.json (committed to git)
{
  "github": {
    "type": "http",
    "url": "https://api.githubcopilot.com/mcp",
    "headers": {
      "Authorization": "Bearer ${GITHUB_TOKEN}"
    }
  }
}
```

Each developer exports their own token:

```bash
export GITHUB_TOKEN=ghp_xxxx
```

For enterprise, administrators can deploy `managed-mcp.json` for centralized control with allowlist/denylist policies.

---

## Managing MCP Servers in a Session

### Toggle with @Mentions

```
# Enable/disable during a session
@github review my latest PR
```

### Interactive Management

```
/mcp
# Shows all servers with status (enabled/disabled)
# Toggle on/off interactively
```

### Monitor Context Usage

```
/context
# Shows token breakdown including MCP server consumption
```

Each enabled MCP server adds tool definitions to the system prompt, consuming context even when not actively used. **Disable unused servers** when approaching context limits or during focused work.

---

## Recommended MCP Servers

### Development Essentials

**GitHub** — PR management, issue tracking, code review:
```bash
claude mcp add-json github '{"type":"http","url":"https://api.githubcopilot.com/mcp","headers":{"Authorization":"Bearer YOUR_PAT"}}'
```

**Context7** — Up-to-date, version-specific library documentation:
```bash
claude mcp add context7 -- npx -y @upstash/context7-mcp@latest
```

**Sequential Thinking** — Break down complex multi-step tasks:
```bash
claude mcp add sequential-thinking -- npx -y mcp-sequentialthinking-tools
```

### Database

**PostgreSQL / Supabase** — Direct database access, schema exploration, querying.

### Monitoring & Testing

**Sentry** — Production error monitoring, stack traces, regression tracking.

**Playwright** — Browser automation and E2E testing.

### Design

**Figma** — Design-to-code workflows, design token extraction.

---

## Drupal/DDEV-Specific MCP Patterns

### Drupal MCP Server

The `drupal/mcp_server` module exposes Drupal's entity and configuration systems to MCP clients:

```bash
# After installing the Drupal MCP module
ddev composer require drupal/mcp_server
ddev drush en mcp_server
```

### Database Access via MCP

Connect Claude directly to your DDEV database:

```json
{
  "mcpServers": {
    "drupal-db": {
      "command": "npx",
      "args": ["-y", "@modelcontextprotocol/server-postgres"],
      "env": {
        "POSTGRES_URL": "postgresql://db:db@127.0.0.1:PORT/db"
      }
    }
  }
}
```

Replace `PORT` with the port from `ddev describe`.

---

## Plugin MCP Servers

Plugins can bundle MCP servers that start automatically when the plugin is enabled. These work identically to manually configured servers.

```json
// .mcp.json at plugin root
{
  "database-tools": {
    "command": "${CLAUDE_PLUGIN_ROOT}/servers/db-server",
    "args": ["--config", "${CLAUDE_PLUGIN_ROOT}/config.json"],
    "env": {
      "DB_URL": "${DB_URL}"
    }
  }
}
```

See [CLAUDE-CODE-PLUGINS.md](CLAUDE-CODE-PLUGINS.md) for details on plugin MCP bundling.

---

## Claude Code as MCP Server

Claude Code can expose its own tools (Bash, Read, Write, Edit, LS, Grep, Glob) to other MCP clients:

```bash
# Start Claude Code as an MCP server
claude mcp serve

# First-time setup (required — headless mode can't prompt)
claude --dangerously-skip-permissions
```

This enables other tools (Claude Desktop, Cursor, etc.) to use Claude Code's capabilities. Note: MCP servers that Claude Code itself connects to are **not** passed through to clients — each layer is separate.

---

## Environment Variables

```bash
# MCP server startup timeout (milliseconds)
MCP_TIMEOUT=10000 claude

# Max token output from MCP tools (default: 10,000)
MAX_MCP_OUTPUT_TOKENS=50000
```

## Troubleshooting

**"Connection closed" errors:**
- Windows: Use `cmd /c` wrapper for `npx` commands: `claude mcp add my-server -- cmd /c npx -y @some/package`
- Check that Node.js 20+ is installed
- Verify the server process starts independently

**Server not connecting:**
- Run `claude mcp get <name>` to test
- Check logs: `ls ~/Library/Logs/Claude/` (macOS)
- Ensure environment variables are properly exported

**High context usage from MCP:**
- Run `/context` to see per-server token consumption
- Disable servers not needed for current task with `/mcp`
- Consider uninstalling rarely-used servers

**npx servers auto-install:** Servers using `npx` commands install their packages automatically on first access.

---

## Resources

- [Claude Code MCP Docs](https://code.claude.com/docs/en/mcp)
- [MCP Specification](https://modelcontextprotocol.io)
- [GitHub MCP Server](https://github.com/github/github-mcp-server)
- [awesome-mcp-servers](https://github.com/punkpeye/awesome-mcp-servers) — Community server list
- [MCPcat](https://mcpcat.io) — MCP server guides and setup
- [Drupal MCP Server](https://www.drupal.org/project/mcp_server) — Drupal integration
