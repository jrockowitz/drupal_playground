---
name: drupalorg-composer-repository
description: Clones any Drupal.org project (module, theme, recipe) as a git repository into composer.json or composer.sandbox.json for local contribution work. Fetches git instructions from drupal.org to determine the correct branch. Trigger when user says "clone for local dev", "contribute to a module", "work off a branch", "add drupal.org git repo", "Clone {name} from Drupal.org", or similar.
---

# Drupal.org Composer Repository Skill

Sets up a Drupal.org project (module, theme, or recipe) as a Composer `type: package` repository sourced from git, so you can work off a live branch for local contribution.

## When to Use This Skill

Activate when the user:
- Says "clone for local dev", "work off a branch", or "add drupal.org git repo"
- Says "contribute to a module/theme/recipe"
- Says "Clone {name} from Drupal.org"
- Wants to swap a packaged release for a git checkout of any Drupal.org project

---

## Step 1 — Gather Project Info

Ask the user for (or infer from context):
- **Project machine name** — e.g. `webform`, `token`, `gin`
- **Project type** — module, theme, or recipe (default: module if unclear)
- **Branch** (optional) — if not provided, fetch from drupal.org in Step 2

---

## Step 2 — Fetch Git Instructions from Drupal.org

Fetch `https://www.drupal.org/project/{project_name}/git-instructions` using WebFetch.

Extract:
- **SSH clone URL** — e.g. `git@git.drupal.org:project/webform.git`
- **Default branch** — the first/recommended branch shown (e.g. `6.3.x`)

Derive the version string from the branch: `6.3.x` → `6.3.x-dev`.

---

## Step 3 — Determine Package Type and Install Path

All git-cloned projects use sandbox Composer types, regardless of whether they are official contrib or actual sandboxes. This keeps them isolated from managed contrib code.

| Project kind | Composer type | Install path |
|---|---|---|
| Module | `drupal-module-sandbox` | `web/modules/sandbox/{$name}` |
| Theme | `drupal-theme-sandbox` | `web/themes/sandbox/{$name}` |
| Recipe | `drupal-recipe` | `recipes/{$name}` |

The Composer package name is always `drupal/{project_name}` (not a `sandbox/` prefix).

---

## Step 4 — Check Installer Paths in `composer.json`

Read `composer.json` and verify `extra.installer-paths` contains an entry for the sandbox type being used. Add any that are missing.

For `drupal-module-sandbox`:
```json
"web/modules/sandbox/{$name}": [
    "type:drupal-module-sandbox"
]
```

For `drupal-theme-sandbox` (add if missing):
```json
"web/themes/sandbox/{$name}": [
    "type:drupal-theme-sandbox"
]
```

Recipes use the existing `drupal-recipe` installer path — verify it exists.

---

## Step 5 — Check for `wikimedia/composer-merge-plugin`

Read `composer.json` and check `require` for `wikimedia/composer-merge-plugin`:
- **Present** → use `composer.sandbox.json` (Step 6a)
- **Absent** → add directly to `composer.json` (Step 6b)

---

## Step 6a — Using `composer.sandbox.json` (merge-plugin present)

1. Check if `composer.sandbox.json` exists at the project root.
2. If not, create it:
   ```json
   {
       "repositories": [],
       "require": {}
   }
   ```
3. Add the repository entry (see Step 7) to `repositories`.
4. Add `"drupal/{project_name}": "*"` to `require`.
5. Check `extra.merge-plugin.include` in `composer.json`. If `composer.sandbox.json` is not listed, add it.

---

## Step 6b — Adding Directly to `composer.json` (no merge-plugin)

1. Add the repository entry (see Step 7) to `repositories`. It must appear **before** `packages.drupal.org` so it takes priority.
2. Add `"drupal/{project_name}": "*"` to `require`.

---

## Step 7 — Repository Entry Format

Use `type: package` with a `source` block:

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

Adjust `type` per Step 3 for themes (`drupal-theme-sandbox`) or recipes (`drupal-recipe`).

---

## Step 8 — Run Composer and Verify

```bash
# Install from git
composer require drupal/{project_name}

# Clear Drupal caches
ddev drush cr
```

Confirm the project installed at the correct sandbox path:
- Module: `web/modules/sandbox/{project_name}/`
- Theme: `web/themes/sandbox/{project_name}/`
- Recipe: `recipes/{project_name}/`

---

## Worked Example: webform Module

Fetching `https://www.drupal.org/project/webform/git-instructions` yields:
- SSH URL: `git@git.drupal.org:project/webform.git`
- Default branch: `6.3.x`
- Version: `6.3.x-dev`
- Composer type: `drupal-module-sandbox`

**`composer.sandbox.json`:**
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

**`composer.json` merge-plugin include** (add `composer.sandbox.json` if missing):
```json
"merge-plugin": {
    "include": [
        "web/modules/contrib/schemadotorg/composer.libraries.json",
        "composer.sandbox.json"
    ]
}
```

Module installs at: `web/modules/sandbox/webform/`

---

## Notes

- **`composer.sandbox.json` is intentionally gitignored** (or should be) — it holds local dev overrides that should not be committed.
- If a package already exists in `repositories` (by `package.name`), update it rather than adding a duplicate.
- If the user provides a specific branch (e.g. `7.x`), use that instead of the default fetched from drupal.org.
- **Always prefer SSH** (`git@git.drupal.org:project/{project_name}.git`) — SSH is required to commit and push changes back to Drupal.org.
- If Composer fails with an SSH timeout, prompt the user to run `ddev auth ssh` to forward the host SSH agent into the container, then retry.
- HTTPS (`https://git.drupalcode.org/project/{project_name}.git`) is an acceptable fallback only if the user explicitly states they do not want SSH (read-only, cannot push to Drupal.org).

---

## Resources

- [Composer tricks for local development](https://www.drupal.org/docs/develop/using-composer/tricks-for-using-composer-in-local-development)
- [git.drupalcode.org](https://git.drupalcode.org)
