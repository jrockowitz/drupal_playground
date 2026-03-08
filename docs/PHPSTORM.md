# IDE Setup: PHPStorm for Drupal

This document provides a reference for configuring PHPStorm for Drupal development, including recommended plugins, tools, and editor settings.

## Drupal Support in PHPStorm

PHPStorm provides built-in support for Drupal, including integration with Drupal's coding standards, hook completion, and more.

1. Go to **Settings** (or **Preferences** on macOS) `⌘ ,`.
2. Navigate to **PHP > Frameworks**.
3. Select **Drupal**.
4. Check **Enable Drupal support**.
5. Specify the Drupal installation path.
6. Choose the Drupal version (e.g., 10 or 11).
7. (Optional) Set up the **Drupal Association** for file types like `.module`, `.inc`, `.theme`.

Reference: [PHPStorm Drupal Support](https://www.jetbrains.com/help/phpstorm/drupal-support.html)

## Plugins

The following plugins are recommended or installed for Drupal development in PHPStorm.

```bash
# List installed plugins.
ls ~/Library/Application\ Support/JetBrains/PhpStorm*/plugins/
```

### Core Drupal & PHP Tools

- **[Drupal](https://plugins.jetbrains.com/plugin/7503-drupal)** (Built-in)
  Provides Drupal-specific support including hook completion, Drupal coding standards, and module integration.
- **[PHPStan](https://plugins.jetbrains.com/plugin/15858-phpstan)**
  Static analysis tool for finding errors in your PHP code without running it.
- **[Psalm](https://plugins.jetbrains.com/plugin/14529-psalm)**
  A static analysis tool for finding errors in PHP applications.
- **[DDEV Integration](https://plugins.jetbrains.com/plugin/18813-ddev-integration)**
  Integrates PHPStorm with DDEV for local development.

### AI & Modern Development

- **[GitHub Copilot](https://plugins.jetbrains.com/plugin/17718-github-copilot)**
  AI-powered code completion and chat.
- **[AI Assistant](https://plugins.jetbrains.com/plugin/22282-ai-assistant)** (ml-llm)
  JetBrains' native AI features for coding assistance.
- **[MCP Server](https://plugins.jetbrains.com/plugin/26252-mcp-server)**
  Model Context Protocol server integration for AI tools.


### Testing & Quality

- **[Pest](https://plugins.jetbrains.com/plugin/14636-pest)**
  A PHP testing framework with a focus on simplicity.
- **[Behat Support](https://plugins.jetbrains.com/plugin/7339-behat-support)**
  Support for the Behat BDD framework.
- **[Codeception Framework](https://plugins.jetbrains.com/plugin/7338-codeception-framework)**
  Full-stack testing framework for PHP.
- **[PHPUnit](https://www.jetbrains.com/help/phpstorm/phpunit.html)** (Built-in)
  The standard unit testing framework for PHP.

### Frameworks & Languages

- **[Laravel Idea](https://plugins.jetbrains.com/plugin/13441-laravel-idea)**
  Advanced support for Laravel, often useful for modern PHP development.
- **[Symfony Support](https://plugins.jetbrains.com/plugin/7219-symfony-support)** (Note: Ensure installed if working with Drupal 8+)
  Provides deep integration with Symfony components used by Drupal.
- **[Blade](https://plugins.jetbrains.com/plugin/7526-blade-support)**
  Laravel Blade template engine support.
- **[Twig](https://plugins.jetbrains.com/plugin/7303-twig)** (Built-in)
  Drupal's default templating engine support.

### Utility & Config

- **[.env files support](https://plugins.jetbrains.com/plugin/9525--env-files-support)**
  Syntax highlighting and completion for `.env` files.
- **[EditorConfig](https://plugins.jetbrains.com/plugin/7294-editorconfig)**
  Maintain consistent coding styles across different editors and IDEs.
- **[Prettier](https://plugins.jetbrains.com/plugin/10456-prettier)**
  Integrate Prettier for consistent code formatting.
- **[Makefile support](https://plugins.jetbrains.com/plugin/9333-makefile-support)**
  Syntax highlighting and run configurations for Makefiles.
- **[Mermaid](https://plugins.jetbrains.com/plugin/20146-mermaid)**
  Renders Mermaid diagrams in Markdown and other supported files.
- **[Regex Rename files](https://plugins.jetbrains.com/plugin/12181-regex-rename-files)**
  Rename multiple files using regular expressions.

## Editor Customization

### Junie AI Guidelines

`AGENTS.md` is not picked up automatically by Junie. To enable it, you must set the path in settings:

1. Go to **Settings > Tools > Junie > Project Settings**.
2. Set the **Guidelines Path** to point to your `AGENTS.md` file.

Reference: [JUNIE-618: Support AGENTS.md](https://youtrack.jetbrains.com/issue/JUNIE-618/Support-AGENTS.md)

### Associate a Directory with a VCS

If your project root or subdirectories are not automatically recognized as Git repositories:
1. Go to **Settings > Version Control > Directory Mappings**.
2. Click **+** to add a mapping.
3. Select the directory and the VCS (usually Git).

Reference: [Associate directory with VCS](https://www.jetbrains.com/help/phpstorm/enabling-version-control.html#associate_directory_with_VCS)

### Zooming in the Editor

You can quickly adjust the font size in the editor:
- **Enable Zoom with Mouse Wheel**: Go to **Settings > Editor > General** and check **Change font size with Command + Mouse Wheel**.
- **Keyboard Shortcuts**:
    - **Increase font size**: `Shift` + `Command` + `.` (period)
    - **Decrease font size**: `Shift` + `Command` + `,` (comma)
    - **Reset font size**: `Command` + `0` (zero)

Reference: [Zooming in the Editor](https://www.jetbrains.com/help/rider/Zooming_in_the_Editor.html) (Common to JetBrains IDEs)

### Visual Guides

Visual guides help you maintain consistent line lengths.
1. Go to **Settings > Editor > Code Style > PHP**.
2. Open the **Wrapping and Braces** tab.
3. Find **Visual guides** and add your preferred column limits (e.g., `80, 120`).

Reference: [Visual Guides Settings](https://www.jetbrains.com/help/phpstorm/settings-code-style-php.html#common-wrapping-options)

### Merge All Project Windows (macOS)

To automatically merge all project windows into tabs on macOS:
1. Open macOS **System Settings**.
2. Navigate to **Desktop & Dock**.
3. In the **Windows** section, set **Prefer tabs when opening documents** to **Always**.
4. In PHPStorm, this will ensure that new projects open as tabs in the existing window.

Reference: [IJPL-59820: Enable Auto-Merge All Project Windows for macOS](https://youtrack.jetbrains.com/issue/IJPL-59820/Enable-Auto-Merge-All-Project-Windows-for-macOS)

### Set Default PHP Version for New Projects

To set a default PHP version and CLI interpreter for all future projects:
1. Go to **File > New Projects Setup > Settings for New Projects...** (on macOS).
2. Navigate to **Languages & Frameworks > PHP**.
3. Set the **PHP Level** (e.g., 8.3).
4. Select or add a default **CLI Interpreter**.
5. Click **Apply** and **OK**.

Reference: [How To Set A Default PHP Version For All New Projects In PhpStorm](https://mikesmith.us/how-to-set-a-default-php-version-for-all-new-projects-in-phpstorm-2022-2-2/)

## Additional Resources

- [JetBrains PHPStorm Blog](https://blog.jetbrains.com/phpstorm/)
- [Drupal.org: Configuring PHPStorm](https://www.drupal.org/docs/develop/development-tools/configuring-phpstorm)
- [Symfony and Drupal 8+ Development in PHPStorm](https://www.jetbrains.com/help/phpstorm/symfony-and-drupal-development-in-phpstorm.html)