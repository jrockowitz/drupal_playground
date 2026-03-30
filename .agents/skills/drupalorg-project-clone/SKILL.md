---
name: drupalorg-project-clone
description: Clone a single Drupal.org project by machine name (module, theme, or recipe) as
  a git repository into composer.json or composer.sandbox.json for local contribution work.
  Use when you already know the project machine name. Fetches Drupal.org git-instructions
  to determine the correct branch, resolves project type before installing, follows the local
  repo's Composer conventions, and swaps packaged releases to git-backed dev branches when needed.
---

# Drupal.org Project Clone Skill

Set up a single Drupal.org project as a Composer `type: package` repository sourced from git so local work uses a live Drupal.org branch instead of a packaged release.

## When to Use This Skill

Activate when the user:
- Says "clone for local dev", "work off a branch", or "add drupal.org git repo"
- Says "contribute to a module/theme/recipe"
- Says "Clone {name} from Drupal.org"
- Wants to swap a packaged release for a git checkout of a Drupal.org project

**Do NOT use** when the user provides a Drupal.org username instead of a project name. Use `drupalorg-projects-clone` to discover their maintained projects first.
[SKILL.md](SKILL.md)
## Workflow

### 1. Gather project details

Infer from context when possible:
- **Project machine name** — for example `webform`, `token`, `gin`
- **Project type** — module, theme, or recipe; use a user-provided or caller-provided value if present
- **Branch** — use the user-provided branch if present; otherwise fetch the recommended branch

If only a display name is provided, first resolve it to the Drupal.org project machine name.

### 2. Fetch Drupal.org metadata from git-instructions

Fetch `https://www.drupal.org/project/{project_name}/git-instructions`.

This page is the authoritative source for:
- **SSH clone URL** — for example `git@git.drupal.org:project/webform.git`
- **Recommended branch** — for example `6.3.x`

If the user supplied a branch override, keep it. Otherwise use the recommended branch from `git-instructions`.

Derive the Composer version from the branch:
- `6.3.x` -> `6.3.x-dev`

### 3. Resolve project type before choosing install path

If the project type was already provided by the caller or user, trust it.

Otherwise inspect Drupal.org page metadata and `git-instructions` context to resolve one of:
- **Module**
- **Theme**
- **Recipe**

Do not guess early. Default to module only if the Drupal.org source truly does not expose the type.

### 4. Follow local Composer conventions before editing

Inspect the local project before deciding where to write:
- Read `composer.json`
- Check whether `wikimedia/composer-merge-plugin` is required
- Check whether `composer.sandbox.json` already exists
- Check whether `composer.sandbox.json` is tracked or ignored by the repo
- Check whether the needed installer path already exists

Use the existing repo convention instead of imposing a generic one:
- If the repo already uses `composer.sandbox.json`, treat it as the dependency owner and update it in place
- If merge-plugin is present but `composer.sandbox.json` does not exist, create it and add it to `extra.merge-plugin.include` if needed
- If merge-plugin is absent, update `composer.json` directly

Do not assume `composer.sandbox.json` should be gitignored. Respect the project's existing policy.

Make one explicit ownership decision before editing:
- **Sandbox owner** — use `composer.sandbox.json` for both `repositories` and `require`
- **Root owner** — use root `composer.json` for both `repositories` and `require`

Never split ownership across both files for the same package.

### 5. Determine package type and install path

Use sandbox types for git-based local contribution checkouts so they stay separate from managed contrib code.

| Project kind | Composer type | Install path |
|---|---|---|
| Module | `drupal-module-sandbox` | `web/modules/sandbox/{$name}` |
| Theme | `drupal-theme-sandbox` | `web/themes/sandbox/{$name}` |
| Recipe | `drupal-recipe` | `recipes/{$name}` |

The Composer package name is `drupal/{project_name}`.

### 6. Verify installer paths

Read `composer.json` and ensure `extra.installer-paths` supports the selected package type.

For modules:
```json
"web/modules/sandbox/{$name}": [
    "type:drupal-module-sandbox"
]
```

For themes:
```json
"web/themes/sandbox/{$name}": [
    "type:drupal-theme-sandbox"
]
```

For recipes, verify the existing `drupal-recipe` installer path is present.

### 7. Update Composer metadata without duplicates

Add or update a single repository entry for the package:

```json
{
    "type": "package",
    "package": {
        "name": "drupal/{project_name}",
        "type": "drupal-module-sandbox",
        "version": "{branch}-dev",
        "source": {
            "url": "git@git.drupal.org:project/{project_name}.git",
            "type": "git",
            "reference": "{branch}"
        }
    }
}
```

Adjust `type` for themes or recipes.

Repository rules:
- If a `repositories` entry for `drupal/{project_name}` already exists, update it instead of appending another one
- If editing root `composer.json`, place the package repository before `packages.drupal.org` so it wins resolution
- If editing `composer.sandbox.json`, keep `repositories` sorted alphabetically by `package.name`

Require rules:
- Always use `"*"` as the version constraint in `require`, never a specific branch-derived version like `1.0.x-dev`
- Use `"drupal/{project_name}": "*"`
- If the package is already in `require`, update the constraint instead of creating a duplicate
- Keep all entries in `require` in alphabetical order by package name

### 8. Install or update through DDEV

Prefer DDEV Composer commands in this project:

```bash
# If the chosen manifest owner is composer.sandbox.json,
# update that file first, then resolve/install from Composer.
ddev composer update drupal/{project_name} --with-all-dependencies

# If the chosen manifest owner is root composer.json and the package is new,
# add it through Composer instead of manual require edits.
ddev composer require drupal/{project_name}:*

# If the chosen manifest owner is root composer.json and the package already exists,
# update it in place.
ddev composer update drupal/{project_name} --with-all-dependencies

# Clear Drupal caches after Composer succeeds.
ddev drush cr
```

Command rules:
- **Sandbox owner** — edit `composer.sandbox.json`, then run `ddev composer update drupal/{project_name} --with-all-dependencies`
- **Root owner, new package** — run `ddev composer require drupal/{project_name}:*`
- **Root owner, existing package** — run `ddev composer update drupal/{project_name} --with-all-dependencies`

Do not run `ddev composer require` after manually editing `composer.sandbox.json`, because that would mutate the wrong manifest in repositories that use sandbox ownership.

Use the update path when switching an existing packaged release to a git-backed branch.

### 9. Verify the result

Confirm all of the following:
- The chosen manifest file contains the package in both `repositories` and `require`
- The package installed to the expected path
- `composer.lock` or Composer output shows `drupal/{project_name}` resolved to `{branch}-dev`
- The locked source points at the Drupal.org git URL and expected branch/reference

Expected paths:
- Module: `web/modules/sandbox/{project_name}/`
- Theme: `web/themes/sandbox/{project_name}/`
- Recipe: `recipes/{project_name}/`

## Worked Example: webform module

Drupal.org git instructions for `webform` yield:
- SSH URL: `git@git.drupal.org:project/webform.git`
- Recommended branch: `6.3.x`
- Version constraint: `6.3.x-dev`
- Composer type: `drupal-module-sandbox`

Example `composer.sandbox.json`:

```json
{
    "repositories": [
        {
            "type": "package",
            "package": {
                "name": "drupal/webform",
                "type": "drupal-module-sandbox",
                "version": "6.3.x-dev",
                "source": {
                    "url": "git@git.drupal.org:project/webform.git",
                    "type": "git",
                    "reference": "6.3.x"
                }
            }
        }
    ],
    "require": {
        "drupal/webform": "*"
    }
}
```

Install with:

```bash
ddev composer update drupal/webform --with-all-dependencies
```

Module installs at `web/modules/sandbox/webform/`.

## Notes

- Always prefer SSH: `git@git.drupal.org:project/{project_name}.git`
- Accept a caller-provided project type when a parent workflow has already resolved it
- If the user provides a specific branch, use it instead of the recommended one
- If Composer cannot access SSH from the container, have the user run `ddev auth ssh` and retry
- HTTPS is acceptable only when the user explicitly wants read-only access and does not intend to push to Drupal.org
