<?php

declare(strict_types=1);

namespace Drupal\Tests\telephone_filter\Unit;

use Drupal\Core\Form\FormState;
use Drupal\telephone_filter\Plugin\Filter\TelephoneFilter;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for TelephoneFilter area-code validation.
 *
 * Covers validateAreaCodes() — which sets a FormState error for any
 * comma-separated token that is not exactly 3 digits — and the downstream
 * effect on submitConfigurationForm(), which stores a clean comma-delimited
 * string once validation has passed.
 *
 * @coversDefaultClass \Drupal\telephone_filter\Plugin\Filter\TelephoneFilter
 * @group telephone_filter
 */
class TelephoneFilterValidationTest extends UnitTestCase {

  // ---------------------------------------------------------------------------
  // validateAreaCodes() — error produced for invalid input
  // ---------------------------------------------------------------------------

  /**
   * Tests that an invalid area-code token triggers a form error.
   *
   * @param string $field_value
   *   Raw textfield input that contains at least one invalid token.
   * @param string $expected_fragment
   *   A substring that must appear in the first error message.
   *
   * @dataProvider invalidAreaCodesProvider
   * @covers ::validateAreaCodes
   */
  public function testValidateAreaCodesSetsError(
    string $field_value,
    string $expected_fragment,
  ): void {
    $form_state = new FormState();
    $element = ['#value' => $field_value, '#parents' => ['area_codes']];

    TelephoneFilter::validateAreaCodes($element, $form_state);

    $errors = $form_state->getErrors();
    $this->assertNotEmpty($errors, 'Expected a form error but none was set.');

    $error_text = implode(' ', array_map('strval', $errors));
    $this->assertStringContainsString($expected_fragment, $error_text);
  }

  /**
   * Data provider for testValidateAreaCodesSetsError().
   *
   * Each entry is [field_value, expected_fragment_in_error_message].
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

      // When the first token is valid but a later token is invalid, an error is
      // still set. The invalid token text appears in the error message.
      'invalid after valid' => ['800,80A,888', '80A'],

      // An invalid token among otherwise blank tokens is still caught.
      'invalid among blank tokens' => [',ABC,', 'ABC'],

    ];
  }

  // ---------------------------------------------------------------------------
  // validateAreaCodes() — no error for valid input
  // ---------------------------------------------------------------------------

  /**
   * Tests that valid textfield input produces no form error.
   *
   * @param string $field_value
   *   Raw textfield input containing only valid (or blank) tokens.
   *
   * @dataProvider validAreaCodesProvider
   * @covers ::validateAreaCodes
   */
  public function testValidateAreaCodesNoError(string $field_value): void {
    $form_state = new FormState();
    $element = ['#value' => $field_value, '#parents' => ['area_codes']];

    TelephoneFilter::validateAreaCodes($element, $form_state);

    $this->assertEmpty($form_state->getErrors(), 'Expected no form errors but errors were set.');
  }

  /**
   * Data provider for testValidateAreaCodesNoError().
   *
   * @return array
   *   Test data.
   */
  public static function validAreaCodesProvider(): array {
    return [
      // Empty field — "link all" mode, no validation required.
      'empty field' => [''],

      // Single valid code.
      'single valid code' => ['800'],

      // Multiple valid codes, comma-separated.
      'multiple valid codes' => ['800,888,877'],

      // Valid codes with surrounding whitespace — trim() normalises them.
      'whitespace around code' => ['  800  ,888'],

      // Blank tokens from extra commas are skipped.
      'blank tokens between valid codes' => [',800,,888,'],

      // Whitespace-only tokens are treated as blank and skipped.
      'whitespace only tokens' => ['   ,800,   ,888'],
    ];
  }

  // ---------------------------------------------------------------------------
  // submitConfigurationForm() — stored value after a clean (no-error) save
  // ---------------------------------------------------------------------------

  /**
   * Tests that submitConfigurationForm() stores only non-empty trimmed tokens.
   *
   * ValidateAreaCodes() is presumed to have already run and passed, so every
   * non-blank token here is a valid 3-digit code. The submit handler is
   * responsible only for stripping blank tokens and re-joining with commas.
   *
   * @param string $field_value
   *   Raw textfield input (valid codes + optional blank tokens).
   * @param string $expected_codes
   *   The string that should be stored in settings after submission.
   *
   * @dataProvider submitStoresValidCodesProvider
   * @covers ::submitConfigurationForm
   */
  public function testSubmitConfigurationFormStoresCodes(
    string $field_value,
    string $expected_codes,
  ): void {
    $filter = $this->createFilter();
    $form_state = new FormState();
    $form_state->setValue(
      ['filters', 'telephone_filter', 'settings', 'area_codes'],
      $field_value,
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
      // Empty field stores an empty string — enables "link all" mode.
      'empty stores empty string' => ['', ''],

      // Single valid code stored as-is.
      'single valid code' => ['800', '800'],

      // Two valid codes stored comma-delimited.
      'two valid codes' => ['800,888', '800,888'],

      // Blank tokens from extra commas are stripped.
      'blank tokens stripped' => [',800,,888,', '800,888'],

      // Whitespace around codes is trimmed before storage.
      'whitespace trimmed' => ['  800  ,888', '800,888'],

      // Three codes in order.
      'three codes' => ['800,888,877', '800,888,877'],
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
      ['settings' => ['area_codes' => '']],
      'telephone_filter',
      ['provider' => 'telephone_filter'],
    );
    $filter->setStringTranslation($this->getStringTranslationStub());
    return $filter;
  }

}
