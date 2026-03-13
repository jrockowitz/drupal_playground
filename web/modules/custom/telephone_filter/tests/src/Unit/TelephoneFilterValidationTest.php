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
   * @param string $textarea_value
   *   Raw textarea input that contains at least one invalid line.
   * @param string $expected_fragment
   *   A substring that must appear in the first error message.
   *
   * @dataProvider invalidAreaCodesProvider
   * @covers ::validateConfigurationForm
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
   * @return array
   *   Test data.
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
   * @param string $textarea_value
   *   Raw textarea input containing only valid (or blank) lines.
   *
   * @dataProvider validAreaCodesProvider
   * @covers ::validateConfigurationForm
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
   * @return array
   *   Test data.
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
   * ValidateConfigurationForm() is presumed to have already run and passed,
   * so every non-blank line here is a valid 3-digit code. The submit handler
   * is responsible only for stripping blank lines.
   *
   * @param string $textarea_value
   *   Raw textarea input (valid codes + optional blank lines).
   * @param list<string> $expected_codes
   *   The array that should be stored in settings after submission.
   *
   * @dataProvider submitStoresValidCodesProvider
   * @covers ::submitConfigurationForm
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
   * @return array
   *   Test data.
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
    $filter = new TelephoneFilter(
      ['settings' => ['area_codes' => []]],
      'telephone_filter',
      ['provider' => 'telephone_filter'],
    );
    $filter->setStringTranslation($this->getStringTranslationStub());
    return $filter;
  }

}
