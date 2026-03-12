# Telephone Filter Module — Build Specification

## Overview

A Drupal 11 custom module (`telephone_filter`) that converts US phone numbers found in rendered HTML into clickable `tel:` links. Implemented as a Drupal **text format filter plugin** — the same approach Jacob Rockowitz used when building it with ChatGPT 4o.

> **Reference:** [Building a Drupal Module Using AI](https://www.jrockowitz.com/blog/building-a-drupal-model-using-al) — Jacob Rockowitz documents his prompt-engineering workflow, the iterative refinement process, and the ~80/20 split between AI generation and human tweaking.

This is the first custom module in the playground. It establishes the pattern for custom module structure and testing conventions.

---

## Requirements

### Functional

1. Detects US phone numbers in rendered HTML, **not** inside existing `<a>` tags.
2. Wraps matches in `<a href="tel:+1XXXXXXXXXX">original text</a>` links.
3. Supported numeric formats: `888-888-8888`, `888.888.8888`, `(888) 888-8888`.
4. Supported vanity formats: `800-FLOWERS`, `800-ASK-HELP`.
5. Configurable list of allowed area codes (default: `800`, `888`) — one per line in a textarea.
6. Skips numbers already inside `<a>` tags to prevent double-wrapping.
7. Regex area-code alternation is built dynamically from the plugin settings at process time.

### Non-Functional

- `declare(strict_types=1)` in every PHP file.
- Fully typed method signatures throughout.
- No `\Drupal::service()` static calls inside class methods — use constructor injection or the `FilterBase` base class helpers.
- PHPCS `Drupal` / `DrupalPractice` clean.
- PHPStan level 5+ clean.
- Filter type: `FilterInterface::TYPE_TRANSFORM_REVERSIBLE`.

---

## Steps to Review (⚫ = step  ✅ = pass  ❌ = fail)

| # | Step | Status |
|---|---|---|
| 1 | `telephone_filter.info.yml` exists and is valid | ⚫ |
| 2 | `telephone_filter.module` implements `hook_help()` | ⚫ |
| 3 | `config/schema/telephone_filter.schema.yml` defines filter settings | ⚫ |
| 4 | `TelephoneFilter.php` discovered as a filter plugin | ⚫ |
| 5 | Settings form renders area codes textarea | ⚫ |
| 6 | Numeric formats produce correct `tel:` links | ⚫ |
| 7 | Vanity numbers produce correct digit-only `tel:` links | ⚫ |
| 8 | Numbers inside `<a>` tags are not double-wrapped | ⚫ |
| 9 | Numbers with unlisted area codes are not wrapped | ⚫ |
| 10 | Unit tests pass | ⚫ |
| 11 | PHPCS reports no errors | ⚫ |

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
       DOMDocument::loadHTML($text)
                  ↓
        Walk all DOMText nodes
                  ↓
        Skip nodes with <a> ancestor
                  ↓
       Apply phone regex to node value
                  ↓
   Split text node → insert <a> DOM nodes
                  ↓
        saveHTML() → FilterProcessResult
```

### Phone Regex

```
/\b(AREA_CODES)\s*[-.]?\s*([A-Z0-9]{3})\s*[-.]?\s*([A-Z0-9]{4})\b/i
```

`AREA_CODES` is replaced at runtime with a `|`-joined alternation built from `$this->settings['area_codes']` (e.g. `800|888`). Each line in the textarea becomes one alternation arm; empty lines and whitespace are stripped.

The three capture groups correspond to:
1. Area code
2. Exchange (first 3 digits/letters)
3. Subscriber (last 4 digits/letters)

### DOMDocument Approach

`\DOMDocument::loadHTML()` is used to parse the HTML fragment. An `XPath` query or recursive tree walk identifies all `DOMText` nodes. Before processing a text node, the code walks `$node->parentNode` up the tree checking for any `<a>` ancestor — if found, the node is skipped.

For each match within a text node:
1. Split the text node around the match using `splitText()`.
2. Create an `<a>` element with `href="tel:+1{digits}"`.
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

---

## Module File Structure

```
web/modules/custom/telephone_filter/
├── composer.json
├── README.md
├── AGENTS.md                              (generated via `claude init`)
├── telephone_filter.info.yml              ⬜ todo
├── telephone_filter.module                ⬜ todo
├── config/
│   └── schema/
│       └── telephone_filter.schema.yml    ⬜ todo
└── src/
    └── Plugin/
        └── Filter/
            └── TelephoneFilter.php        ⬜ todo
tests/
└── src/
    └── Unit/
        └── TelephoneFilterTest.php        ⬜ todo
```

---

## Implementation

Files listed in creation order: scaffolding → config → plugin → tests.

---

### README.md

```markdown
# Telephone Filter

A Drupal 11 text format filter plugin that converts US phone numbers in HTML
text into clickable `tel:` links.

## Features

- Detects numeric formats: `888-888-8888`, `888.888.8888`, `(888) 888-8888`.
- Detects vanity formats: `800-FLOWERS`, `800-ASK-HELP`.
- Configurable list of allowed area codes (default: `800`, `888`).
- Skips numbers already inside `<a>` tags — no double-wrapping.
- Safe DOM-based HTML manipulation via `DOMDocument`.

## Requirements

- Drupal core 11.x
- PHP 8.3+

## Installation

```bash
drush en telephone_filter
```

Enable the filter on a text format at `/admin/config/content/formats`.

## Development

See [AGENTS.md](AGENTS.md) for AI-assisted development guidelines.
```

---

### AGENTS.md

Do not hand-author this file. After the module directory is created and initial code is in place, generate it by running:

```bash
claude init
```

`claude init` inspects the codebase and produces an `AGENTS.md` tailored to the actual file structure, coding patterns, and architecture it finds.

---

### composer.json

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
        "drupal/core": "^11"
    }
}
```

---

### telephone_filter.info.yml ✅

Already created. No changes needed.

```yaml
name: Telephone Filter
type: module
description: 'Converts US phone numbers in text to clickable tel: links.'
package: Custom
core_version_requirement: ^10.3 || ^11
php: '8.3'
```

---

### telephone_filter.module ✅

Already created. Implements `hook_help()` only.

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
      type: string
      label: 'Allowed area codes (one per line)'
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
 *   - Vanity:   800-FLOWERS  | 800-ASK-HELP
 *
 * Numbers already inside <a> tags are never double-wrapped.
 * Only numbers whose area code appears in the configured list are linked.
 */
#[Filter(
  id: 'telephone_filter',
  title: new TranslatableMarkup('Telephone filter'),
  description: new TranslatableMarkup('Converts US phone numbers to clickable tel: links.'),
  type: FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
  weight: 10,
)]
class TelephoneFilter extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['area_codes' => "800\n888"] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form['area_codes'] = [
      '#type'          => 'textarea',
      '#title'         => $this->t('Allowed area codes'),
      '#description'   => $this->t('Enter one area code per line. Only phone numbers whose area code appears in this list will be converted to links.'),
      '#default_value' => $this->settings['area_codes'],
      '#rows'          => 5,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function process(string $text, string $langcode): FilterProcessResult {
    $area_codes = $this->getAreaCodeAlternation();

    if ($area_codes === '') {
      return new FilterProcessResult($text);
    }

    // Pattern explanation:
    //   \b              — word boundary to avoid partial matches
    //   (AREA_CODES)    — allowed area code alternation built from settings
    //   \s*[-.]?\s*     — optional separator (space, dash, or dot) with surrounding whitespace
    //   ([A-Z0-9]{3})   — exchange: 3 alphanumeric characters (vanity or digits)
    //   \s*[-.]?\s*     — optional separator
    //   ([A-Z0-9]{4})   — subscriber: 4 alphanumeric characters (vanity or digits)
    //   \b              — word boundary
    $pattern = '/\b(' . $area_codes . ')\s*[-.]?\s*([A-Z0-9]{3})\s*[-.]?\s*([A-Z0-9]{4})\b/i';

    $document = new \DOMDocument();
    // Use UTF-8 charset declaration so loadHTML does not mangle multibyte chars.
    @$document->loadHTML(
      '<?xml encoding="utf-8"?>' . $text,
      LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
    );

    $this->processNode($document, $document, $pattern);

    // saveHTML() on the document returns the full document; strip the XML
    // declaration and whitespace that loadHTML may have added.
    $processed = $document->saveHTML();
    $processed = preg_replace('/<\?xml[^?]*\?>\s*/i', '', $processed) ?? $processed;

    return new FilterProcessResult($processed);
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
      $area    = $this->vanityToDigits($matches[1][$index][0]);
      $exchange = $this->vanityToDigits($matches[2][$index][0]);
      $subscriber = $this->vanityToDigits($matches[3][$index][0]);
      $digits  = $area . $exchange . $subscriber;

      $anchor = $document->createElement('a', $matched_text);
      $anchor->setAttribute('href', 'tel:+1' . $digits);
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
   * @param string $number  A single phone segment (area code, exchange, or subscriber).
   * @return string         The segment with all letters converted to digits.
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
   * Builds the regex area-code alternation string from the plugin settings.
   *
   * Returns an empty string when no valid area codes are configured,
   * in which case process() returns the text unmodified.
   */
  private function getAreaCodeAlternation(): string {
    $raw_codes = $this->settings['area_codes'] ?? '';
    $codes = array_filter(
      array_map('trim', explode("\n", $raw_codes)),
      static fn (string $code): bool => $code !== '' && ctype_digit($code),
    );
    return implode('|', array_map('preg_quote', $codes, array_fill(0, count($codes), '/')));
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

### tests/src/Unit/TelephoneFilterTest.php

- **Namespace:** `Drupal\Tests\telephone_filter\Unit`
- **Extends:** `\Drupal\Tests\UnitTestCase`
- Uses `\ReflectionMethod` to exercise `vanityToDigits()` directly as it is private.
- Uses `@dataProvider` for all `process()` test cases.

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

  /**
   * Creates a TelephoneFilter instance configured with the given area codes.
   *
   * @param string $area_codes  Newline-separated area codes.
   */
  private function createFilter(string $area_codes = "800\n888"): TelephoneFilter {
    return new TelephoneFilter(
      ['area_codes' => $area_codes],
      'telephone_filter',
      ['provider' => 'telephone_filter'],
    );
  }

  /**
   * Data provider for testProcess().
   *
   * @return array<string, array{string, string}>
   */
  public static function processProvider(): array {
    return [
      'dash format'              => ['Call 888-888-8888 now', 'tel:+18888888888'],
      'parentheses format'       => ['Call (800) 555-1234 now', 'tel:+18005551234'],
      'dot format'               => ['Call 888.888.8888 now', 'tel:+18888888888'],
      'vanity FLOWERS'           => ['Call 800-FLOWERS today', 'tel:+18003569377'],
      'vanity ASK-HELP'          => ['Call 800-ASK-HELP', 'tel:+18002754357'],
    ];
  }

  /**
   * Tests that phone numbers are correctly converted to tel: links.
   *
   * @dataProvider processProvider
   * @covers ::process
   */
  public function testProcess(string $input, string $expected_href): void {
    $result = $this->createFilter()->process($input, 'en');
    $this->assertStringContainsString('href="' . $expected_href . '"', $result->getProcessedText());
  }

  /**
   * Tests that numbers inside existing <a> tags are not double-wrapped.
   *
   * @covers ::process
   */
  public function testNoDoubleWrap(): void {
    $input = '<a href="tel:+18888888888">888-888-8888</a>';
    $result = $this->createFilter()->process($input, 'en');
    // Should contain exactly one <a> element.
    $this->assertSame(1, substr_count($result->getProcessedText(), '<a '));
  }

  /**
   * Tests that unlisted area codes are not wrapped.
   *
   * @covers ::process
   */
  public function testUnlistedAreaCodeNotWrapped(): void {
    $result = $this->createFilter()->process('999-888-8888', 'en');
    $this->assertStringNotContainsString('<a ', $result->getProcessedText());
  }

  /**
   * Tests that multiple numbers in one string are each wrapped independently.
   *
   * @covers ::process
   */
  public function testMultipleNumbersWrapped(): void {
    $input = 'Call 888-888-8888 or 800-555-1234';
    $result = $this->createFilter()->process($input, 'en');
    $this->assertSame(2, substr_count($result->getProcessedText(), '<a '));
  }

  /**
   * Data provider for testVanityToDigits().
   *
   * @return array<string, array{string, string}>
   */
  public static function vanityToDigitsProvider(): array {
    return [
      'all digits passthrough'  => ['8888', '8888'],
      'FLOWERS'                 => ['FLOWERS', '3569377'],
      'ASK'                     => ['ASK', '275'],
      'HELP'                    => ['HELP', '4357'],
      'lowercase letters'       => ['flowers', '3569377'],
      'mixed case'              => ['fLoWeRs', '3569377'],
    ];
  }

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

}
```

#### Test cases summary

| # | Input | Expected outcome |
|---|---|---|
| 1 | `888-888-8888` | Wrapped in `<a href="tel:+18888888888">` |
| 2 | `(800) 555-1234` | Wrapped in `<a href="tel:+18005551234">` |
| 3 | `888.888.8888` | Wrapped in `<a href="tel:+18888888888">` |
| 4 | `800-FLOWERS` | Wrapped in `<a href="tel:+18003569377">` |
| 5 | `800-ASK-HELP` | Wrapped in `<a href="tel:+18002754357">` |
| 6 | `<a href="tel:...">888-888-8888</a>` | **Not** double-wrapped |
| 7 | `999-888-8888` (area code not in list) | **Not** wrapped |
| 8 | Multiple numbers in one string | Each wrapped independently |

---

## Skills

The following `/skills` should be loaded when using Claude Code to generate the `telephone_filter` module. Each skill provides domain knowledge or coding patterns that directly inform one or more files.

| Skill | Role |
|---|---|
| `drupal-at-your-fingertips` | Filter plugin boilerplate (`FilterBase`, `#[Filter]` attribute, `settingsForm()`, `FilterProcessResult`), hook patterns, plugin system conventions |
| `ivangrynenko-cursorrules-drupal` | XSS-safe HTML output, input sanitisation, OWASP patterns — ensures `DOMDocument` output is not re-injecting unsanitised strings |
| `drupal-ddev` | Commands for enabling the module, running unit tests with `phpunit`, enabling Xdebug for step-debugging the filter pipeline |
| `drupal-config-mgmt` | Exporting and inspecting the text format config after wiring up the filter; verifying `filter.format.*.yml` changes are captured in config sync |
| `simplify` | Post-implementation review pass — checks for DRY violations, overly complex regex handling, and any redundant DOM manipulation |

### How to invoke skills before generating code

```
/drupal-at-your-fingertips
/ivangrynenko-cursorrules-drupal
```

Load both before asking Claude to generate `TelephoneFilter.php`. The skills load relevant Drupal filter plugin boilerplate and security patterns into context, which significantly improves the quality of the first generation pass.

After generation, run:

```
/simplify
```

to review the produced code for unnecessary complexity, missed abstractions, or style violations before committing.

---

## Verification Commands

```bash
# Enable the module
ddev drush en telephone_filter -y

# Check for errors in the log
ddev drush watchdog:show --count=20

# Run unit tests
ddev exec vendor/bin/phpunit web/modules/custom/telephone_filter/tests/

# Check code style
ddev exec vendor/bin/phpcs --standard=Drupal web/modules/custom/telephone_filter/

# Auto-fix style violations
ddev exec vendor/bin/phpcbf --standard=Drupal web/modules/custom/telephone_filter/

# Static analysis
ddev exec vendor/bin/phpstan analyse web/modules/custom/telephone_filter/

# Open the text formats admin page to wire up the filter
ddev launch /admin/config/content/formats

# Export config after enabling the filter on a format
ddev drush config:export -y

# Inspect the saved filter config
ddev drush config:get filter.format.basic_html
```
