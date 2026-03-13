# Telephone Filter Module — Build Specification

## Overview

**This module** (`telephone_filter`) converts US phone numbers found in rendered HTML into clickable `tel:` links. Implemented as a Drupal **text format filter plugin**.

---

## Requirements

### Functional

1. Detects US phone numbers in rendered HTML, **not** inside existing `<a>` tags.
2. Wraps matches in `<a href="tel:+1XXXXXXXXXX">original text</a>` links.
3. Supported numeric formats: `888-888-8888`, `888.888.8888`, `(888) 888-8888`.
4. Supported vanity formats: `800-FLOWERS`, `800-ASK-HELP`.
5. Configurable list of allowed area codes stored as an array — entered one per line in a textarea. Leaving the list empty links **all** phone numbers regardless of area code.
6. Skips numbers already inside `<a>` tags to prevent double-wrapping.
7. Regex area-code alternation is built dynamically from the plugin settings at process time.

### Non-Functional

Standards and coding conventions that apply to every file in the module.

- PHPCS `Drupal`/`DrupalPractice` sniffs; no errors or warnings.
- `declare(strict_types=1)` in every PHP file; fully typed method signatures.
- Dependency injection throughout — no `\Drupal::service()` or static calls inside class methods.
- No hard dependencies beyond Drupal core.
- PHPStan level 5+ clean.
- Filter type: `FilterInterface::TYPE_TRANSFORM_REVERSIBLE`.
- Unit tests for the filter plugin.

---

## Steps to Review (⚫ = step  ✅ = pass  ❌ = fail)

### Filter plugin discovery

- ⚫ Navigate to **Administration → Configuration → Content authoring → Text formats and editors** (`/admin/config/content/formats`).
- ⚫ Edit a text format and confirm **Telephone filter** appears in the filter list.
- ⚫ Enable the filter, save the format, and confirm no errors appear.

### Settings form

- ⚫ With the filter enabled, confirm the **Allowed area codes** textarea is present in the filter's settings row.
- ⚫ Clear the textarea and save — confirm **all** phone numbers are linked (empty list = match all).
- ⚫ Enter `800` and `888` (one per line), save, and confirm only numbers with those area codes are linked.
- ⚫ Enter an invalid value such as `80X` in the area codes textarea and attempt to save — confirm an inline validation error appears and the format is **not** saved.
- ⚫ Enter a wrong-length value such as `8000` and attempt to save — confirm the same inline error behaviour.

### Phone number conversion

- ⚫ Render text containing `888-888-8888` — confirm it becomes `<a href="tel:+18888888888">`.
- ⚫ Render text containing `888.888.8888` — confirm dot format is linked correctly.
- ⚫ Render text containing `(888) 888-8888` — confirm parentheses format is linked correctly.
- ⚫ Render text containing `800-FLOWERS` — confirm vanity format produces `<a href="tel:+18003569377">`.
- ⚫ Render text containing `<a href="tel:+18888888888">888-888-8888</a>` — confirm no double-wrapping.
- ⚫ Render text containing `999-888-8888` with area codes set to `800`/`888` — confirm unlisted area code produces no link.

---

## Design

### Filter Plugin Architecture

The module registers a single class `TelephoneFilter` as a Drupal filter plugin using the PHP 8 attribute syntax (`#[Filter(...)]`). Filter plugins extend `FilterBase` and are discovered automatically by Drupal's plugin system from `src/Plugin/Filter/`.

The filter appears in the **Text formats** admin UI (`/admin/config/content/formats`) and can be enabled on any text format. Once enabled, every time body text is rendered through that format, `process()` is called.

```
Admin UI → Text Format → TelephoneFilter enabled
                  ↓
           process($text, $langcode)
                  ↓
          Html::load($text)
                  ↓
        Walk all DOMText nodes
                  ↓
        Skip nodes with <a> ancestor
                  ↓
       Apply phone regex to node value
                  ↓
   Split text node → insert <a> DOM nodes
                  ↓
     Html::serialize() → FilterProcessResult
```

### Phone Regex

```
/\b(AREA_CODES)\s*[-.]?\s*([A-Z0-9]{3})\s*[-.]?\s*([A-Z0-9]{4})\b/
```

`AREA_CODES` is replaced at runtime with a `|`-joined alternation built from `$this->settings['area_codes']`, which is a `list<string>` of digit-only area code strings (e.g. `['800', '888']`). When the array is empty, the area-code capture group is replaced with `[0-9]{3}`, matching any three-digit area code — all phone numbers are linked.

The pattern has **no `i` flag**. The `[A-Z0-9]` character class matches uppercase letters and digits only. Vanity numbers must be entered in uppercase (`800-FLOWERS`) to be linked; lowercase (`800-flowers`) or mixed-case (`800-Flowers`) segments are treated as plain text and left unchanged.

The three capture groups correspond to:
1. Area code
2. Exchange (first 3 digits/letters)
3. Subscriber (last 4 digits/letters)

### DOMDocument Approach

`Html::load()` (from `\Drupal\Component\Utility\Html`) parses the HTML fragment into a `\DOMDocument` using the Masterminds HTML5 parser with UTF-8 encoding and no namespace injection — no manual charset declaration or error suppression required. A recursive tree walk then identifies all `DOMText` nodes. Before processing a text node, the code walks `$node->parentNode` up the tree checking for any `<a>` ancestor — if found, the node is skipped. After the tree is modified, `Html::serialize()` extracts only the `<body>` children and returns a clean HTML string, handling newline normalisation internally — no `saveHTML()` post-processing needed.

`DOMText::nodeValue` always contains **decoded** text — the HTML5 parser resolves all entities (e.g. `&amp;` → `&`) before the value is exposed to PHP. The phone regex therefore operates on plain characters with no risk of matching across entity boundaries.

For each match within a text node:
1. Split the text node around the match using `splitText()`.
2. Create an `<a>` element with `href="tel:+1{digits}"`. The element is created empty and the matched text is appended as a child `DOMText` node via `createTextNode()` — never passed as the second argument to `createElement()`, which does not HTML-encode its content and would be unsafe for text that contains `<` or `&`.
3. Replace the match portion of the text with the new element.
4. Digits are obtained by calling `vanityToDigits()` on each capture group before assembling the `href`.

### Vanity-to-Digits Conversion

`vanityToDigits(string $number): string` (private) strips all non-alphanumeric characters from `$number` and maps each letter to its telephone-keypad digit:

| Letters | Digit |
|---|---|
| ABC | 2 |
| DEF | 3 |
| GHI | 4 |
| JKL | 5 |
| MNO | 6 |
| PQRS | 7 |
| TUV | 8 |
| WXYZ | 9 |

Digits pass through unchanged. The result is a pure-digit string ready for a `tel:` URI.

### Config Schema

`filter_settings.telephone_filter` stores:

| Key | Type | Label | Constraints |
|---|---|---|---|
| `area_codes` | `sequence` of `string` | Allowed area codes | Optional; empty = match all |

Schema lives in `config/schema/telephone_filter.schema.yml`.

---

## Module File Structure

```
telephone_filter/
├── .gitlab-ci.yml                                  # Drupal Association CI template
├── composer.json
├── logo.png
├── README.md
├── AGENTS.md
├── CLAUDE.md
├── telephone_filter.info.yml
├── telephone_filter.module                         # hook_help() only
├── config/
│   └── schema/
│       └── telephone_filter.schema.yml             # filter settings schema
└── src/
    └── Plugin/
        └── Filter/
            └── TelephoneFilter.php                 # FilterBase plugin
tests/
└── src/
    └── Unit/
        ├── TelephoneFilterTest.php
        └── TelephoneFilterValidationTest.php         # validateConfigurationForm() / area-code validation
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

> Create a square 512×512 logo for a Drupal contributed module called **Telephone Filter**.
>
> The module converts US phone numbers in HTML text into clickable `tel:` links via a text format filter plugin.
>
> Design a clean, minimal icon that reads clearly at 64 × 64 px. Do not include the module name as text. Do not round the corners. Use a transparent or solid background.
>
> Suggested visual direction: a simple telephone handset overlaid with a small link-chain or cursor motif, rendered in Drupal blue (#0678BE) with a white accent. Flat, two-tone, no gradients, no drop shadows.
>
> Output a PNG at exactly 512 × 512 px. File size should be 10 KB or less.

Reference: [Project Browser — Module logo](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/project-browser/module-maintainers-how-to-update-projects-to-be-compatible-with-project-browser#s-logo)

---

### README.md

Brief user-facing documentation covering what the module does, requirements, installation, permissions, and usage.

```markdown
# Telephone Filter

A Drupal 10/11 text format filter plugin that converts US phone numbers in HTML
text into clickable `tel:` links.

## Requirements

- Drupal core 10.3.x or 11.x
- PHP 8.3+

## Installation

```bash
composer require drupal/telephone_filter
drush en telephone_filter
```

Enable the filter on a text format at `/admin/config/content/formats`.

## Features

- Detects numeric formats: `888-888-8888`, `888.888.8888`, `(888) 888-8888`.
- Detects vanity formats: `800-FLOWERS`, `800-ASK-HELP`.
- Configurable list of allowed area codes — leave blank to link all phone numbers.
- Skips numbers already inside `<a>` tags — no double-wrapping.
- Safe DOM-based HTML manipulation via `DOMDocument`.

## Usage

1. Navigate to **Administration → Configuration → Content authoring → Text formats and editors**.
2. Edit a text format and enable **Telephone filter**.
3. Optionally enter allowed area codes (one per line) in the filter's settings. Leave blank to link all phone numbers.
4. Save the format.
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

Standard Drupal module manifest. Keep the `drupal/core` constraint in sync with `core_version_requirement` in `telephone_filter.info.yml`.

```json
{
    "name": "drupal/telephone_filter",
    "description": "Text format filter that converts US phone numbers to clickable tel: links.",
    "type": "drupal-module",
    "license": "GPL-2.0-or-later",
    "homepage": "https://drupal.org/project/telephone_filter",
    "support": {
        "issues": "https://drupal.org/project/issues/telephone_filter",
        "source": "https://git.drupalcode.org/project/telephone_filter"
    },
    "require": {
        "drupal/core": "^10 || ^11"
    }
}
```

---

### telephone_filter.info.yml

```yaml
name: 'Telephone Filter'
type: module
description: 'Converts US phone numbers in text to clickable tel: links.'
package: Filter
core_version_requirement: ^10.3 || ^11
php: '8.3'
```

---

### telephone_filter.module

Implements `hook_help()` only.

```php
<?php

declare(strict_types=1);

/**
 * @file
 * Converts US phone numbers in text filters to clickable tel: links.
 */

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Implements hook_help().
 */
function telephone_filter_help(string $route_name, RouteMatchInterface $route_match): TranslatableMarkup|string {
  return match ($route_name) {
    'help.page.telephone_filter' => (string) t(
      'Provides a text filter that converts US phone numbers to clickable tel: links. '
      . 'Enable the filter on a text format at <a href=":url">Text formats</a>.',
      [':url' => '/admin/config/content/formats'],
    ),
    default => '',
  };
}
```

---

### config/schema/telephone_filter.schema.yml

Required for Drupal to understand and store the plugin's `area_codes` setting.

```yaml
filter_settings.telephone_filter:
  type: filter
  label: 'Telephone filter settings'
  mapping:
    area_codes:
      type: sequence
      label: 'Allowed area codes'
      sequence:
        type: string
        label: 'Area code'
```

---

### src/Plugin/Filter/TelephoneFilter.php

- **Namespace:** `Drupal\telephone_filter\Plugin\Filter`
- **Extends:** `\Drupal\filter\Plugin\FilterBase`
- **Plugin attribute:** `#[Filter(...)]` with `type: FilterInterface::TYPE_TRANSFORM_REVERSIBLE`

```php
<?php

declare(strict_types=1);

namespace Drupal\telephone_filter\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\filter\Attribute\Filter;
use Drupal\filter\Plugin\FilterInterface;

/**
 * Converts US phone numbers in HTML text to clickable tel: links.
 *
 * Supported formats:
 *   - Numeric:  888-888-8888 | 888.888.8888 | (888) 888-8888
 *   - Vanity:   800-FLOWERS  | 800-ASK-HELP  (uppercase letters only)
 *
 * Numbers already inside <a> tags are never double-wrapped.
 * Only numbers whose area code appears in the configured list are linked.
 * Lowercase or mixed-case vanity segments are not matched.
 */
#[Filter(
  id: 'telephone_filter',
  title: new TranslatableMarkup('Telephone filter'),
  description: new TranslatableMarkup('Converts US phone numbers to clickable tel: links.'),
  type: FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
)]
final class TelephoneFilter extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    // Empty array means all phone numbers are linked regardless of area code.
    return ['area_codes' => []] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form['area_codes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Allowed area codes'),
      '#description' => $this->t('Enter one area code per line. Leave blank to link all phone numbers regardless of area code.'),
      // Implode the stored array back to one-per-line for display in the textarea.
      '#default_value' => implode("\n", $this->settings['area_codes']),
      '#rows' => 5,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $raw = $form_state->getValue(['filters', $this->pluginId, 'settings', 'area_codes']) ?? '';

    foreach (array_map('trim', explode("\n", $raw)) as $line) {
      if ($line === '') {
        continue;
      }
      if (!ctype_digit($line) || strlen($line) !== 3) {
        $form_state->setErrorByName(
          'filters][' . $this->pluginId . '][settings][area_codes',
          $this->t('"%line" is not a valid area code. Each line must contain exactly 3 digits (e.g. 800).', ['%line' => $line]),
        );
        // One error message is enough; stop on the first invalid entry.
        break;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    // Validation has already rejected non-digit and wrong-length entries, so
    // only blank-line stripping is needed here.
    $raw = $form_state->getValue(['filters', $this->pluginId, 'settings', 'area_codes']) ?? '';
    $this->settings['area_codes'] = array_values(array_filter(
      array_map('trim', explode("\n", $raw)),
      static fn (string $code): bool => $code !== '',
    ));
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function process(string $text, string $langcode): FilterProcessResult {
    $area_codes = $this->settings['area_codes'];

    // Pattern explanation:
    //   \b              — word boundary to avoid partial matches
    //   (AREA_CODES)    — area code alternation; omitted entirely when list is empty
    //   \s*[-.]?\s*     — optional separator (space, dash, or dot) with surrounding whitespace
    //   ([A-Z0-9]{3})   — exchange: 3 uppercase alphanumeric characters (vanity or digits)
    //   \s*[-.]?\s*     — optional separator
    //   ([A-Z0-9]{4})   — subscriber: 4 uppercase alphanumeric characters (vanity or digits)
    //   \b              — word boundary
    // No i flag — lowercase vanity letters intentionally do not match.
    // When area_codes is empty, a bare 3-digit group matches any area code.
    if ($area_codes !== []) {
      $alternation = implode('|', array_map('preg_quote', $area_codes, array_fill(0, count($area_codes), '/')));
      $pattern = '/\b(' . $alternation . ')\s*[-.]?\s*([A-Z0-9]{3})\s*[-.]?\s*([A-Z0-9]{4})\b/';
    }
    else {
      // No restriction — match any 3-digit area code.
      $pattern = '/\b([0-9]{3})\s*[-.]?\s*([A-Z0-9]{3})\s*[-.]?\s*([A-Z0-9]{4})\b/';
    }

    $document = Html::load($text);
    $this->processNode($document, $document, $pattern);

    return new FilterProcessResult(Html::serialize($document));
  }

  /**
   * Recursively processes DOMText nodes, replacing phone matches with <a> elements.
   */
  private function processNode(\DOMDocument $document, \DOMNode $node, string $pattern): void {
    // Collect child nodes first; modifying the tree while iterating causes skips.
    /** @var list<\DOMNode> $children */
    $children = [];
    foreach ($node->childNodes as $child) {
      $children[] = $child;
    }

    foreach ($children as $child) {
      if ($child instanceof \DOMText) {
        if (!$this->hasAnchorAncestor($child)) {
          $this->replacePhoneNumbers($document, $child, $pattern);
        }
      }
      elseif ($child->hasChildNodes()) {
        $this->processNode($document, $child, $pattern);
      }
    }
  }

  /**
   * Returns TRUE if the given node has an <a> element anywhere in its ancestry.
   */
  private function hasAnchorAncestor(\DOMNode $node): bool {
    $parent = $node->parentNode;
    while ($parent !== NULL) {
      if ($parent instanceof \DOMElement && strtolower($parent->tagName) === 'a') {
        return TRUE;
      }
      $parent = $parent->parentNode;
    }
    return FALSE;
  }

  /**
   * Splits a DOMText node around phone-number matches, inserting <a> elements.
   */
  private function replacePhoneNumbers(\DOMDocument $document, \DOMText $text_node, string $pattern): void {
    $value = $text_node->nodeValue ?? '';

    // Guard: most text nodes contain no phone number. The cheap preg_match()
    // check avoids the overhead of PREG_OFFSET_CAPTURE for the common case.
    // preg_match_all() is only called when at least one match is confirmed.
    if (!preg_match($pattern, $value)) {
      return;
    }

    $parent = $text_node->parentNode;
    if ($parent === NULL) {
      return;
    }

    $offset = 0;
    $fragment = $document->createDocumentFragment();

    preg_match_all($pattern, $value, $matches, PREG_OFFSET_CAPTURE);

    foreach ($matches[0] as $index => $match) {
      [$matched_text, $match_offset] = $match;

      // Text before the match.
      if ($match_offset > $offset) {
        $fragment->appendChild(
          $document->createTextNode(substr($value, $offset, $match_offset - $offset))
        );
      }

      // Build the digit-only phone number for the tel: href.
      $area       = $this->vanityToDigits($matches[1][$index][0]);
      $exchange   = $this->vanityToDigits($matches[2][$index][0]);
      $subscriber = $this->vanityToDigits($matches[3][$index][0]);
      $digits     = $area . $exchange . $subscriber;

      // createElement() does not encode its second argument, so the matched
      // text is appended as a safe DOMText child node instead.
      $anchor = $document->createElement('a');
      $anchor->setAttribute('href', 'tel:+1' . $digits);
      $anchor->appendChild($document->createTextNode($matched_text));
      $fragment->appendChild($anchor);

      $offset = $match_offset + strlen($matched_text);
    }

    // Remaining text after the last match.
    if ($offset < strlen($value)) {
      $fragment->appendChild(
        $document->createTextNode(substr($value, $offset))
      );
    }

    $parent->replaceChild($fragment, $text_node);
  }

  /**
   * Converts vanity letters in a phone number segment to their keypad digits.
   *
   * Digits pass through unchanged. Letters are mapped per the standard
   * telephone keypad layout:
   *   ABC=2, DEF=3, GHI=4, JKL=5, MNO=6, PQRS=7, TUV=8, WXYZ=9
   *
   * @param string $number
   *   A single phone segment (area code, exchange, or subscriber).
   *
   * @return string
   *   The segment with all letters converted to digits.
   */
  private function vanityToDigits(string $number): string {
    return strtr(strtoupper($number), [
      'A' => '2', 'B' => '2', 'C' => '2',
      'D' => '3', 'E' => '3', 'F' => '3',
      'G' => '4', 'H' => '4', 'I' => '4',
      'J' => '5', 'K' => '5', 'L' => '5',
      'M' => '6', 'N' => '6', 'O' => '6',
      'P' => '7', 'Q' => '7', 'R' => '7', 'S' => '7',
      'T' => '8', 'U' => '8', 'V' => '8',
      'W' => '9', 'X' => '9', 'Y' => '9', 'Z' => '9',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function tips(bool $long = FALSE): TranslatableMarkup {
    return $this->t('US phone numbers with allowed area codes are automatically converted to clickable telephone links.');
  }

}
```

---

## Tests

The phone regex uses `[A-Z0-9]` (uppercase only) without the `i` flag, so vanity numbers must be uppercase to match. Lowercase or mixed-case letter segments are treated as plain text and not linked. `vanityToDigits()` is only ever called on segments that have already matched `[A-Z0-9]`.

`area_codes` is stored as a `list<string>`. `createFilter()` in the test helper accepts that array directly. An empty array means all phone numbers are linked.

---

### tests/src/Unit/TelephoneFilterTest.php

`UnitTestCase`. Every test method uses `@dataProvider`. Exercises `vanityToDigits()` directly via `\ReflectionMethod` (private method).

```php
<?php

declare(strict_types=1);

namespace Drupal\Tests\telephone_filter\Unit;

use Drupal\telephone_filter\Plugin\Filter\TelephoneFilter;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for TelephoneFilter.
 *
 * @coversDefaultClass \Drupal\telephone_filter\Plugin\Filter\TelephoneFilter
 * @group telephone_filter
 */
class TelephoneFilterTest extends UnitTestCase {

  // ---------------------------------------------------------------------------
  // process() — cases that SHOULD produce a link
  // ---------------------------------------------------------------------------

  /**
   * Tests that phone numbers matching the configured area codes are linked.
   *
   * @dataProvider processProducesLinkProvider
   * @covers ::process
   */
  public function testProcessProducesLink(string $input, string $expected_href): void {
    $result = $this->createFilter()->process($input, 'en');
    $this->assertStringContainsString(
      'href="' . $expected_href . '"',
      $result->getProcessedText(),
    );
  }

  /**
   * Data provider for testProcessProducesLink().
   *
   * Each entry is [input, expected_href] using the default area codes ['800', '888'].
   *
   * @return array<string, array{string, string}>
   */
  public static function processProducesLinkProvider(): array {
    return [
      // --- Separator formats ---

      // Standard dash-separated format.
      'dash format' => ['Call 888-888-8888 now', 'tel:+18888888888'],

      // Dot-separated format.
      'dot format' => ['Call 888.888.8888 now', 'tel:+18888888888'],

      // Parenthesised area code with space and dash.
      'parens space dash format' => ['Call (800) 555-1234 now', 'tel:+18005551234'],

      // Parenthesised area code, no space before exchange.
      'parens no space' => ['Call (800)555-1234 now', 'tel:+18005551234'],

      // Parenthesised area code with dot separator.
      'parens dot separator' => ['Call (800) 555.1234 now', 'tel:+18005551234'],

      // Space-separated (no dashes or dots).
      'space separator' => ['Call 888 888 8888 now', 'tel:+18888888888'],

      // Mixed separators — dash then dot.
      'mixed separators' => ['Call 888-888.8888 now', 'tel:+18888888888'],

      // --- Vanity formats (uppercase only) ---

      // Uppercase vanity word — [A-Z0-9] character class matches.
      'vanity FLOWERS uppercase' => ['Call 800-FLOWERS today', 'tel:+18003569377'],

      // Two-segment uppercase vanity number.
      'vanity ASK-HELP' => ['Call 800-ASK-HELP', 'tel:+18002754357'],

      // --- Position in string ---

      // Number appears at the very start — word boundary must fire at pos 0.
      'number at string start' => ['888-888-8888 is our number', 'tel:+18888888888'],

      // Number appears at the very end — word boundary must fire at end of string.
      'number at string end' => ['Call us at 888-888-8888', 'tel:+18888888888'],

      // --- Surrounding punctuation ---

      // Period immediately after the number — boundary must not consume it.
      'number followed by period' => ['Call 888-888-8888.', 'tel:+18888888888'],

      // Comma immediately after the number.
      'number followed by comma' => ['Call 888-888-8888, today', 'tel:+18888888888'],

      // Number wrapped in parentheses (the prose kind, not the area code kind).
      'number in prose parentheses' => ['(see 888-888-8888 for details)', 'tel:+18888888888'],

      // Number wrapped in double quotes.
      'number in double quotes' => ['"888-888-8888"', 'tel:+18888888888'],

      // --- HTML context — number inside non-anchor inline elements ---

      // Number inside a <p> tag — the text node should still be processed.
      'number in p tag' => ['<p>Call 888-888-8888 now</p>', 'tel:+18888888888'],

      // Number inside <strong> — not an anchor ancestor, must be linked.
      'number in strong tag' => ['<strong>888-888-8888</strong>', 'tel:+18888888888'],

      // Number inside <span> — not an anchor ancestor, must be linked.
      'number in span tag' => ['<span>888-888-8888</span>', 'tel:+18888888888'],
    ];
  }

  // ---------------------------------------------------------------------------
  // process() — cases that should NOT produce a link
  // ---------------------------------------------------------------------------

  /**
   * Tests that certain inputs produce no link.
   *
   * @dataProvider processProducesNoLinkProvider
   * @covers ::process
   */
  public function testProcessProducesNoLink(array $area_codes, string $input): void {
    $result = $this->createFilter($area_codes)->process($input, 'en');
    $this->assertStringNotContainsString('<a ', $result->getProcessedText());
  }

  /**
   * Data provider for testProcessProducesNoLink().
   *
   * Each entry is [area_codes, input].
   *
   * @return array<string, array{list<string>, string}>
   */
  public static function processProducesNoLinkProvider(): array {
    return [
      // Area code not in the configured list — must not be linked.
      'unlisted area code' => [['800', '888'], '999-888-8888'],

      // 7-digit number with no area code — regex requires a 3-digit area code.
      'seven digit number' => [['800', '888'], '555-1234'],

      // Empty string — nothing to process.
      'empty string' => [['800', '888'], ''],

      // Plain text with no phone number at all.
      'no phone number in text' => [['800', '888'], '<p>No phone number here.</p>'],

      // Lowercase vanity — [A-Z0-9] does not match lowercase letters.
      'vanity flowers lowercase' => [['800', '888'], 'Call 800-flowers today'],

      // Mixed-case vanity — partial uppercase is not enough for a full match.
      'vanity Flowers mixed case' => [['800', '888'], 'Call 800-Flowers today'],
    ];
  }

  // ---------------------------------------------------------------------------
  // process() — anchor count assertions
  // ---------------------------------------------------------------------------

  /**
   * Tests the number of <a> elements produced for various inputs.
   *
   * @dataProvider processAnchorCountProvider
   * @covers ::process
   */
  public function testProcessAnchorCount(array $area_codes, string $input, int $expected_count): void {
    $result = $this->createFilter($area_codes)->process($input, 'en');
    $this->assertSame($expected_count, substr_count($result->getProcessedText(), '<a '));
  }

  /**
   * Data provider for testProcessAnchorCount().
   *
   * Each entry is [area_codes, input, expected_anchor_count].
   *
   * @return array<string, array{list<string>, string, int}>
   */
  public static function processAnchorCountProvider(): array {
    return [
      // A number already inside an <a> tag must not be double-wrapped.
      // The DOMDocument walk detects the <a> ancestor and skips the text node.
      'no double wrap direct anchor' => [
        ['800', '888'],
        '<a href="tel:+18888888888">888-888-8888</a>',
        1,
      ],

      // A number nested inside an inline element within an <a> must not be
      // double-wrapped. The ancestor check must walk all the way up the tree,
      // not just the immediate parent.
      'no double wrap nested inside anchor' => [
        ['800', '888'],
        '<a href="tel:+18888888888"><strong>888-888-8888</strong></a>',
        1,
      ],

      // A number inside a non-anchor inline element must be wrapped.
      // <strong> is not an anchor ancestor.
      'number in non-anchor inline element is wrapped' => [
        ['800', '888'],
        '<p>Call <strong>888-888-8888</strong> now.</p>',
        1,
      ],

      // Multiple numbers in a single string must each be wrapped independently.
      'multiple numbers each wrapped' => [
        ['800', '888'],
        'Call 888-888-8888 or 800-555-1234',
        2,
      ],

      // When only 800 is configured, the 888 number must not be linked.
      'only configured area code linked' => [
        ['800'],
        '800-555-1234 and 888-555-1234',
        1,
      ],

      // Empty area_codes array — all phone numbers are linked.
      'empty area codes links all numbers' => [
        [],
        'Call 999-888-8888',
        1,
      ],
    ];
  }

  // ---------------------------------------------------------------------------
  // process() — content preservation
  // ---------------------------------------------------------------------------

  /**
   * Tests content preservation through the DOMDocument round-trip.
   *
   * @dataProvider processPreservesContentProvider
   * @covers ::process
   */
  public function testProcessPreservesContent(array $area_codes, string $input, string $expected_href, string $expected_text): void {
    $result = $this->createFilter($area_codes)->process($input, 'en');
    $this->assertStringContainsString('href="' . $expected_href . '"', $result->getProcessedText());
    $this->assertStringContainsString($expected_text, $result->getProcessedText());
  }

  /**
   * Data provider for testProcessPreservesContent().
   *
   * Each entry is [area_codes, input, expected_href, expected_text].
   *
   * @return array<string, array{list<string>, string, string, string}>
   */
  public static function processPreservesContentProvider(): array {
    return [
      // Multiple valid area codes stored in non-alphabetical order still produce
      // links — the regex alternation matches any listed code regardless of order.
      'valid codes in arbitrary order produce links' => [
        ['888', '800'],
        '888-555-1234',
        'tel:+18885551234',
        '888-555-1234',
      ],

      // Multibyte / Unicode characters surrounding a phone number must survive
      // the Html::load() / Html::serialize() round-trip. The Masterminds HTML5
      // parser used internally by Html::load() handles UTF-8 natively.
      'multibyte content preserved' => [
        "800\n888",
        '<p>Appelez le 888-888-8888 s\'il vous plaît.</p>',
        'tel:+18888888888',
        'plaît',
      ],
    ];
  }

  // ---------------------------------------------------------------------------
  // vanityToDigits() — via ReflectionMethod
  // ---------------------------------------------------------------------------

  /**
   * Tests vanityToDigits() directly via reflection.
   *
   * @dataProvider vanityToDigitsProvider
   * @covers ::vanityToDigits
   */
  public function testVanityToDigits(string $input, string $expected): void {
    $filter = $this->createFilter();
    $method = new \ReflectionMethod($filter, 'vanityToDigits');
    $this->assertSame($expected, $method->invoke($filter, $input));
  }

  /**
   * Data provider for testVanityToDigits().
   *
   * vanityToDigits() is only called on segments that have already matched
   * [A-Z0-9], so inputs here are uppercase. The method internally calls
   * strtoupper() for safety, which is also verified here.
   *
   * @return array<string, array{string, string}>
   */
  public static function vanityToDigitsProvider(): array {
    return [
      // Pure digit string — must pass through unchanged.
      'all digits passthrough' => ['8888', '8888'],

      // Full word FLOWERS in uppercase.
      'FLOWERS uppercase' => ['FLOWERS', '3569377'],

      // Three-letter segment ASK.
      'ASK' => ['ASK', '275'],

      // Four-letter segment HELP.
      'HELP' => ['HELP', '4357'],

      // Each keypad row verified individually.
      'ABC maps to 2' => ['ABC', '222'],
      'DEF maps to 3' => ['DEF', '333'],
      'GHI maps to 4' => ['GHI', '444'],
      'JKL maps to 5' => ['JKL', '555'],
      'MNO maps to 6' => ['MNO', '666'],
      'PQRS maps to 7' => ['PQRS', '7777'],
      'TUV maps to 8' => ['TUV', '888'],
      'WXYZ maps to 9' => ['WXYZ', '9999'],
    ];
  }

  // ---------------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------------

  /**
   * Creates a TelephoneFilter instance configured with the given area codes.
   *
   * @param list<string> $area_codes
   *   Array of digit-only area code strings. Defaults to ['800', '888'].
   *   Pass [] to link all phone numbers regardless of area code.
   */
  private function createFilter(array $area_codes = ['800', '888']): TelephoneFilter {
    return new TelephoneFilter(
      ['area_codes' => $area_codes],
      'telephone_filter',
      ['provider' => 'telephone_filter'],
    );
  }

}
```

---

### tests/src/Unit/TelephoneFilterValidationTest.php

`UnitTestCase`. Dedicated to `validateConfigurationForm()` and `submitConfigurationForm()`. Asserts that invalid lines trigger a `FormState` error and that valid inputs (including empty textarea) produce no error. Uses `Drupal\Core\Form\FormState` to invoke each method exactly as Drupal would; reads back `settings['area_codes']` via `\ReflectionProperty` where needed.

```php
<?php

declare(strict_types=1);

namespace Drupal\Tests\telephone_filter\Unit;

use Drupal\Core\Form\FormState;
use Drupal\telephone_filter\Plugin\Filter\TelephoneFilter;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for TelephoneFilter area-code validation.
 *
 * Covers validateConfigurationForm() — which sets a FormState error for any
 * line that is not exactly 3 digits — and the downstream effect on
 * submitConfigurationForm(), which stores only non-empty lines once
 * validation has passed.
 *
 * @coversDefaultClass \Drupal\telephone_filter\Plugin\Filter\TelephoneFilter
 * @group telephone_filter
 */
class TelephoneFilterValidationTest extends UnitTestCase {

  // ---------------------------------------------------------------------------
  // validateConfigurationForm() — error produced for invalid input
  // ---------------------------------------------------------------------------

  /**
   * Tests that an invalid area-code line triggers a form error.
   *
   * @dataProvider invalidAreaCodesProvider
   * @covers ::validateConfigurationForm
   *
   * @param string $textarea_value
   *   Raw textarea input that contains at least one invalid line.
   * @param string $expected_fragment
   *   A substring that must appear in the first error message.
   */
  public function testValidateConfigurationFormSetsError(
    string $textarea_value,
    string $expected_fragment,
  ): void {
    $filter = $this->createFilter();
    $form_state = new FormState();
    $form_state->setValue(
      ['filters', 'telephone_filter', 'settings', 'area_codes'],
      $textarea_value,
    );

    $form = [];
    $filter->validateConfigurationForm($form, $form_state);

    $errors = $form_state->getErrors();
    $this->assertNotEmpty($errors, 'Expected a form error but none was set.');

    $error_text = implode(' ', array_map('strval', $errors));
    $this->assertStringContainsString($expected_fragment, $error_text);
  }

  /**
   * Data provider for testValidateConfigurationFormSetsError().
   *
   * Each entry is [textarea_value, expected_fragment_in_error_message].
   *
   * @return array<string, array{string, string}>
   */
  public static function invalidAreaCodesProvider(): array {
    return [

      // A letter-only string is not a valid area code; it appears in the error.
      'letters only' => ['ABC', 'ABC'],

      // An alphanumeric string is rejected; the offending value is reported.
      'alphanumeric' => ['80A', '80A'],

      // A plus-prefixed string is rejected.
      'plus prefix' => ['+800', '+800'],

      // A hyphenated string is rejected.
      'hyphen' => ['800-888', '800-888'],

      // A 2-digit string fails the length check.
      'two digits' => ['80', '80'],

      // A 4-digit string fails the length check.
      'four digits' => ['8008', '8008'],

      // A 10-digit string (full phone number) fails the length check.
      'ten digits' => ['8008888888', '8008888888'],

      // When the first line is valid but a later line is invalid, an error is
      // still set. The invalid line text appears in the error message.
      'invalid after valid' => ["800\n80A\n888", '80A'],

      // An invalid line among otherwise blank lines is still caught.
      'invalid among blank lines' => ["\n\nABC\n", 'ABC'],

    ];
  }

  // ---------------------------------------------------------------------------
  // validateConfigurationForm() — no error for valid input
  // ---------------------------------------------------------------------------

  /**
   * Tests that valid textarea input produces no form error.
   *
   * @dataProvider validAreaCodesProvider
   * @covers ::validateConfigurationForm
   *
   * @param string $textarea_value
   *   Raw textarea input containing only valid (or blank) lines.
   */
  public function testValidateConfigurationFormNoError(string $textarea_value): void {
    $filter = $this->createFilter();
    $form_state = new FormState();
    $form_state->setValue(
      ['filters', 'telephone_filter', 'settings', 'area_codes'],
      $textarea_value,
    );

    $form = [];
    $filter->validateConfigurationForm($form, $form_state);

    $this->assertEmpty($form_state->getErrors(), 'Expected no form errors but errors were set.');
  }

  /**
   * Data provider for testValidateConfigurationFormNoError().
   *
   * @return array<string, array{string}>
   */
  public static function validAreaCodesProvider(): array {
    return [
      // Empty textarea — "link all" mode, no validation required.
      'empty textarea' => [''],

      // Single valid code.
      'single valid code' => ['800'],

      // Multiple valid codes on separate lines.
      'multiple valid codes' => ["800\n888\n877"],

      // Valid codes with surrounding whitespace — trim() normalises them.
      'whitespace around code' => ["  800  \n888"],

      // Windows-style CRLF endings — trim() strips the \r.
      'crlf line endings' => ["800\r\n888\r\n877"],

      // Blank lines interspersed with valid codes are skipped.
      'blank lines between valid codes' => ["\n800\n\n888\n\n"],

      // Whitespace-only lines are treated as blank and skipped.
      'whitespace only lines' => ["   \n800\n   \n888"],
    ];
  }

  // ---------------------------------------------------------------------------
  // submitConfigurationForm() — stored value after a clean (no-error) save
  // ---------------------------------------------------------------------------

  /**
   * Tests that submitConfigurationForm() stores only non-empty trimmed lines.
   *
   * validateConfigurationForm() is presumed to have already run and passed,
   * so every non-blank line here is a valid 3-digit code. The submit handler
   * is responsible only for stripping blank lines.
   *
   * @dataProvider submitStoresValidCodesProvider
   * @covers ::submitConfigurationForm
   *
   * @param string $textarea_value
   *   Raw textarea input (valid codes + optional blank lines).
   * @param list<string> $expected_codes
   *   The array that should be stored in settings after submission.
   */
  public function testSubmitConfigurationFormStoresCodes(
    string $textarea_value,
    array $expected_codes,
  ): void {
    $filter = $this->createFilter();
    $form_state = new FormState();
    $form_state->setValue(
      ['filters', 'telephone_filter', 'settings', 'area_codes'],
      $textarea_value,
    );

    $form = [];
    $filter->submitConfigurationForm($form, $form_state);

    $reflection = new \ReflectionProperty($filter, 'settings');
    $settings = $reflection->getValue($filter);

    $this->assertSame($expected_codes, $settings['area_codes']);
  }

  /**
   * Data provider for testSubmitConfigurationFormStoresCodes().
   *
   * @return array<string, array{string, list<string>}>
   */
  public static function submitStoresValidCodesProvider(): array {
    return [
      // Empty textarea stores an empty array — enables "link all" mode.
      'empty stores empty array' => ['', []],

      // Single valid code stored as one-element array.
      'single valid code' => ['800', ['800']],

      // Two valid codes on separate lines.
      'two valid codes' => ["800\n888", ['800', '888']],

      // Blank lines are stripped; valid codes are preserved.
      'blank lines stripped' => ["\n800\n\n888\n\n", ['800', '888']],

      // Whitespace around codes is trimmed before storage.
      'whitespace trimmed' => ["  800  \n888", ['800', '888']],

      // Windows CRLF endings produce the same result as LF.
      'crlf endings' => ["800\r\n888\r\n877", ['800', '888', '877']],
    ];
  }

  // ---------------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------------

  /**
   * Creates a TelephoneFilter instance with an empty area_codes setting.
   */
  private function createFilter(): TelephoneFilter {
    return new TelephoneFilter(
      ['area_codes' => []],
      'telephone_filter',
      ['provider' => 'telephone_filter'],
    );
  }

}
```
---

## Reference

### Drupal APIs

- [Html](https://api.drupal.org/api/drupal/core!lib!Drupal!Component!Utility!Html.php/class/Html/11.x) — `Html::load()` / `Html::serialize()` for DOM parsing and serialization
- [FilterBase](https://api.drupal.org/api/drupal/core!modules!filter!src!Plugin!FilterBase.php/class/FilterBase/11.x) — base class for text format filter plugins
- [FilterInterface](https://api.drupal.org/api/drupal/core!modules!filter!src!Plugin!FilterInterface.php/interface/FilterInterface/11.x) — filter plugin interface and type constants
- [FilterProcessResult](https://api.drupal.org/api/drupal/core!modules!filter!src!FilterProcessResult.php/class/FilterProcessResult/11.x) — return value of `process()`
- [Filter attribute](https://api.drupal.org/api/drupal/core!modules!filter!src!Attribute!Filter.php/class/Filter/11.x) — PHP 8 attribute for registering filter plugins (replaces annotation)

### Drupal.org Project Setup

- [GitLab CI — Using GitLab to contribute to Drupal](https://www.drupal.org/docs/develop/git/using-gitlab-to-contribute-to-drupal/gitlab-ci) — configuring automated testing via `.gitlab-ci.yml` and the Drupal Association's maintained template
- [Project Browser — Module logo](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/project-browser/module-maintainers-how-to-update-projects-to-be-compatible-with-project-browser#s-logo) — `logo.png` specification and requirements for Project Browser display
