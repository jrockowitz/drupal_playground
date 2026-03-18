<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_labels\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Functional tests for custom_field 4.x column row support in entity_labels.
 *
 * These tests are skipped automatically when the custom_field module is not
 * installed or when it is below version 4.x. They verify that custom_field
 * column rows appear in the bundle-level report and can be imported via CSV.
 *
 * @group entity_labels
 */
#[Group('entity_labels')]
#[RunTestsInSeparateProcesses]
class EntityLabelsCustomFieldTest extends EntityLabelsTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'entity_labels'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    if (!\Drupal::moduleHandler()->moduleExists('custom_field')) {
      $this->markTestSkipped('custom_field module is not available.');
    }

    if (!class_exists('\Drupal\custom_field\Attribute\CustomFieldType')) {
      $this->markTestSkipped('custom_field 4.x is required (attribute class not found).');
    }

    $this->drupalLogin(
      $this->drupalCreateUser(['access site reports'])
    );
  }

  /**
   * Tests that custom_field column rows appear in the bundle-level report.
   *
   * Creates a custom_field field on node/article and verifies the bundle-
   * level report page includes a column row (field_column not empty).
   */
  public function testCustomFieldColumnRowsAppearInBundleReport(): void {
    $this->createCustomField('node', 'article', 'field_custom_test');

    $this->drupalGet('admin/reports/entity-labels/field/node/article');
    $this->assertSession()->statusCodeEquals(200);
    // The field_column header column should be visible.
    $this->assertSession()->pageTextContains('field_column');
  }

  /**
   * Tests that a custom_field column CSV row can be imported without errors.
   */
  public function testCustomFieldColumnCsvImportSucceeds(): void {
    $this->createCustomField('node', 'article', 'field_custom_import');

    $csv_content = "langcode,entity_type,bundle,field_name,field_column,"
      . "label,description\n"
      . "en,node,article,field_custom_import,value,Imported col label,"
      . "Imported col desc\n";

    $this->drupalGet('admin/reports/entity-labels/field/import');
    $this->submitForm(
      ['files[csv_upload]' => $this->writeTemporaryCsv($csv_content)],
      'Import CSV',
    );
    $this->assertSession()->statusCodeEquals(200);
    // Should update or skip without fatal error.
    $this->assertSession()->statusMessageNotExists('error');
  }

  /**
   * Creates a custom_field field on the given entity type and bundle.
   *
   * @param string $entity_type
   *   Entity type ID.
   * @param string $bundle
   *   Bundle machine name.
   * @param string $field_name
   *   Field machine name.
   */
  private function createCustomField(
    string $entity_type,
    string $bundle,
    string $field_name,
  ): void {
    if (!FieldStorageConfig::loadByName($entity_type, $field_name)) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => $entity_type,
        'type' => 'custom',
        'settings' => [
          'columns' => [
            ['name' => 'value', 'type' => 'string', 'max_length' => 255],
          ],
        ],
      ])->save();
    }
    if (!FieldConfig::loadByName($entity_type, $bundle, $field_name)) {
      FieldConfig::create([
        'field_storage' => FieldStorageConfig::loadByName($entity_type, $field_name),
        'bundle' => $bundle,
        'label' => 'Custom test field',
        'settings' => [
          'field_settings' => [
            'value' => [
              'label' => 'Value column',
              'description' => '',
              'type' => 'string',
            ],
          ],
        ],
      ])->save();
    }
  }

}
