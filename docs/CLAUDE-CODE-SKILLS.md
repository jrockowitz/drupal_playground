# CLAUDE-CODE-SKILLS.md: Agent Skills System

Skills are reusable instruction sets that Claude loads **on-demand** when relevant to a task. Each skill lives in a folder with a `SKILL.md` file containing YAML frontmatter and markdown instructions. Claude discovers skills automatically and invokes them without explicit slash commands.

---

## Core Concepts

### Progressive Disclosure

Skills load information in stages to preserve context:

1. **YAML frontmatter** — loaded at startup (lightweight; just name + description)
2. **SKILL.md body** — loaded when Claude determines the skill is relevant
3. **Bundled resources** — scripts, templates, references loaded only as needed

This means you can install many skills with minimal context penalty. Claude only knows each skill exists until it needs the full instructions.

### Skills vs. Slash Commands vs. Subagents

| | Skills | Slash Commands | Subagents |
|---|---|---|---|
| Invocation | Claude auto-invokes | User types `/command` | Claude delegates |
| Context | Runs inline | Runs inline | Separate context window |
| Reusability | Across conversations | Per-session | Per-session |
| Best for | Portable expertise | Explicit user actions | Task isolation |

Skills are portable expertise any agent can apply. Subagents are purpose-built for specific workflows with tool restrictions. They combine well: a subagent can leverage a skill for specialized knowledge.

---

## Skill File Structure

```
skill-name/
├── SKILL.md              # Required — frontmatter + instructions
├── scripts/              # Optional — executable code
│   └── helper.py
├── references/           # Optional — docs loaded into context as needed
│   └── api-spec.md
└── assets/               # Optional — templates, icons, fonts
    └── template.json
```

### SKILL.md Anatomy

```markdown
---
name: my-skill
description: What this skill does and when to use it
---

# Instructions

Claude follows these when the skill is active.

## Examples
- Example usage 1
- Example usage 2
```

### Frontmatter Fields

All fields are optional. Only `description` is recommended.

```yaml
---
name: skill-name                    # Slash command name (if invoked manually)
description: When to use this       # Key for auto-invocation — be specific
disable-model-invocation: true      # Prevent auto-invocation (manual only)
allowed-tools: Read, Grep           # Grant tool access without per-use approval
context: fork                       # Run in a forked subagent
agent: Explore                      # Which subagent to use (Explore, Plan, etc.)
---
```

### Variable Substitution

Skills support dynamic values:

```markdown
---
name: session-logger
description: Log activity for this session
---

Log the following to logs/${CLAUDE_SESSION_ID}.log:
$ARGUMENTS
```

`$ARGUMENTS` captures whatever follows the slash command. `${CLAUDE_SESSION_ID}` is a built-in variable.

---

## Skill Locations

| Location | Scope | Use case |
|---|---|---|
| `~/.claude/skills/` | User (all projects) | Personal workflows |
| `.claude/skills/` | Project (git-tracked) | Team-shared expertise |
| Plugin-installed | Varies | Community/marketplace skills |

---

## Skill Types

### Reference Skills (Inline)

Add knowledge Claude applies to your current work — conventions, patterns, domain knowledge. Runs inline alongside your conversation context.

```markdown
---
name: drupal-conventions
description: Drupal coding standards and patterns. Use when writing Drupal module code, hooks, services, or plugins.
---

When writing Drupal code:

- Follow Drupal coding standards (PSR-12 with Drupal-specific rules)
- Use dependency injection via services, not static calls
- Prefix custom module functions with module name
- Use `\Drupal::service()` only in `.module` files, never in classes
- Place config schema in `config/schema/` for all config entities
- Run `ddev drush cr` after adding new services or plugins
```

### Task Skills (Step-by-Step)

Give Claude specific procedures for actions like deployments, code generation, or analysis.

```markdown
---
name: drupal-module-scaffold
description: Scaffold a new Drupal custom module with proper structure. Use when creating new modules.
---

When scaffolding a Drupal module:

1. Create `web/modules/custom/{module_name}/`
2. Generate `{module_name}.info.yml` with proper metadata
3. Create `{module_name}.module` with file doc block
4. Add `{module_name}.services.yml` if services are needed
5. Create `src/` directory for PSR-4 autoloaded classes
6. Add `config/schema/{module_name}.schema.yml` for any config
7. Run `ddev drush cr` to register the module
8. Verify with `ddev drush pm:list --filter={module_name}`
```

### Forked Skills (Subagent Context)

Run research or heavy analysis in a separate context to keep your main conversation clean.

```markdown
---
name: deep-research
description: Research a topic thoroughly in the codebase
context: fork
agent: Explore
---

Research $ARGUMENTS thoroughly:

1. Find relevant files using Glob and Grep
2. Read and analyze the code
3. Summarize findings with specific file references
```

---

## Writing Effective Descriptions

The `description` field is the primary mechanism determining whether Claude invokes a skill. Be specific and slightly "pushy" — Claude tends to under-trigger skills.

```yaml
# Too vague — won't trigger reliably
description: Helps with Drupal stuff

# Better — specific triggers
description: >
  Drupal migration process plugin reference. Use when writing or
  debugging migration YAML, process plugin chains, source plugins,
  or any migrate_plus configurations. Also use when the user mentions
  migrations, ETL, data import, or content migration.
```

---

## Best Practices

**Keep skills under 5k tokens.** Long skills dilute attention. If a skill exceeds ~50 lines, split it into two focused skills.

**Use imperative language.** Write direct commands: "Always run tests before committing", not "You might want to consider running tests."

**Include examples of correct and incorrect behavior.** Concrete examples reduce ambiguity dramatically.

**Start with a clear trigger condition.** Specify exactly when the skill activates — e.g., "before writing any code" or "when the user asks to deploy."

**Version your skills.** Treat `SKILL.md` like code — commit changes, document rationale, roll back when needed.

**Skills + MCP = powerful combos.** A skill provides expertise and process knowledge; an MCP server provides the capability. Example: a deployment skill that uses a CI/CD MCP server.

---

## Installing Skills

### From Plugin Marketplaces

```bash
# Add a marketplace
/plugin marketplace add anthropics/skills

# Install a specific plugin containing skills
/plugin install document-skills@claude-plugins-official
```

### Manual Installation

```bash
# Create skill directory
mkdir -p ~/.claude/skills/my-skill

# Create SKILL.md
cat > ~/.claude/skills/my-skill/SKILL.md << 'EOF'
---
name: my-skill
description: What it does and when to use it
---

Instructions here...
EOF
```

### From skills.sh

[skills.sh](https://skills.sh) is an open community skills directory with install telemetry that surfaces the most-used skills in the ecosystem. Skills are identified by `owner/repo` slugs (GitHub-style) and installed with the `skills` CLI via `npx`.

```bash
# Install a single skill into the current project
npx skills add vercel-labs/agent-skills

# Install into your global user skills directory
npx skills add --user anthropics/skills

# Search for skills by topic
# Browse https://skills.sh/?q=drupal
```

Skills install into `.claude/skills/` (project) or `~/.claude/skills/` (user) by default. Each `owner/repo` can contain multiple skills; the CLI installs all of them unless you specify a path.

**Noteworthy skills from skills.sh:**

| Skill | Install | Description |
|---|---|---|
| `find-skills` | `npx skills add vercel-labs/skills` | Helps Claude discover relevant skills mid-session |
| `systematic-debugging` | `npx skills add obra/superpowers` | Structured debugging process |
| `test-driven-development` | `npx skills add obra/superpowers` | TDD workflow guidance |
| `skill-creator` | `npx skills add anthropics/skills` | Build and iterate on your own skills |
| `mcp-builder` | `npx skills add anthropics/skills` | Scaffold MCP servers |
| `webapp-testing` | `npx skills add anthropics/skills` | End-to-end testing patterns |

> **Security note:** skills.sh runs routine audits, but review a skill's source before installing. Skills execute in your agent's context and can influence its behavior significantly.

### From Claude.ai

Pre-built skills work automatically for document creation (docx, pdf, pptx, xlsx). Custom skills can be uploaded as zip files through Settings → Features.

---

## Recommended Drupal Skills

Three skill collections on [skills.sh](https://skills.sh/?q=drupal) stand out for general Drupal + Claude Code development.

### `omedia/drupal-skill` — Frontend, backend, and tooling

[skills.sh/omedia/drupal-skill](https://skills.sh/omedia/drupal-skill) · [GitHub](https://github.com/omedia/drupal-skill)

```bash
npx skills add omedia/drupal-skill
```

- `drupal-frontend` — Theming, Twig templates, preprocess functions, theme structure
- `drupal-backend` — Hooks, module structure, custom entities, block plugins, form alterations
- `drupal-tooling` — DDEV setup, Drush workflows, database operations, config export

### `grasmash/drupal-claude-skills` — Comprehensive reference collection

[skills.sh/grasmash/drupal-claude-skills](https://skills.sh/grasmash/drupal-claude-skills) · [GitHub](https://github.com/grasmash/drupal-claude-skills)

A deep collection drawing from [drupalatyourfingertips.com](https://drupalatyourfingertips.com) by Selwyn Polit, Ivan Grynenko's OWASP cursor rules, and original authoring.

```bash
npx skills add grasmash/drupal-claude-skills
```

- `drupal-at-your-fingertips` — 50+ topics: core APIs, entities, forms, routing, theming, caching, testing
- `drupal-ddev` — Complete `.ddev/config.yaml` reference, Xdebug, Mutagen, custom hooks
- `drupal-config-mgmt` — Safe config inspection/sync, avoiding accidental imports, remote Drush safety flags
- `drupal-contrib-mgmt` — Composer module updates, D11 compatibility, patch management
- `ivangrynenko-cursorrules-drupal` — OWASP Top 10 security patterns: access control, XSS/SQLi prevention, SSRF

### `kanopi/cms-cultivator` — CMS development toolkit

[skills.sh/kanopi/cms-cultivator](https://skills.sh/kanopi/cms-cultivator) · [GitHub](https://github.com/kanopi/cms-cultivator)

A broad CMS development toolkit from [Kanopi Studios](https://kanopi.com). Most skills are general-purpose (code quality, testing, accessibility, performance), with two Drupal.org-specific skills for contribution workflows.

```bash
npx skills add kanopi/cms-cultivator
```

- `responsive-styling` — Responsive CSS and styling patterns
- `drupalorg-issue-helper` — Issue management on Drupal.org
- `design-analyzer` — Design analysis and review
- `code-standards-checker` — Code standards validation
- `test-scaffolding` — Test setup and scaffolding patterns
- `performance-analyzer` — Performance analysis and optimization
- `drupalorg-contribution-helper` — Contribution workflow for Drupal.org
- `security-scanner` — Security scanning patterns
- `documentation-generator` — Documentation generation
- `accessibility-checker` — Accessibility auditing
- `coverage-analyzer` — Test coverage analysis
- `test-plan-generator` — Test plan creation
- `commit-message-generator` — Conventional commit message generation
- `browser-validator` — Cross-browser validation

---

## Resources

- [Claude Code Skills Docs](https://code.claude.com/docs/en/skills)
- [Agent Skills Overview](https://platform.claude.com/docs/en/agents-and-tools/agent-skills/overview)
- [anthropics/skills](https://github.com/anthropics/skills) — Official skill examples
- [awesome-claude-skills](https://github.com/travisvn/awesome-claude-skills) — Curated community list
- [SkillsMP](https://skillsmp.com) — Marketplace with 400k+ skills
- [Skills Explained Blog](https://claude.com/blog/skills-explained) — How skills fit in the Claude ecosystem
- <https://skills.sh/?q=drupal>
