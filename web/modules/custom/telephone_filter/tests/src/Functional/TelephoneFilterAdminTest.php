<?php

declare(strict_types=1);

namespace Drupal\Tests\telephone_filter\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Functional tests for TelephoneFilter settings form administration.
 *
 * Verifies that the filter settings form renders correctly after saving a
 * text format and that the #element_validate callback fires in the real UI.
 *
 * @group telephone_filter
 */
#[Group('telephone_filter')]
#[RunTestsInSeparateProcesses]
class TelephoneFilterAdminTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['filter', 'telephone_filter'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->drupalCreateUser(['administer filters']));
  }

  /**
   * Tests that the settings form renders after saving area codes.
   *
   * Exercises the full round-trip: create format → save with area codes →
   * reload settings form. Verifies the stored value is displayed correctly.
   */
  public function testSettingsFormRendersAfterSave(): void {
    $format_id = $this->randomMachineName();

    $this->drupalGet('admin/config/content/formats/add');
    $this->submitForm([
      'format' => $format_id,
      'name' => $this->randomMachineName(),
      'filters[telephone_filter][status]' => TRUE,
      'filters[telephone_filter][settings][area_codes]' => '800,888',
    ], 'Save configuration');
    $this->assertSession()->statusMessageNotExists('error');

    // Reload the edit form — verify the stored value is shown.
    $this->drupalGet('admin/config/content/formats/manage/' . $format_id);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals(
      'filters[telephone_filter][settings][area_codes]',
      '800,888',
    );
  }

  /**
   * Tests that the settings form renders when area codes are empty.
   *
   * Empty value must be stored and displayed as an empty string.
   */
  public function testSettingsFormRendersWithEmptyAreaCodes(): void {
    $format_id = $this->randomMachineName();

    $this->drupalGet('admin/config/content/formats/add');
    $this->submitForm([
      'format' => $format_id,
      'name' => $this->randomMachineName(),
      'filters[telephone_filter][status]' => TRUE,
      'filters[telephone_filter][settings][area_codes]' => '',
    ], 'Save configuration');
    $this->assertSession()->statusMessageNotExists('error');

    // Reload — empty string must be shown.
    $this->drupalGet('admin/config/content/formats/manage/' . $format_id);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals(
      'filters[telephone_filter][settings][area_codes]',
      '',
    );
  }

  /**
   * Tests that an invalid area code token surfaces a validation error.
   *
   * Confirms that validateAreaCodes() is actually invoked by the form system
   * via #element_validate and that the error message reaches the user.
   */
  public function testInvalidAreaCodeShowsError(): void {
    $this->drupalGet('admin/config/content/formats/add');
    $this->submitForm([
      'format' => $this->randomMachineName(),
      'name' => $this->randomMachineName(),
      'filters[telephone_filter][status]' => TRUE,
      'filters[telephone_filter][settings][area_codes]' => '800,INVALID,888',
    ], 'Save configuration');
    $this->assertSession()->statusMessageExists('error');
    $this->assertSession()->pageTextContains('INVALID');
  }

}
