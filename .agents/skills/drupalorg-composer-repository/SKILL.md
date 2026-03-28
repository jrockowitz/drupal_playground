---
name: drupalorg-composer-repository
description: Clones any Drupal.org project (module, theme, recipe) as a git repository into composer.json or composer.sandbox.json for local contribution work. Fetches git instructions from Drupal.org to determine the correct branch, follows the local repo's Composer conventions, and swaps packaged releases to git-backed dev branches when needed.
---

# Drupal.org Composer Repository Skill

Set up a Drupal.org project (module, theme, or recipe) as a Composer `type: package` repository sourced from git so local work uses a live Drupal.org branch instead of a packaged release.

## When to Use This Skill

Activate when the user:
- Says "clone for local dev", "work off a branch", or "add drupal.org git repo"
- Says "contribute to a module/theme/recipe"
- Says "Clone {name} from Drupal.org"
- Wants to swap a packaged release for a git checkout of a Drupal.org project

## Workflow

### 1. Gather project details

Infer from context when possible:
- **Project machine name** — for example `webform`, `token`, `gin`
- **Project type** — module, theme, or recipe; default to module only if the type is not discoverable
- **Branch** — use the user-provided branch if present; otherwise fetch the recommended branch

### 2. Fetch Drupal.org git instructions

Prefer official Drupal tooling and metadata:
- If `drupalorg-cli` is available, use it or other official Drupal metadata first
- Otherwise fetch `https://www.drupal.org/project/{project_name}/git-instructions`

Extract:
- **SSH clone URL** — for example `git@git.drupal.org:project/webform.git`
- **Recommended branch** — for example `6.3.x`

Derive the Composer version from the branch:
- `6.3.x` -> `6.3.x-dev`

### 3. Follow local Composer conventions before editing

Inspect the local project before deciding where to write:
- Read `composer.json`
- Check whether `wikimedia/composer-merge-plugin` is required
- Check whether `composer.sandbox.json` already exists
- Check whether `composer.sandbox.json` is tracked or ignored by the repo
- Check whether the needed installer path already exists

Use the existing repo convention instead of imposing a generic one:
- If the repo already uses `composer.sandbox.json`, update it in place
- If merge-plugin is present but `composer.sandbox.json` does not exist, create it and add it to `extra.merge-plugin.include` if needed
- If merge-plugin is absent, update `composer.json` directly

Do not assume `composer.sandbox.json` should be gitignored. Respect the project's existing policy.

### 4. Determine package type and install path

Use sandbox types for git-based local contribution checkouts so they stay separate from managed contrib code.

| Project kind | Composer type | Install path |
|---|---|---|
| Module | `drupal-module-sandbox` | `web/modules/sandbox/{$name}` |
| Theme | `drupal-theme-sandbox` | `web/themes/sandbox/{$name}` |
| Recipe | `drupal-recipe` | `recipes/{$name}` |

The Composer package name is `drupal/{project_name}`.

### 5. Verify installer paths

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

### 6. Update Composer metadata without duplicates

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
- If editing `composer.json` directly, place the package repository before `packages.drupal.org` so it wins resolution

Require rules:
- Require the explicit branch-derived dev version, not `*`
- Use `"drupal/{project_name}": "{branch}-dev"`
- If the package is already in `require`, update the constraint instead of creating a duplicate

### 7. Install or update through DDEV

Prefer DDEV Composer commands in this project:

```bash
# New package
ddev composer require drupal/{project_name}:{branch}-dev

# Existing package already present
ddev composer update drupal/{project_name} --with-all-dependencies

# Clear Drupal caches
ddev drush cr
```

Use the update path when switching an existing packaged release to a git-backed branch.

### 8. Verify the result

Confirm all of the following:
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
        "drupal/webform": "6.3.x-dev"
    }
}
```

Install with:

```bash
ddev composer require drupal/webform:6.3.x-dev
```

Module installs at `web/modules/sandbox/webform/`.

## Notes

- Always prefer SSH: `git@git.drupal.org:project/{project_name}.git`
- If the user provides a specific branch, use it instead of the recommended one
- If Composer cannot access SSH from the container, have the user run `ddev auth ssh` and retry
- HTTPS is acceptable only when the user explicitly wants read-only access and does not intend to push to Drupal.org
