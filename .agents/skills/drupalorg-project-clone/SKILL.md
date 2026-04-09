---
name: drupalorg-project-clone
description: Clone a single Drupal.org project by machine name (module, theme, or recipe) as
  a git repository into composer.json or composer.sandbox.json for local contribution work.
  Use when you already know the project machine name. Fetches Drupal.org git-instructions
  to determine the correct branch, resolves project type before installing, follows the local
  repo's Composer conventions, and swaps packaged releases to git-backed dev branches when needed.
---

# Drupal.org Project Clone Skill

Set up a single Drupal.org project as a Composer `type: package` repository sourced from git for local contribution work. If the user provides a username instead of a project name, use `drupalorg-projects-clone` instead.

## Workflow

### 1. Gather project details

Infer from context when possible:
- **Machine name** — e.g. `webform`, `token`, `gin`
- **Project type** — module, theme, or recipe; trust caller-provided value
- **Branch** — use user-provided branch if present; otherwise fetch recommended branch

If only a display name is provided, resolve it to the machine name first.

### 2. Fetch metadata from git-instructions

Fetch `https://www.drupal.org/project/{project_name}/git-instructions`.

Extract:
- **SSH clone URL** — e.g. `git@git.drupal.org:project/webform.git`
- **Recommended branch** — e.g. `6.3.x`

Derive the Composer version: `6.3.x` → `6.3.x-dev`. Keep any user-supplied branch override.

### 3. Resolve project type

Trust caller- or user-provided type. Otherwise inspect Drupal.org page metadata and `git-instructions` to resolve module, theme, or recipe. Default to module only if the type is truly not exposed.

### 4. Follow local Composer conventions

Read `composer.json` and check:
- Whether `wikimedia/composer-merge-plugin` is required
- Whether `composer.sandbox.json` exists and is tracked/ignored

Make one explicit ownership decision before editing:
- **Sandbox owner** — use `composer.sandbox.json` for both `repositories` and `require`
- **Root owner** — use root `composer.json` for both

Never split ownership across files for the same package.

### 5. Determine package type and install path

| Project kind | Composer type | Install path |
|---|---|---|
| Module | `drupal-module-sandbox` | `web/modules/sandbox/{$name}` |
| Theme | `drupal-theme-sandbox` | `web/themes/sandbox/{$name}` |
| Recipe | `drupal-recipe` | `recipes/{$name}` |

Package name is `drupal/{project_name}`.

### 6. Verify installer paths

Ensure `extra.installer-paths` in `composer.json` supports the selected type:

```json
"web/modules/sandbox/{$name}": ["type:drupal-module-sandbox"]
"web/themes/sandbox/{$name}": ["type:drupal-theme-sandbox"]
```

Verify the existing `drupal-recipe` path for recipes.

### 7. Update Composer metadata

Add or update a repository entry (update instead of appending if one already exists):

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

Repository rules:
- In root `composer.json`: place before `packages.drupal.org`
- In `composer.sandbox.json`: keep `repositories` sorted alphabetically by `package.name`

Require rules:
- Always use `"*"` as the version constraint: `"drupal/{project_name}": "*"`
- Update existing constraint instead of creating a duplicate
- Keep `require` sorted alphabetically

### 8. Install via DDEV

| Ownership | Package state | Command |
|---|---|---|
| Sandbox | any | `ddev composer update drupal/{project_name} --with-all-dependencies` |
| Root | new | `ddev composer require drupal/{project_name}:*` |
| Root | existing | `ddev composer update drupal/{project_name} --with-all-dependencies` |

Do not run `ddev composer require` after manually editing `composer.sandbox.json`.

After Composer succeeds: `ddev drush cr`

### 9. Verify the result

Confirm:
- Manifest contains the package in both `repositories` and `require`
- Package installed to the expected path
- `composer.lock` shows `drupal/{project_name}` resolved to `{branch}-dev` from the Drupal.org git URL

## Notes

- Always prefer SSH: `git@git.drupal.org:project/{project_name}.git`
- If Composer cannot access SSH from the container, have the user run `ddev auth ssh` and retry
- HTTPS is acceptable only for read-only access when the user does not intend to push
