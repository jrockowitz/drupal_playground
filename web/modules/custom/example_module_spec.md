# Example Module — Build Specification

## Overview

**This example module** (`example`) provides a configuration form at **Administration → Configuration → Example** where administrators enter a message. A read-only page at `/example` displays the saved value.

---

## Requirements

### Functional

1. `ExampleSettingsForm` — a `ConfigFormBase` at `/admin/config/example/settings` with fields:
   - `message` (text, required) — a short message, max 255 characters.
2. Form uses `example.settings` config object; `#config_target` on each element handles both populating default values and saving on submit — no manual `set()`/`save()` calls required. `RedundantEditableConfigNamesTrait` replaces the `getEditableConfigNames()` override.
3. `ExampleController::page()` — a read-only page at `/example` that reads `example.settings` and renders the value in a `#markup` render array.
4. Menu link under **Administration → Configuration** pointing to the settings form.
5. Permissions: settings form requires `administer site configuration`; the display page requires `access content`.
6. Config schema in `config/schema/example.schema.yml` validates the field and enables translation.

### Non-Functional

Standards and coding conventions that apply to every file in the module.

- PHPCS `Drupal`/`DrupalPractice` sniffs; no errors or warnings.
- `declare(strict_types=1)` in every PHP file; fully typed method signatures.
- Dependency injection throughout — no `\Drupal::service()` or static calls inside classes.
- No hard dependencies beyond Drupal core.
- Functional tests for the form and the display page.

---

## Steps to Review (⚫ = step  ✅ = pass  ❌ = fail)

### Settings form

- ⚫ Navigate to **Administration → Configuration → Example** (`/admin/config/example/settings`).
- ⚫ Confirm the form has a **Message** text field with a label and description.
- ⚫ Leave **Message** empty and submit — confirm a validation error appears on that field.
- ⚫ Enter a valid message and submit — confirm the "Configuration saved." status message appears.

### Display page

- ⚫ Navigate to `/example` as a user with `access content` permission.
- ⚫ Confirm the page renders and shows the message saved in the previous step.
- ⚫ Navigate to `/example` as an anonymous user — confirm a `403` or login redirect depending on site anonymous access settings.

---

## Design

### Routing

| Route | Path | Handler | Permission |
|---|---|---|---|
| `example.settings` | `/admin/config/example/settings` | `ExampleSettingsForm` | `administer site configuration` |
| `example.page` | `/example` | `ExampleController::page()` | `access content` |

### Config Schema

`example.settings` stores:

| Key | Type | Label | Constraints |
|---|---|---|---|
| `message` | `string` | Message | Required; max 255 chars |

Default values live in `config/install/example.settings.yml`. Schema lives in `config/schema/example.schema.yml`.

### Form Architecture

`ExampleSettingsForm` extends `ConfigFormBase` and uses `RedundantEditableConfigNamesTrait`. Because every form element uses `#config_target`, the trait replaces the `getEditableConfigNames()` override entirely. `buildForm()` adds the `message` element with `#config_target => 'example.settings:message'`; the form system handles loading the default value and saving on submit. `submitForm()` delegates to `parent::submitForm()` for the status message.

### Controller Architecture

`ExampleController` extends `ControllerBase`. It does not inject additional services — `config()` is inherited from `ControllerBase`. `page()` reads `example.settings`, builds a `#markup` render array containing the message, and returns it with a title.

---

## Module File Structure

```
example/
├── .gitlab-ci.yml                          # Drupal Association CI template
├── composer.json
├── logo.png
├── README.md
├── AGENTS.md
├── CLAUDE.md
├── example.info.yml
├── example.routing.yml
├── example.links.menu.yml
├── config/
│   ├── install/
│   │   └── example.settings.yml           # default config values
│   └── schema/
│       └── example.schema.yml             # config schema + translation metadata
└── src/
    ├── Controller/
    │   └── ExampleController.php          # page() — renders saved settings
    └── Form/
        └── ExampleSettingsForm.php        # ConfigFormBase; message
```

---

## Implementation

---

### .gitlab-ci.yml

Use the Drupal Association's maintained template. The simplest setup via the GitLab UI:

1. Open the repository on `git.drupalcode.org`.
2. Add a new file named `.gitlab-ci.yml` using the repository file browser (not the Web IDE).
3. Select the **Drupal Association `template.gitlab-ci.yml`** from the template picker.
4. Commit to the default branch.

Verify by navigating to **Build → Pipelines** — the pipeline should trigger automatically on commit.

Reference: [GitLab CI — Using GitLab to contribute to Drupal](https://www.drupal.org/docs/develop/git/using-gitlab-to-contribute-to-drupal/gitlab-ci)

---

### logo.png

512 × 512 px PNG, ≤ 10 KB, no rounded corners, no module name text. Optimise with `pngquant` at ~80% quality. Place in the repository root on the default branch.

**Image generation prompt:**

> Create a square 512×512 logo for a Drupal contributed module called **Example**.
>
> The module provides an admin configuration form and a read-only display page. It is a developer reference and starting-point module.
>
> Design a clean, minimal icon that reads clearly at 64 × 64 px. Do not include the module name as text. Do not round the corners. Use a transparent or solid background.
>
> Suggested visual direction: a simple settings gear or wrench overlaid with a small document or page motif, rendered in Drupal blue (#0678BE) with a white accent. Flat, two-tone, no gradients, no drop shadows.
>
> Output a PNG at exactly 512 × 512 px. File size should be 10 KB or less.

Reference: [Project Browser — Module logo](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/project-browser/module-maintainers-how-to-update-projects-to-be-compatible-with-project-browser#s-logo)

---

### README.md

Brief user-facing documentation covering what the module does, requirements, installation, permissions, and usage.

```markdown
# Example

A Drupal 10/11 module that provides a configuration form for storing a message
and a read-only page that displays the saved value.

## Requirements

- Drupal core 10.x or 11.x

## Installation

```bash
composer require drupal/example
drush en example
```

## Permissions

| Permission | Purpose |
|---|---|
| `administer site configuration` | Edit settings at `/admin/config/example/settings` |
| `access content` | View the display page at `/example` |

## Usage

1. Navigate to **Administration → Configuration → Example**.
2. Enter a message; save.
3. View the saved value at `/example`.
```

---

### AGENTS.md / CLAUDE.md

Do not hand-author these files. After the module directory and initial code are in place, generate them by running:

```bash
claude init
```

`claude init` inspects the codebase and produces a `CLAUDE.md` tailored to the actual file structure, coding patterns, and architecture it finds.

`AGENTS.md` is kept in sync with identical content. Both files serve the same purpose — project memory for AI coding agents. Copy `CLAUDE.md` to `AGENTS.md` after generation:

```bash
cp CLAUDE.md AGENTS.md
```

---

### composer.json

Standard Drupal module manifest. Keep the `drupal/core` constraint in sync with `core_version_requirement` in `example.info.yml`.

```json
{
    "name": "drupal/example",
    "description": "Configuration form and display page demonstrating module settings patterns.",
    "type": "drupal-module",
    "license": "GPL-2.0-or-later",
    "homepage": "https://drupal.org/project/example",
    "support": {
        "issues": "https://drupal.org/project/issues/example",
        "source": "https://git.drupalcode.org/project/example"
    },
    "require": {
        "drupal/core": "^10 || ^11"
    }
}
```

---

### example.info.yml

```yaml
name: 'Example'
type: module
description: 'Configuration form and display page demonstrating module settings patterns.'
package: Other
core_version_requirement: ^10 || ^11
```

---

### example.routing.yml

```yaml
example.settings:
  path: '/admin/config/example/settings'
  defaults:
    _form: '\Drupal\example\Form\ExampleSettingsForm'
    _title: 'Example settings'
  requirements:
    _permission: 'administer site configuration'

example.page:
  path: '/example'
  defaults:
    _controller: '\Drupal\example\Controller\ExampleController::page'
    _title: 'Example'
  requirements:
    _permission: 'access content'
```

---

### example.links.menu.yml

```yaml
example.settings:
  title: 'Example'
  description: 'Configure the Example module message.'
  route_name: example.settings
  parent: system.admin_config_other
```

---

### config/install/example.settings.yml

```yaml
message: 'Hello, world!'
```

---

### config/schema/example.schema.yml

```yaml
example.settings:
  type: config_object
  label: 'Example settings'
  mapping:
    message:
      type: string
      label: 'Message'
```

---

### src/Form/ExampleSettingsForm.php

`RedundantEditableConfigNamesTrait` replaces `getEditableConfigNames()` — valid because every element uses `#config_target` (see [change record](https://www.drupal.org/node/3373502)).

```php
<?php

declare(strict_types=1);

namespace Drupal\example\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\RedundantEditableConfigNamesTrait;

/**
 * Settings form for the Example module.
 */
final class ExampleSettingsForm extends ConfigFormBase {

  use RedundantEditableConfigNamesTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'example_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Message'),
      '#description' => $this->t('A short message displayed on the Example page.'),
      '#config_target' => 'example.settings:message',
      '#maxlength' => 255,
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

}
```

---

### src/Controller/ExampleController.php

```php
<?php

declare(strict_types=1);

namespace Drupal\example\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for the Example module display page.
 */
final class ExampleController extends ControllerBase {

  /**
   * Renders the Example settings display page.
   *
   * @return array<string, mixed>
   *   A render array.
   */
  public function page(): array {
    $message = $this
      ->config('example.settings')
      ->get('message');

    return [
      '#markup' => $this->t(
        '<p><strong>Message:</strong> @message</p>',
        ['@message' => $message],
      ),
    ];
  }

}
```

---

## Tests

---

### tests/src/Functional/ExampleSettingsFormTest.php

`BrowserTestBase`. Creates an admin user with `administer site configuration`. Covers the form render, validation, and a successful save.

```php
public function testFormRendersWithDefaults(): void {
  // Check that /admin/config/example/settings returns 200.
  // Check that the 'message' field is present and shows the default value.
}

public function testValidationErrors(): void {
  // Check that submitting with an empty message shows a required error on that field.
}

public function testSuccessfulSave(): void {
  // Check that submitting message = 'Hi there' shows "Configuration saved."
  // Check that the config object example.settings now has message = 'Hi there'.
}
```

---

### tests/src/Functional/ExamplePageTest.php

`BrowserTestBase`. Creates a user with `access content`. Covers the display page rendering the saved config values.

```php
public function testPageDisplaysSavedValues(): void {
  // Programmatically set example.settings message = 'Hello test'.
  // Check that GET /example returns 200.
  // Check that the response body contains 'Hello test'.
}

public function testPageRequiresPermission(): void {
  // Check that an anonymous request to /example returns 403 (or redirects to login).
}
```

---

## Reference

### Drupal APIs

- [ConfigFormBase](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Form!ConfigFormBase.php/class/ConfigFormBase/11.x) — base class for admin configuration forms
- [RedundantEditableConfigNamesTrait](https://www.drupal.org/node/3373502) — replaces `getEditableConfigNames()` when all form elements use `#config_target` (introduced in Drupal 10.2)
- [ControllerBase](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Controller!ControllerBase.php/class/ControllerBase/11.x) — base class for route controllers
- [Config schema](https://www.drupal.org/docs/drupal-apis/configuration-api/config-schema-and-translations) — defining types for config keys and enabling translation

### Drupal.org Project Setup

- [GitLab CI — Using GitLab to contribute to Drupal](https://www.drupal.org/docs/develop/git/using-gitlab-to-contribute-to-drupal/gitlab-ci) — configuring automated testing via `.gitlab-ci.yml` and the Drupal Association's maintained template
- [Project Browser — Module logo](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/project-browser/module-maintainers-how-to-update-projects-to-be-compatible-with-project-browser#s-logo) — `logo.png` specification and requirements for Project Browser display
