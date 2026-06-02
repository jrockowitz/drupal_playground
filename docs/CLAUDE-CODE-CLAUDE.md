# CLAUDE-CODE-CLAUDE.md: Writing & Maintaining CLAUDE.md Files

CLAUDE.md is a markdown file that Claude Code reads at the start of every session. It gives Claude persistent context about your project — build commands, coding conventions, architecture decisions, gotchas — so you stop repeating yourself. This is the single most impactful configuration for Claude Code.

---

## Getting Started with /init

The `/init` command analyzes your codebase and generates a starter CLAUDE.md. It reads `package*.json`, existing documentation, config files, code structure, and any existing instruction files (`.cursorrules`, `.github/copilot-instructions.md`) to produce a tailored starting point.

```bash
# Inside a Claude Code session
/init

# Add notes to CLAUDE.md,
# Keep comments short and concise.
```

### What /init Does

1. Scans your project files and directory structure
2. Detects build systems, test frameworks, and code patterns
3. Reads existing README.md, configuration files, and instruction files
4. Generates a CLAUDE.md with detected commands, conventions, and architecture
5. If a CLAUDE.md already exists, suggests improvements instead

### The Critical Next Step: Edit What It Generates

Treat `/init` output as a **starting point, not a finished product**. The generated file captures obvious patterns but often includes filler. Review it immediately and:

**Delete generics.** Remove anything Claude could infer from the code itself — obvious instructions like "provide helpful error messages" or "write unit tests for new utilities." If removing a line wouldn't cause Claude to make a mistake, cut it.

**Delete boilerplate.** Don't list every component or file that can be easily discovered. Don't include generic development practices. Don't keep made-up sections like "Tips for Development" or "Support and Documentation" unless they actually exist in your project.

**Add what /init missed.** Workflow instructions Claude couldn't infer: branch naming conventions, deployment processes, code review requirements, DDEV-prefixed commands, environment-specific quirks.

**Correct wrong assumptions.** `/init` may misidentify architecture patterns, especially in less conventional codebases. Always verify.

> Deleting is easier than creating from scratch. Start with `/init`, then cut ruthlessly.

---

## Memory Hierarchy

Claude Code doesn't read a single CLAUDE.md — it assembles a layered hierarchy, from broadest to most specific. More specific instructions override broader ones.

### File Locations

| Location | Scope | Loaded | Shared? |
|---|---|---|---|
| `~/.claude/CLAUDE.md` | Global (all projects) | At launch | No |
| `./CLAUDE.md` | Project root | At launch | Yes (commit to git) |
| `./CLAUDE.local.md` | Project (personal) | At launch | No (.gitignore it) |
| `./subdir/CLAUDE.md` | Subdirectory | On demand | Yes |
| `.claude/rules/*.md` | Modular rules | On demand | Yes |
| Auto memory | Session learnings | At launch | No |

### Loading Behavior

- Files **above** the working directory: loaded in full at launch
- Files **below** (subdirectories): loaded on demand when Claude reads files in that directory
- CLAUDE.md **survives compaction** — after `/compact`, Claude re-reads it from disk and re-injects it fresh

### Precedence

When instructions conflict across levels, the most specific wins:

```
Managed policy (enterprise) → Global (~/) → Project (./CLAUDE.md) → Local (.local.md) → Subdirectory → Rules
```

If your global file says "use 2-space indentation" and your project file says "use 4-space indentation," the project instruction wins.

---

## What Belongs in CLAUDE.md

Every line competes for attention with the actual work. Ask yourself: "Would removing this cause Claude to make a mistake?" If not, cut it.

### Essential Sections

**Project context** — One line orienting Claude:

```markdown
# Drupal 11 CMS site running on DDEV with multilingual config split
```

**Commands** — Exact commands Claude should use. This is critical for DDEV projects where all commands must be prefixed:

```markdown
## Commands
ddev start                      # Start environment
ddev drush cr                   # Clear cache
ddev drush cex -y               # Export config
ddev drush cim -y               # Import config
ddev composer require drupal/x  # Add a module
ddev drush updb -y              # Run database updates
ddev phpcs web/modules/custom   # Code standards check
```

**Code style** — Be specific and actionable, not vague:

```markdown
## Code Style
- Follow Drupal coding standards (PSR-12 with Drupal-specific rules)
- Use dependency injection via services, never static \Drupal:: calls in classes
- Only use \Drupal::service() in .module files and hooks
- Prefix all custom module functions with the module name
- Type-hint all function parameters and return types
```

**Architecture** — High-level patterns that require reading multiple files to understand:

```markdown
## Architecture
- Custom modules in web/modules/custom/
- Config sync in config/sync/, splits in config/splits/{env}/
- Theme: web/themes/custom/my_theme (Twig + Tailwind)
- Translations managed via Interface Translation, not config
```

**Gotchas** — Project-specific warnings that save time:

```markdown
## Gotchas
- NEVER modify config/sync/ files manually — always export with drush cex
- The admin theme forces English via custom language negotiation plugin
- Webform submissions use a separate database table, not entity storage
- Always run ddev drush cr after adding new services or route subscribers
```

### Optional But Useful Sections

**Workflows** — When Claude repeatedly misses steps:

```markdown
## Workflows
### Adding a New Content Type
1. Create via config export, not UI (ddev drush cex after UI changes)
2. Add view mode displays for full, teaser, and search_result
3. Create config split overrides if field visibility differs by environment
4. Update search_api index if content should be searchable
```

**Business terms** — If you keep redefining terms in prompts:

```markdown
## Terminology
- "Modernize" = the migration from legacy Drupal 7 to Drupal 11
- "Provider" = an entity reference to Organization content type
- "Alert" = a taxonomy-driven notification banner, not a JS alert
```

---

## Modular Rules (.claude/rules/)

For large projects, break instructions into topic-specific files instead of bloating a single CLAUDE.md:

```
.claude/
└── rules/
    ├── frontend.md       # Rules for web/themes/**
    ├── migrations.md     # Rules for migration YAML
    ├── testing.md        # PHPUnit conventions
    └── api.md            # REST/JSON:API patterns
```

Rules files are loaded on demand based on which files Claude is working with. This keeps context lean — migration rules don't load when you're editing theme templates.

Rules can use glob patterns to target specific paths:

```markdown
# .claude/rules/migrations.md
# Applies when working with migration files

When writing migration YAML:
- Always include migration_dependencies
- Use sub_process for multi-value fields
- Prefer migrate_plus process plugins over custom code
- Test with ddev drush migrate:import --limit=5 before full runs
```

### Branch-Specific Rules

Add temporary rules on feature branches without touching the main CLAUDE.md:

```bash
# On a migration branch
echo "- All source plugins must validate connection before processing" > .claude/rules/migration-branch.md
git add .claude/rules/migration-branch.md
```

This avoids merge conflicts on CLAUDE.md while adapting behavior to branch context.

---

## Auto Memory

Claude accumulates knowledge across sessions automatically — build commands, debugging insights, architecture notes, code style preferences. It decides what's worth saving based on whether the information would be useful in a future conversation.

Auto memory is **on by default**. Manage it with:

```bash
# Toggle auto memory and browse saved notes
/memory

# Auto memory files are plain markdown you can read, edit, or delete
```

### When Auto Memory Shines

Tell Claude something once and it remembers:

```
> "Always use ddev drush cr, not plain drush cr"
# Claude saves this — you never need to repeat it
```

### Auto Memory vs. CLAUDE.md

| | CLAUDE.md | Auto Memory |
|---|---|---|
| Who writes it | You | Claude |
| When it loads | Every session | Every session |
| Shared with team | Yes (if committed) | No (personal) |
| Best for | Conventions, commands, architecture | Corrections, discoveries, quirks |

Use both: CLAUDE.md for team-shared knowledge, auto memory for personal corrections.

---

## Sizing and Performance

### Target Size

Aim for **50–100 lines** in the root CLAUDE.md. The effective range is 30–200 lines. Beyond 200 lines, signal-to-noise drops and context consumption increases noticeably.

CLAUDE.md is loaded into the system prompt of every session. Bloated files waste tokens before you've typed a word.

### Keeping It Lean

**Split, don't stuff.** Move detailed content into separate files:

```markdown
## Architecture
See docs/architecture.md for full details.
```

Claude reads referenced files on demand via `@path` imports, keeping the root file slim.

**Bullet points over paragraphs.** Concise directives are more reliably followed than prose explanations.

**No redundancy.** Duplicate lines add no value and burn context.

---

## Tips and Tricks

### Run /init on Existing Projects

If you already have a CLAUDE.md, `/init` reviews it and suggests improvements based on what it learns from re-exploring your codebase. Useful after major refactors.

### Evolve CLAUDE.md From Real Usage

When Claude makes an assumption you want to correct, don't just fix the output — tell it to update CLAUDE.md:

```
> "Always use early returns in PHP, not nested if/else. Add this to CLAUDE.md."
```

This turns one-off corrections into permanent instructions.

### Use /memory for Inspection

```bash
/memory
# Browse auto memory folder to see what Claude has saved
# Edit or delete entries as needed
```

### CLAUDE.md for Monorepos

Place a root CLAUDE.md with global conventions, then subdirectory files for each package:

```
monorepo/
├── CLAUDE.md                    # Global: monorepo tooling, CI
├── packages/
│   ├── frontend/
│   │   └── CLAUDE.md            # React/Tailwind conventions
│   └── backend/
│       └── CLAUDE.md            # Drupal/PHP conventions
```

Subdirectory files load only when Claude works in that part of the tree.

### CLAUDE.local.md for Personal Overrides

For personal preferences that shouldn't be committed:

```bash
echo "CLAUDE.local.md" >> .gitignore
```

```markdown
# CLAUDE.local.md
- I prefer verbose drush output: always add -v flag
- Skip asking for confirmation on cache clears
- My local DDEV uses port 8443 for HTTPS
```

### Re-read Mid-Session

If you edit CLAUDE.md during a session, reference it explicitly to trigger a re-read:

```
> @CLAUDE.md
```

### Case Sensitivity

The filename must be exactly `CLAUDE.md` — uppercase CLAUDE, lowercase `.md`. Claude Code looks for this specific filename.

---

## Drupal/DDEV CLAUDE.md Template

A starter template for Drupal projects on DDEV:

```markdown
# CLAUDE.md

Drupal 11 site running on DDEV.

## Commands
ddev start                        # Start environment
ddev stop                         # Stop environment
ddev drush cr                     # Clear cache (run after most changes)
ddev drush cex -y                 # Export config
ddev drush cim -y                 # Import config
ddev drush updb -y                # Run database updates
ddev composer require drupal/x    # Add a module
ddev composer update drupal/x     # Update a module
ddev drush en module_name         # Enable a module
ddev drush pmu module_name        # Uninstall a module
ddev phpcs --standard=Drupal,DrupalPractice web/modules/custom/
ddev phpcbf --standard=Drupal,DrupalPractice web/modules/custom/

## Architecture
- Custom modules: web/modules/custom/
- Custom theme: web/themes/custom/THEME_NAME/
- Config sync: config/sync/
- Composer patches: defined in composer.json extra.patches

## Code Style
- Drupal coding standards (PSR-12 + Drupal-specific)
- Dependency injection via services in classes
- \Drupal::service() only in .module files
- Type-hint all parameters and return types
- Doc blocks on all public methods

## Gotchas
- All CLI commands must be prefixed with `ddev` (not bare drush/composer)
- Run `ddev drush cr` after adding services, plugins, or route subscribers
- Never edit config/sync/ files directly — export with drush cex
- Composer install runs inside the DDEV container automatically
```

---

## Resources

- [Official CLAUDE.md Guide](https://claude.com/blog/using-claude-md-files) — Anthropic's guide
- [Claude Code Memory Docs](https://code.claude.com/docs/en/memory) — File locations, auto memory, rules
- [How to Write a Good CLAUDE.md](https://www.builder.io/blog/claude-md-guide) — Practical walkthrough
- [Creating the Perfect CLAUDE.md](https://dometrain.com/blog/creating-the-perfect-claudemd-for-claude-code/) — Section-by-section breakdown
- [Build Your Own /init](https://kau.sh/blog/build-ai-init-command/) — Reverse-engineered /init prompt
- [SFEIR Memory System Tips](https://institute.sfeir.com/en/claude-code/claude-code-memory-system-claude-md/tips/) — 20 practical tips
