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
   * Each entry is [input, expected_href] using area codes ['800', '888'].
   *
   * @return array
   *   Test data.
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

      // Number at the very end — word boundary must fire at end of string.
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
   * @return array
   *   Test data.
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
   * @return array
   *   Test data.
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
   * @return array
   *   Test data.
   */
  public static function processPreservesContentProvider(): array {
    return [
      // Non-alphabetical area codes still produce links — alternation matches
      // any listed code regardless of order.
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
        ['800', '888'],
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
   * VanityToDigits() is only called on segments that have already matched
   * [A-Z0-9], so inputs here are uppercase. The method internally calls
   * strtoupper() for safety, which is also verified here.
   *
   * @return array
   *   Test data.
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
      ['settings' => ['area_codes' => $area_codes]],
      'telephone_filter',
      ['provider' => 'telephone_filter'],
    );
  }

}
