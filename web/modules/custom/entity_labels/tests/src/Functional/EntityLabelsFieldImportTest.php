<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_labels\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
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
class EntityLabelsFieldImportTest extends EntityLabelsTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'field', 'file', 'entity_labels'];

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
   * Tests the import form and CSV upload.
   */
  public function testImportForm(): void {
    // Check that the field import form loads and shows the submit button.
    $this->drupalGet('admin/reports/entity-labels/field/import');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->buttonExists('Import CSV');

    // Check that submitting without a file is handled gracefully.
    $this->submitForm([], 'Import CSV');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->statusMessageNotExists('error');

    // Check that a valid CSV import updates the field label.
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

    // Check that a CSV with missing required headers shows an error.
    $csv_content = "entity_type,bundle,field_name\nnode,article,title\n";

    $this->drupalGet('admin/reports/entity-labels/field/import');
    $this->submitForm(
      ['files[csv_upload]' => $this->writeTemporaryCsv($csv_content)],
      'Import CSV',
    );
    $this->assertSession()->statusMessageExists('error');

    // Check that a row for a non-existent field is skipped gracefully.
    $csv_content = "langcode,entity_type,bundle,field_name,label,description\n"
      . "en,node,article,field_does_not_exist,Label,Desc\n";

    $this->drupalGet('admin/reports/entity-labels/field/import');
    $this->submitForm(
      ['files[csv_upload]' => $this->writeTemporaryCsv($csv_content)],
      'Import CSV',
    );
    $this->assertSession()->statusCodeEquals(200);
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
  private function createTestField(string $entity_type, string $bundle, string $field_name, string $label): void {
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'field_storage' => FieldStorageConfig::loadByName($entity_type, $field_name),
      'bundle' => $bundle,
      'label' => $label,
    ])->save();
  }

}
