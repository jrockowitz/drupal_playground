<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_labels\Kernel;

use Drupal\entity_labels\EntityLabelsFieldImporterInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for EntityLabelsFieldImporter custom_field error branches.
 *
 * Covers the path where custom_field is installed but the named column is
 * absent from the field's field_settings — the importer should skip the row
 * with an error.
 *
 * @coversDefaultClass \Drupal\entity_labels\EntityLabelsFieldImporter
 * @group entity_labels
 */
#[RunTestsInSeparateProcesses]
class EntityLabelsFieldImporterCustomFieldTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'file',
    'image',
    'link',
    'entity_labels',
    'custom_field',
  ];

  /**
   * The field importer service under test.
   */
  protected EntityLabelsFieldImporterInterface $importer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['node']);
    $this->installSchema('node', 'node_access');
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    // A plain string field has no field_settings, so any column lookup fails.
    FieldStorageConfig::create([
      'field_name' => 'field_test',
      'entity_type' => 'node',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'field_storage' => FieldStorageConfig::load('node.field_test'),
      'bundle' => 'article',
      'label' => 'Test Field',
    ])->save();

    $this->importer = $this->container->get('entity_labels.field.importer');
  }

  /**
   * @covers ::import
   */
  public function testImportCustomFieldColumnNotFound(): void {
    // custom_field is installed and the field config exists, but the named
    // column 'nonexistent_col' is not in field_settings → skip with error.
    $result = $this->importer->import(
      "langcode,entity_type,bundle,field_name,field_column,label,description\n"
      . "en,node,article,field_test,nonexistent_col,Label,Desc\n",
    );

    $this->assertSame(1, $result['skipped']);
    $this->assertNotEmpty($result['errors']);
    $this->assertStringContainsString('nonexistent_col', $result['errors'][0]);
    $this->assertStringContainsString('not found', $result['errors'][0]);
  }

}
