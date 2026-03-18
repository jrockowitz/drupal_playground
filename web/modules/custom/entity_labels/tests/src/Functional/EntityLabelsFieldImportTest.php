<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_labels\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Functional tests for the entity-labels field CSV import form.
 *
 * Verifies that the field import form renders correctly, that a valid CSV
 * updates field labels and descriptions, and that malformed CSV surfaces
 * error messages without crashing.
 *
 * @group entity_labels
 */
#[Group('entity_labels')]
#[RunTestsInSeparateProcesses]
class EntityLabelsFieldImportTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'field', 'file', 'entity_labels'];

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
   * Tests that the field import form loads successfully.
   */
  public function testFieldImportFormLoads(): void {
    $this->drupalGet('admin/reports/entity-labels/field/import');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->buttonExists('Import CSV');
  }

  /**
   * Tests that the import form shows the allowed_values/field_type notice.
   */
  public function testFieldImportFormShowsNotice(): void {
    $this->drupalGet('admin/reports/entity-labels/field/import');
    $this->assertSession()->pageTextContains('allowed_values');
    $this->assertSession()->pageTextContains('field_type');
  }

  /**
   * Tests that submitting without a file is handled gracefully.
   */
  public function testImportWithoutFileIsHandledGracefully(): void {
    $this->drupalGet('admin/reports/entity-labels/field/import');
    $this->submitForm([], 'Import CSV');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->statusMessageNotExists('error');
  }

  /**
   * Tests that uploading a valid field CSV updates the field label.
   *
   * Creates a custom field on node/article, imports a CSV that changes its
   * label, and verifies the success message reports at least one update.
   */
  public function testValidFieldCsvImportUpdatesLabel(): void {
    $this->createTestField('node', 'article', 'field_test_label', 'Original Label');

    $csv_content = "langcode,entity_type,bundle,field_name,label,description\n"
      . "en,node,article,field_test_label,Imported Label,Imported description\n";

    $this->drupalGet('admin/reports/entity-labels/field/import');
    $this->submitForm(
      ['files[csv_upload]' => $this->writeTemporaryCsv($csv_content)],
      'Import CSV',
    );
    $this->assertSession()->statusMessageContains('updated', 'status');

    $field = FieldConfig::load('node.article.field_test_label');
    $this->assertSame('Imported Label', $field?->label());
  }

  /**
   * Tests that a CSV with missing required headers shows an error message.
   */
  public function testMissingHeadersCsvShowsError(): void {
    $csv_content = "entity_type,bundle,field_name\nnode,article,title\n";

    $this->drupalGet('admin/reports/entity-labels/field/import');
    $this->submitForm(
      ['files[csv_upload]' => $this->writeTemporaryCsv($csv_content)],
      'Import CSV',
    );
    $this->assertSession()->statusMessageExists('error');
  }

  /**
   * Tests that importing a row for a non-existent field is skipped gracefully.
   */
  public function testNonexistentFieldRowIsSkipped(): void {
    $csv_content = "langcode,entity_type,bundle,field_name,label,description\n"
      . "en,node,article,field_does_not_exist,Label,Desc\n";

    $this->drupalGet('admin/reports/entity-labels/field/import');
    $this->submitForm(
      ['files[csv_upload]' => $this->writeTemporaryCsv($csv_content)],
      'Import CSV',
    );
    $this->assertSession()->statusCodeEquals(200);
    // 0 updated, 1 skipped — no fatal error.
    $this->assertSession()->statusMessageContains('skipped', 'status');
  }

  /**
   * Creates a simple string field on a bundle for testing.
   *
   * @param string $entity_type
   *   Entity type ID.
   * @param string $bundle
   *   Bundle machine name.
   * @param string $field_name
   *   Field machine name.
   * @param string $label
   *   Field label.
   */
  private function createTestField(
    string $entity_type,
    string $bundle,
    string $field_name,
    string $label,
  ): void {
    if (!FieldStorageConfig::loadByName($entity_type, $field_name)) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => $entity_type,
        'type' => 'string',
      ])->save();
    }
    if (!FieldConfig::loadByName($entity_type, $bundle, $field_name)) {
      FieldConfig::create([
        'field_storage' => FieldStorageConfig::loadByName($entity_type, $field_name),
        'bundle' => $bundle,
        'label' => $label,
      ])->save();
    }
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
