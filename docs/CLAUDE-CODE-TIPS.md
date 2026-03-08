# Claude Code — Tips & Tricks

Practical tips for getting the most out of Claude Code day-to-day — covering CLAUDE.md, CLI habits, token management, and effective prompting.

---

## CLAUDE.md — Your Project's Persistent Memory

`CLAUDE.md` is a Markdown file Claude Code reads automatically at the start of every session. Use it to encode your project's standards, conventions, and preferences so you don't have to re-explain them every time.

### File locations

| File | Scope |
|---|---|
| `CLAUDE.md` (project root) | Project-level — shared with your team via Git |
| `.claude/CLAUDE.md` | Project-level — alternative location |
| `~/.claude/CLAUDE.md` | Global — applies to all projects |

### Example CLAUDE.md content

```markdown
# Project: Symfony Examples Playground

## About this project
A learning playground for Symfony concepts, similar to the Drupal Examples module.
Each example is self-contained with comments linking to official Symfony docs.

## Development environment
- Runs in DDEV. All PHP/Composer/Symfony commands must use `ddev exec` or `ddev console`.
- Never run PHP or Composer directly outside DDEV.

## Code conventions
- Use PHP 8 attributes for routing (#[Route], #[IsGranted], etc.)
- Use AbstractBaseController as base for all example controllers
- All examples must be self-contained and well-commented
- Link to relevant Symfony docs in every example file

## Git workflow
- Always create a feature branch before making changes
- Use descriptive commit messages
```

Commit `CLAUDE.md` to version control — it's a team resource, not just a personal config.

---

## CLI Tips & Tricks

### Use Plan Mode before big changes
Ask Claude to plan before touching any files. Review and redirect before anything is written.
```
> describe your plan for adding authentication — don't make any changes yet
```

### Start tasks with /clear
Fresh context = better results. Don't carry stale conversation history into a new task.
```
/clear
```

### Use /compact to stay in context longer
When context is growing large, compress it without losing continuity.
```
/compact "focus on the current controller refactor"
```

### Use Escape to interrupt, not Ctrl+C
`Escape` stops Claude mid-task but keeps your session alive. `Ctrl+C` cancels the current input or generation. `Ctrl+D` exits the session entirely.

### Commit frequently during complex tasks
More commits = more rollback points. Ask Claude to commit after each logical step.
```
> commit what we have so far
```

### Put your conventions in CLAUDE.md
Anything you find yourself repeating — environment setup, code patterns, what not to do — belongs in `CLAUDE.md`. Claude reads it automatically at the start of every session.

### Use custom slash commands for repeated workflows
Turn frequently-used prompts into slash commands in `.claude/commands/`.

### Use -c to resume your last session
```bash
claude -c   # pick up exactly where you left off
```

---

## Effective Token Usage Tips

Token costs compound quickly — every message resends your entire conversation history. These habits keep usage lean.

### Manage context aggressively

```bash
/clear        # Fresh start — use between unrelated tasks
/compact      # Summarise context — use when sessions run long
/context      # Visualise what's consuming your context window
```

`/clear` between tasks is the single highest-ROI habit. Context from your last bug fix has no business tagging along into your next feature.

### Keep CLAUDE.md lean

Keep your `CLAUDE.md` under 5k tokens. It's loaded on every session start — bloated files waste tokens before you've typed a word. If it grows too large, split secondary content into separate files under `docs/` and reference them only when needed.

```markdown
<!-- In CLAUDE.md — point to supplementary docs, don't inline them -->
## Architecture notes
See docs/architecture.md for full details.
```

### Use Sonnet by default, Opus only when needed

Opus can be ~5x more expensive than Sonnet. Reserve it for tasks that genuinely need deep reasoning.

```bash
/model sonnet   # Default for ~80% of tasks
/model opus     # Complex architecture decisions, deep refactors only
```

For complex planning: temporarily switch to `/model opus`, approve the plan, then switch back to `/model sonnet` for implementation.

### Disable MCP servers you're not using

Every enabled MCP server adds tool definitions to your system prompt. Use `/mcp` to disable servers irrelevant to your current task:

```bash
/context    # Visualise context usage as a coloured grid
/mcp        # Manage MCP server connections (disable servers here)
```

### Use subagents for verbose output

Running tests, fetching docs, or processing logs generates a lot of output. Delegate to subagents so the verbose output stays in their context — only the summary comes back to your main session.

### Keep agent teams small

Agent teams use ~7x more tokens than standard sessions (each teammate has its own full context window). Keep team tasks small and self-contained, and clean up teams when done — idle teammates still consume tokens.

### Monitor your usage

```bash
/cost     # Token usage and cost for the current session
/stats    # Usage patterns over time
```

If context/history is dominating the `/cost` breakdown, it's time to `/compact` or `/clear`.

---

## Effective Prompting Tips

### Be specific
```
# Too vague
> fix the bug

# Much better
> fix the login bug where users see a blank screen after entering wrong credentials in LoginController.php
```

### Break complex tasks into steps
```
> 1. Create a Doctrine entity for BlogPost with title, body, createdAt fields
> 2. Generate a migration for the new entity
> 3. Create a repository with a method to find recent posts
```

### Let Claude explore first
```
> analyze the folder structure of this project
> what Symfony bundles are installed?
> how is routing currently configured?
```
Then give it tasks once it understands the codebase.

### Use Plan Mode for big changes
Before a large refactor or feature addition, ask Claude to plan first:
```
> describe your plan for adding a full authentication example to this project — don't make any changes yet
```
Review the plan, then approve it or redirect before any files are touched.
