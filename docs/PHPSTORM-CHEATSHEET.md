# IDE-PHPSTORM-CHEATSHEET.md: PHPStorm Cheatsheet (Mac)

---

## Navigation

| Action | Shortcut |
|---|---|
| Search Everywhere | `Shift Shift` (double-tap) |
| Go to File | `Cmd+Shift+O` |
| Go to Class | `Cmd+O` |
| Go to Symbol | `Cmd+Opt+O` |
| Go to Action | `Cmd+Shift+A` |
| Go to Line | `Cmd+L` |
| Go to Declaration | `Cmd+B` |
| Go to Implementation | `Cmd+Opt+B` |
| Go to Type Declaration | `Cmd+Shift+B` |
| Go to Super Method | `Cmd+U` |
| Navigate Back / Forward | `Cmd+[` / `Cmd+]` |
| Recent Files | `Cmd+E` |
| Recent Locations | `Cmd+Shift+E` |
| File Structure (outline) | `Cmd+F12` |
| Next/Prev Error | `F2` / `Shift+F2` |
| Switch Tab | `Ctrl+Tab` |
| Jump to Navigation Bar | `Cmd+Up` |

---

## Editing

| Action | Shortcut |
|---|---|
| Basic Code Completion | `Ctrl+Space` |
| Smart Completion | `Ctrl+Shift+Space` |
| Show Intention Actions / Quick Fix | `Opt+Enter` |
| Parameter Info | `Cmd+P` |
| Quick Documentation | `F1` |
| Generate Code (getters, setters, etc.) | `Cmd+N` |
| Override Methods | `Ctrl+O` |
| Implement Methods | `Ctrl+I` |
| Surround With (if, try, etc.) | `Cmd+Opt+T` |
| Comment Line | `Cmd+/` |
| Comment Block | `Cmd+Opt+/` |
| Duplicate Line | `Cmd+D` |
| Delete Line | `Cmd+Backspace` |
| Move Line Up/Down | `Opt+Shift+Up/Down` |
| Move Statement Up/Down | `Cmd+Shift+Up/Down` |
| Extend/Shrink Selection | `Opt+Up/Down` |
| Reformat Code | `Cmd+Opt+L` |
| Optimize Imports | `Ctrl+Opt+O` |
| Auto-Indent Lines | `Ctrl+Opt+I` |
| Undo / Redo | `Cmd+Z` / `Cmd+Shift+Z` |
| Clipboard History | `Cmd+Shift+V` |
| Toggle Case | `Cmd+Shift+U` |
| Join Lines | `Ctrl+Shift+J` |
| Split Line | `Cmd+Enter` |
| Complete Current Statement | `Cmd+Shift+Enter` |

---

## Multi-Cursor & Selection

| Action | Shortcut |
|---|---|
| Add Cursor Above/Below | `Opt+Opt+Up/Down` (hold second Opt) |
| Add Cursor at Mouse Click | `Opt+Click` |
| Select All Occurrences | `Ctrl+Cmd+G` |
| Select Next Occurrence | `Ctrl+G` |
| Unselect Occurrence | `Ctrl+Shift+G` |
| Column Selection Mode | `Cmd+Shift+8` |

---

## Search & Replace

| Action | Shortcut |
|---|---|
| Find in File | `Cmd+F` |
| Replace in File | `Cmd+R` |
| Find in Path (project-wide) | `Cmd+Shift+F` |
| Replace in Path | `Cmd+Shift+R` |
| Find Usages | `Opt+F7` |
| Show Usages Popup | `Cmd+Opt+F7` |
| Highlight Usages in File | `Cmd+Shift+F7` |
| Find Next / Previous | `Cmd+G` / `Cmd+Shift+G` |

---

## Refactoring

| Action | Shortcut |
|---|---|
| Refactor This (menu) | `Ctrl+T` |
| Rename | `Shift+F6` |
| Extract Variable | `Cmd+Opt+V` |
| Extract Constant | `Cmd+Opt+C` |
| Extract Field | `Cmd+Opt+F` |
| Extract Method | `Cmd+Opt+M` |
| Extract Parameter | `Cmd+Opt+P` |
| Inline | `Cmd+Opt+N` |
| Change Signature | `Cmd+F6` |
| Safe Delete | `Cmd+Delete` |
| Copy Class / Move Class | `F5` / `F6` |

---

## Debugging

| Action | Shortcut |
|---|---|
| Toggle Breakpoint | `Cmd+F8` |
| View Breakpoints | `Cmd+Shift+F8` |
| Debug | `Ctrl+D` |
| Step Over | `F8` |
| Step Into | `F7` |
| Smart Step Into | `Shift+F7` |
| Step Out | `Shift+F8` |
| Run to Cursor | `Opt+F9` |
| Evaluate Expression | `Opt+F8` |
| Resume Program | `Cmd+Opt+R` |
| Stop | `Cmd+F2` |

---

## Running

| Action | Shortcut |
|---|---|
| Run | `Ctrl+R` |
| Debug | `Ctrl+D` |
| Run Current File/Config | `Ctrl+Shift+R` |
| Edit Run Configurations | `Ctrl+Opt+R` |

---

## Version Control / Git

| Action | Shortcut |
|---|---|
| VCS Operations Popup | `Ctrl+V` |
| Commit | `Cmd+K` |
| Push | `Cmd+Shift+K` |
| Update Project (Pull) | `Cmd+T` |
| Show Diff | `Cmd+D` (in Commit window) |
| Rollback Changes | `Cmd+Opt+Z` |
| Show Git Log | `Cmd+9` |
| Annotate (Git Blame) | Right-click gutter → **Annotate with Git Blame** |
| Show History for File | Right-click file → **Git → Show History** |

---

## Tool Windows

| Action | Shortcut |
|---|---|
| Project | `Cmd+1` |
| Version Control | `Cmd+9` |
| Terminal | `Opt+F12` |
| Run | `Cmd+4` |
| Debug | `Cmd+5` |
| Problems | `Cmd+6` |
| Structure | `Cmd+7` |
| Services | `Cmd+8` |
| Hide All Tool Windows | `Cmd+Shift+F12` |
| Jump to Last Tool Window | `F12` |

---

## Live Templates & Postfix Completion

### Built-in Live Templates (type abbreviation + Tab)

| Abbreviation | Expands To |
|---|---|
| `eco` | `echo "";` |
| `fore` | `foreach` loop |
| `forek` | `foreach` with key and value |
| `pubf` | `public function` |
| `prof` | `protected function` |
| `prif` | `private function` |
| `pub` | `public property` |
| `__con` | `__construct()` method |
| `thr` | `throw new` |

### Postfix Completion (type expression then `.` + abbreviation)

| Postfix | Example | Result |
|---|---|---|
| `.if` | `$x.if` | `if ($x) {}` |
| `.else` | `$x.else` | `if (!$x) {}` |
| `.var` | `getValue().var` | `$value = getValue();` |
| `.null` | `$x.null` | `if ($x === null) {}` |
| `.notnull` | `$x.notnull` | `if ($x !== null) {}` |
| `.foreach` | `$items.foreach` | `foreach ($items as $item) {}` |
| `.echo` | `$x.echo` | `echo $x;` |
| `.return` | `$x.return` | `return $x;` |
| `.throw` | `$exception.throw` | `throw $exception;` |
| `.try` | `someCall().try` | wraps in `try/catch` |

---

## PHP-Specific Tips

### Type Hinting Assistance
- PHPStorm infers types from PHPDoc, type hints, and usage patterns.
- Use `/** @var Type $var */` inline to help the IDE when inference fails.

### Composer Integration
- **Tools → Composer** to manage `composer.json` directly.
- PHPStorm auto-detects PSR-4 autoload mappings from `composer.json`.

### PHPUnit Integration
- Click the green play icon next to any test method or class to run/debug it.
- Configure the test framework in **Settings → PHP → Test Frameworks**.
- Right-click a directory to **Run Tests** for all tests within it.

### Xdebug Setup
1. Install Xdebug in your PHP environment.
2. Set `xdebug.mode=debug` and `xdebug.start_with_request=yes` in `php.ini`.
3. In PHPStorm: **Settings → PHP → Debug** — set the Xdebug port (default `9003`).
4. Click **Start Listening for PHP Debug Connections** (phone icon in toolbar).
5. Set breakpoints and load your page in the browser.

### Database Tools
- Open **Database** tool window to connect to MySQL, PostgreSQL, SQLite, etc.
- Get autocompletion for SQL inside PHP string literals when a data source is configured.
- Use `Cmd+Enter` to execute SQL in the console.

---

## Drupal-Specific Tips

### Enable Drupal Support
- **Settings → PHP → Frameworks → Drupal** — enable and set Drupal root.
- This adds Drupal hook completion, navigation to hook definitions, and coding standards.

### Drush & DDEV Integration
- Use the built-in Terminal (`Opt+F12`) to run `ddev drush` commands.
- Set up an external tool: **Settings → Tools → External Tools** — add `ddev drush` with parameters.

### Useful Plugins for Drupal
- **Symfony Support** — aids with service container, routing, and Twig.
- **Twig Support** (bundled) — syntax highlighting and completion in `.html.twig` files.
- **PHP Annotations** — better annotation handling for Drupal plugins.

### Hook & Service Navigation
- Use `Cmd+B` on a hook function name to jump to where it's invoked.
- Use **Go to Symbol** (`Cmd+Opt+O`) to search for service IDs.

---

## Productivity Boosters

### Quick Lists
- Create custom shortcut menus: **Settings → Appearance → Quick Lists**.
- Assign a single shortcut to access a group of related actions.

### File Templates
- **Settings → Editor → File and Code Templates** — customize templates for new PHP classes, interfaces, etc.

### Scratches & Consoles
- `Cmd+Shift+N` (from Search Everywhere) → **New Scratch File** for quick throwaway code.
- Scratch files support full IDE features (completion, debugging).

### Local History
- Right-click a file → **Local History → Show History** to see all recent changes, even without Git.
- Useful for recovering code lost before committing.

### Structural Search & Replace
- **Edit → Find → Search Structurally** — find code patterns beyond text (e.g., find all `if` statements with empty bodies).

### HTTP Client
- **Tools → HTTP Client** or create `.http` files for testing REST APIs directly in the IDE.
- Supports variables, environments, and response assertions.

### IDE Scripting Console
- **Tools → IDE Scripting Console** — run Groovy/Kotlin scripts to automate IDE actions.
- Example: list all plugins, batch-rename files, or generate boilerplate.

### Distraction-Free & Zen Mode
- **View → Appearance → Enter Distraction Free Mode** — hides all toolbars and windows.
- **View → Appearance → Enter Zen Mode** — distraction-free + full screen.

---

## Useful Settings to Know

| Setting | Location |
|---|---|
| Keymap | Settings → Keymap |
| Code Style (PHP) | Settings → Editor → Code Style → PHP |
| Inspections | Settings → Editor → Inspections |
| PHP Interpreter | Settings → PHP |
| Composer | Settings → PHP → Composer |
| External Tools | Settings → Tools → External Tools |
| Editor Font & Colors | Settings → Editor → Font / Color Scheme |
| Save Actions (reformat, optimize) | Settings → Tools → Actions on Save |
| File Watchers (Sass, LESS, etc.) | Settings → Tools → File Watchers |
