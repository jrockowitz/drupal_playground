# Claude Code — CLAUDE.md, Plugins & Skills

How to customize and extend Claude Code with project memory (CLAUDE.md and custom commands), plugins (community/official add-ons), and skills (task-specific knowledge).

---

## CLAUDE.md & Custom Slash Commands

`CLAUDE.md` is the foundation of project customization — it provides persistent memory that Claude Code reads at the start of every session. Custom slash commands extend this by turning prompt templates into reusable commands.

### Custom slash commands

Custom slash commands are lightweight prompt templates stored as Markdown files. The filename becomes the command name.

**Locations:**
- `.claude/commands/` — project-level (share via Git)
- `~/.claude/commands/` — global (available in all projects)

#### Example: `.claude/commands/example.md`
```markdown
Create a new Symfony example controller for: $ARGUMENTS

Follow these steps:
1. Create a controller in src/Controller/ with appropriate naming
2. Add PHP 8 attribute routes
3. Create a matching Twig template in templates/examples/
4. Add comments linking to Symfony docs
5. Register the example in HomeController.php
6. Follow the conventions in CLAUDE.md
```

Usage: `/example RoutingController with parameter examples`

---

## Plugins

Plugins extend Claude Code with domain-specific knowledge, tools, and slash commands beyond what's built in. They are distributed through plugin marketplaces.

### Basic workflow

```
# Step 1 — add a marketplace (only needed once)
/plugin marketplace add anthropics/claude-code

# Step 2 — install a plugin from that marketplace
/plugin install frontend-design@claude-code-plugins

# List installed plugins
/plugin list

# Remove a plugin
/plugin remove frontend-design
```

### Managing marketplaces

```
# Add a marketplace
/plugin marketplace add <owner/repo>

# List configured marketplaces
/plugin marketplace list

# Remove a marketplace
/plugin marketplace remove <owner/repo>
```

### Official plugins (`claude-plugins-official`)

Pre-installed marketplace — browse with `/plugin` > Discover, or install directly.

**Code intelligence (LSP)** — gives Claude live type errors, jump-to-definition, find-references, and more. Requires the language server binary to be installed on your system.

| Plugin | Language |
|---|---|
| `clangd-lsp` | C / C++ |
| `csharp-lsp` | C# |
| `gopls-lsp` | Go |
| `jdtls-lsp` | Java |
| `kotlin-lsp` | Kotlin |
| `lua-lsp` | Lua |
| `php-lsp` | PHP |
| `pyright-lsp` | Python |
| `rust-analyzer-lsp` | Rust |
| `swift-lsp` | Swift |
| `typescript-lsp` | TypeScript |

**External integrations** — connect Claude to services without manual MCP setup.

| Plugin | Service |
|---|---|
| `github` | GitHub |
| `gitlab` | GitLab |
| `atlassian` | Jira / Confluence |
| `asana` | Asana |
| `linear` | Linear |
| `notion` | Notion |
| `figma` | Figma |
| `vercel` | Vercel |
| `firebase` | Firebase |
| `supabase` | Supabase |
| `slack` | Slack |
| `sentry` | Sentry |

**Development workflows**

| Plugin | Description |
|---|---|
| `commit-commands` | Git commit, push, and PR creation workflows |
| `pr-review-toolkit` | Specialised agents for reviewing pull requests |
| `agent-sdk-dev` | Tools for building with the Claude Agent SDK |
| `plugin-dev` | Toolkit for creating your own plugins |

**Output styles**

| Plugin | Description |
|---|---|
| `explanatory-output-style` | Educational insights about implementation choices |
| `learning-output-style` | Interactive learning mode for skill building |

### Demo plugins (`claude-code-plugins`)

Add the demo marketplace first, then install:
```
/plugin marketplace add anthropics/claude-code
/plugin install frontend-design@claude-code-plugins
```

| Plugin | Description |
|---|---|
| `frontend-design` | HTML/CSS layouts, components, and styling |

### Superpowers (`obra/superpowers`)

Superpowers is a community-driven agentic skills framework and software development methodology by Jesse Vincent. It ships as a plugin that bundles a library of skills covering TDD, systematic debugging, brainstorming, collaboration patterns, and implementation planning. Skills activate automatically when Claude encounters a matching task — for example, `brainstorming` activates before writing code to refine ideas through questions and explore alternatives, and `test-driven-development` activates when implementing features.

```
# Register the marketplace
/plugin marketplace add obra/superpowers-marketplace

# Install the plugin
/plugin install superpowers@superpowers-marketplace

# Update skills to latest
/plugin update superpowers
```

Superpowers enforces a brainstorm → plan → implement workflow. It also provides slash commands like `/superpowers:brainstorm`, `/superpowers:write-plan`, and `/superpowers:execute-plan`. The project is open source and accepts community skill contributions.

### How plugins work

Plugins are installed per-project (stored in `.claude/`) or globally (`~/.claude/`). Once installed, a plugin may add:
- New slash commands specific to that domain
- Additional context Claude draws on automatically
- Specialised tools for that workflow

---

## Skills

Skills are curated instruction sets — Markdown files that teach Claude Code how to perform specific tasks with best practices. Unlike plugins (which add tools and slash commands), skills provide domain knowledge and step-by-step guidance that Claude follows when executing tasks.

### How skills work

A skill is a `SKILL.md` file placed in a known directory. When Claude Code encounters a relevant task, it reads the skill file before acting — similar to how a developer reads documentation before starting unfamiliar work.

Skills typically contain:
- Step-by-step instructions for a specific task type
- Best practices and common pitfalls
- Tool-specific configuration and usage patterns
- Templates or patterns to follow

### Skill locations

| Location | Scope |
|---|---|
| `.claude/skills/` | Project-level — shared via Git |
| `~/.claude/skills/` | Global — available in all projects |

### Creating a skill

A skill is just a Markdown file with structured instructions. The filename should describe the task.

#### Example: `.claude/skills/new-example.md`

```markdown
# Skill: Create a New Symfony Example

When creating a new example for the Symfony Playground:

## Steps

1. Create a controller in `src/Controller/` named after the concept
   - Use PHP 8 attributes for routing
   - Extend `AbstractController`
   - Add generous inline comments explaining the concept

2. Create a Twig template in `templates/examples/<category>/`
   - Include a heading and description paragraph
   - Show the example output
   - Link back to the homepage
   - Link to the relevant Symfony documentation

3. Register the example in `HomeController.php`
   - Add the route to the appropriate category array
   - Include a one-line description

4. Verify the example works
   - Run `ddev launch` and navigate to the new route
   - Check that links from the homepage work

## Conventions
- Keep examples self-contained — no dependencies on other examples
- Always link to official Symfony docs
- Use typed properties and return types throughout
```

### Community skills

The community shares skills for common frameworks and tools. Notable examples for Drupal/Symfony developers:

| Skill | Description | Source |
|---|---|---|
| Drupal DDEV Site Setup | Automates Drupal site creation with DDEV | [drupal.org](https://www.drupal.org/project/claude_skill_drupal_ddev_site_setup) |
| Drupal Module | Scaffolds and develops Drupal modules | [GitHub](https://github.com/ablerz/claude-skill-drupal-module) |
| Drupal Claude Skills | Collection of Drupal-specific skills by Grasmash | [GitHub](https://github.com/grasmash/drupal-claude-skills) |

_Poor man's way to install/copy skills from grasmash/drupal-claude-skills._

```json
{
    "scripts": {
        "post-update-cmd": [
            "# Copy skills from grasmash/drupal-claude-skills",
            "[ -d vendor/grasmash/drupal-claude-skills ] || git clone git@github.com:grasmash/drupal-claude-skills.git vendor/grasmash/drupal-claude-skills;",
            "git -C vendor/grasmash/drupal-claude-skills pull;",
            "[ -d .claude ] || mkdir .claude;",
            "[ ! -d .claude/skills ] || rm -rf .claude/skills;",
            "cp -R vendor/grasmash/drupal-claude-skills/.claude/skills .claude/skills"
        ]
    }
}

```
### When to use what

| Mechanism | Purpose | Format | When it loads |
|---|---|---|---|
| `CLAUDE.md` | Project conventions and persistent context | Markdown | Every session start |
| Custom commands | Reusable prompt templates for repeated tasks | Markdown (`.claude/commands/`) | On slash command invocation |
| Skills | Task-specific instructions and best practices | Markdown (`SKILL.md`) | When Claude encounters a matching task |
| Plugins | New tools, slash commands, and integrations | Plugin package | On install, always available |
