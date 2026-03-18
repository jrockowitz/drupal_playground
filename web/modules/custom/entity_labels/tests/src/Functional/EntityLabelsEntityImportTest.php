<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_labels\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Functional tests for the entity-labels entity CSV import form.
 *
 * Verifies that the import form is accessible, that uploading a valid CSV
 * updates bundle labels, and that malformed CSV surfaces an error message.
 *
 * @group entity_labels
 */
#[Group('entity_labels')]
#[RunTestsInSeparateProcesses]
class EntityLabelsEntityImportTest extends EntityLabelsTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'file', 'entity_labels'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin(
      $this->drupalCreateUser(['access site reports', 'administer site configuration'])
    );
  }

  /**
   * Tests that the import form loads and handles empty submission gracefully.
   */
  public function testImportForm(): void {
    // Check that the import form loads and shows the submit button.
    $this->drupalGet('admin/reports/entity-labels/entity/import');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->buttonExists('Import CSV');

    // Check that submitting without a file is handled gracefully.
    $this->submitForm([], 'Import CSV');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->statusMessageNotExists('error');
  }

  /**
   * Tests CSV upload behaviour for valid and malformed files.
   */
  public function testImportCsv(): void {
    // Check that a valid CSV import shows a success status message.
    $csv_content = "langcode,entity_type,bundle,label,description,help\n"
      . "en,node,article,Updated Article,Updated description,\n";

    $this->drupalGet('admin/reports/entity-labels/entity/import');
    $this->submitForm(
      ['files[csv_upload]' => $this->writeTemporaryCsv($csv_content)],
      'Import CSV',
    );
    $this->assertSession()->statusMessageContains('updated', 'status');

    // Check that a CSV with missing required headers shows an error.
    $csv_content = "entity_type,bundle\nnode,article\n";

    $this->drupalGet('admin/reports/entity-labels/entity/import');
    $this->submitForm(
      ['files[csv_upload]' => $this->writeTemporaryCsv($csv_content)],
      'Import CSV',
    );
    $this->assertSession()->statusMessageExists('error');
  }

}
