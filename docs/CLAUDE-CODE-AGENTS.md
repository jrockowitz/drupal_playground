# CLAUDE-CODE-AGENTS.md: Subagents & Multi-Agent Orchestration

Claude Code can spawn specialized AI assistants called **subagents** that handle focused tasks in their own context windows. This keeps exploration and implementation out of your main conversation, preserves context, and enforces tool restrictions.

---

## Core Concepts

Subagents are separate agent instances with their own system prompt, tool access, and independent permissions. When Claude encounters a task matching a subagent's description, it delegates automatically and returns the result. Subagents cannot spawn other subagents (no infinite nesting).

Key benefits: context isolation (exploration stays out of your main conversation), tool restrictions (read-only agents can't accidentally modify files), and reusable configurations across projects.

---

## Built-in Subagents

Claude Code ships with several subagents it invokes automatically:

### Explore

A fast, read-only agent for searching and analyzing codebases. Claude delegates here when it needs to search or understand code without making changes. Supports thoroughness levels: `quick`, `medium`, `very thorough`.

### Plan

A research agent used during plan mode to gather codebase context before presenting a plan. Invoked when you're in plan mode and Claude needs to understand your code.

### General-Purpose

A capable agent for complex, multi-step tasks requiring both exploration and action. Handles tasks needing code modifications, complex reasoning, or multiple dependent steps.

### Helper Agents

Additional task-specific agents (e.g., for file operations) invoked automatically — you don't need to use these directly.

---

## Creating Custom Subagents

Subagents are defined as Markdown files with YAML frontmatter. Store them in:

| Location | Scope |
|---|---|
| `~/.claude/agents/` | Available across all projects |
| `.claude/agents/` | Project-specific (commit to git for team sharing) |

### Using the /agents Command

```bash
# Interactive agent creation
/agents
# Select "Create new agent"
# Choose scope: User-level or Project-level
# Describe the agent's purpose
# Configure tool access
```

### Manual Creation

Create a file like `.claude/agents/code-reviewer.md`:

```markdown
---
name: code-reviewer
description: Reviews code and suggests improvements for readability, performance, and best practices. Use when reviewing code changes, PRs, or refactoring.
tools: Read, Grep, Glob
model: sonnet
---

You are a code review specialist. For each file:

1. Check for readability issues
2. Identify performance concerns
3. Flag security problems
4. Suggest concrete improvements with code examples

Always explain the reasoning behind each suggestion.
```

### Frontmatter Fields

```yaml
---
name: agent-name              # Becomes the invocable name
description: When to use      # Claude uses this to decide delegation
tools: Read, Write, Bash      # Tool access (comma-separated)
model: sonnet                 # Model routing: sonnet, opus, haiku
---
```

Tool options: `Read`, `Write`, `Edit`, `Bash`, `Glob`, `Grep`. For read-only agents, limit to `Read`, `Grep`, `Glob`.

---

## Drupal-Specific Agent Examples

### Migration Reviewer

```markdown
---
name: migration-reviewer
description: Reviews Drupal migration YAML and process plugins. Use when working on migrate configurations or debugging migration issues.
tools: Read, Grep, Glob
model: sonnet
---

Review Drupal migration configurations for:

1. Source plugin configuration and field mappings
2. Process plugin chain correctness (order, dependencies)
3. Destination plugin and entity type validation
4. Missing migration_dependencies
5. Multi-value field handling with sub_process

Check against Drupal migrate API conventions. Flag deprecated plugins.
```

### Config Auditor

```markdown
---
name: config-auditor
description: Audits Drupal configuration for consistency, missing dependencies, and environment-specific overrides. Use when reviewing config exports or config split setups.
tools: Read, Grep, Glob
model: sonnet
---

Audit Drupal config files in config/sync/ for:

1. Missing module dependencies in core.extension.yml
2. Config split placement (dev vs. staging vs. prod)
3. Language negotiation and translation settings
4. Orphaned config from uninstalled modules
5. Sensitive values that should use environment variables

Report findings grouped by severity.
```

---

## Agent Teams (Experimental)

Agent teams coordinate multiple agents working in **parallel across separate sessions**, unlike subagents which work within a single session.

```bash
# Start an agent team session
# Claude spawns specialized teammates for parallel work
```

Key differences from subagents:

| | Subagents | Agent Teams |
|---|---|---|
| Context | Shares parent session | Separate sessions |
| Communication | Returns result to parent | Shared task board |
| Parallelism | Sequential | True parallel |
| Token cost | ~1x overhead | ~7x overhead |

### Token Considerations

Agent teams use approximately 7x more tokens than standard sessions. Keep team tasks small and self-contained. Clean up teams when work is done — active teammates consume tokens even when idle.

---

## Invocation Patterns

Claude auto-delegates based on the `description` field. You can also invoke explicitly:

```
# Automatic — Claude decides
Review the authentication module for security issues

# Explicit — you choose
Have the code-reviewer agent analyze my latest commits

# Via command
/agents
```

### Context Passing

The only channel from parent to subagent is the prompt string. Include file paths, error messages, and any decisions the subagent needs directly in your request.

---

## Best Practices

**Limit to 3–4 custom subagents.** More than that and you spend too much time deciding which to invoke. For most work, stick with stock Claude Code.

**Reserve subagents for senior-level tasks:** architecture reviews, security audits, complex debugging, migration validation.

**Use clear descriptions.** The `description` field determines whether Claude invokes the agent. Be specific about when it should and shouldn't activate.

**Match model to task.** Use `model: haiku` for quick lookups, `model: sonnet` for most work, `model: opus` for complex reasoning.

**Project-level agents belong in version control.** Commit `.claude/agents/` so the whole team benefits.

**Name conflicts:** Project-specific agents override global (user-level) agents with the same name.

---

## Resources

- [Claude Code Subagents Docs](https://code.claude.com/docs/en/sub-agents)
- [Agent SDK Subagents](https://platform.claude.com/docs/en/agent-sdk/subagents)
- [awesome-claude-code-subagents](https://github.com/VoltAgent/awesome-claude-code-subagents) — 100+ community subagents
- [subagents.cc](https://subagents.cc) — Browse and share agents
- [subagents.app](https://subagents.app) — Production-ready agent directory
