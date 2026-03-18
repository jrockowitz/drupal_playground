<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_labels\Functional;

use Drupal\Tests\BrowserTestBase;
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
class EntityLabelsEntityImportTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'entity_labels'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
    $this->drupalLogin(
      $this->drupalCreateUser(['access site reports', 'administer site configuration'])
    );
  }

  /**
   * Tests that the import form loads successfully.
   */
  public function testImportFormLoads(): void {
    $this->drupalGet('admin/reports/entity-labels/entity/import');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->buttonExists('Import CSV');
  }

  /**
   * Tests that submitting without a file shows no fatal errors.
   *
   * The form should submit cleanly (no uploaded file means nothing changes).
   */
  public function testImportWithoutFileIsHandledGracefully(): void {
    $this->drupalGet('admin/reports/entity-labels/entity/import');
    $this->submitForm([], 'Import CSV');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->statusMessageNotExists('error');
  }

  /**
   * Tests that uploading a valid CSV updates the bundle label.
   *
   * Uploads a CSV row for node/article and verifies the success status
   * message reports at least one updated row.
   */
  public function testValidCsvImportShowsSuccessMessage(): void {
    $csv_content = "langcode,entity_type,bundle,label,description,help\n"
      . "en,node,article,Updated Article,Updated description,\n";

    $file_path = $this->writeTemporaryCsv($csv_content);

    $this->drupalGet('admin/reports/entity-labels/entity/import');
    $this->submitForm(
      ['files[csv_upload]' => $file_path],
      'Import CSV',
    );
    $this->assertSession()->statusMessageContains('updated', 'status');
  }

  /**
   * Tests that a CSV with missing required headers shows an error message.
   */
  public function testMissingHeadersCsvShowsError(): void {
    $csv_content = "entity_type,bundle\nnode,article\n";

    $file_path = $this->writeTemporaryCsv($csv_content);

    $this->drupalGet('admin/reports/entity-labels/entity/import');
    $this->submitForm(
      ['files[csv_upload]' => $file_path],
      'Import CSV',
    );
    $this->assertSession()->statusMessageExists('error');
  }

  /**
   * Writes CSV content to a temporary file and returns its path.
   *
   * @param string $content
   *   The CSV content to write.
   *
   * @return string
   *   Absolute path to the temporary file.
   */
  private function writeTemporaryCsv(string $content): string {
    $path = $this->tempFilesDirectory . '/' . $this->randomMachineName() . '.csv';
    file_put_contents($path, $content);
    return $path;
  }

}
