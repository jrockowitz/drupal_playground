<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_labels\Kernel;

use Drupal\entity_labels\EntityLabelsFieldImporterInterface;
use Drupal\entity_labels\Exception\EntityLabelsCsvParseException;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for EntityLabelsFieldImporter.
 *
 * @coversDefaultClass \Drupal\entity_labels\EntityLabelsFieldImporter
 * @group entity_labels
 */
#[RunTestsInSeparateProcesses]
class EntityLabelsFieldImporterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'node', 'field', 'entity_labels'];

  /**
   * CSV header row for field imports.
   */
  private const HEADER = "langcode,entity_type,bundle,field_name,label,description\n";

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
    $this->createField('article', 'field_test', 'Test Field');
    $this->importer = $this->container->get('entity_labels.field.importer');
  }

  /**
   * Creates a string FieldStorageConfig + FieldConfig for the given bundle.
   */
  private function createField(string $bundle, string $field_name, string $label): void {
    if (FieldStorageConfig::load('node.' . $field_name) === NULL) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'type' => 'string',
      ])->save();
    }
    FieldConfig::create([
      'field_storage' => FieldStorageConfig::load('node.' . $field_name),
      'bundle' => $bundle,
      'label' => $label,
    ])->save();
  }

  /**
   * @covers ::import
   */
  public function testImportThrowsOnEmptyCsv(): void {
    $this->expectException(EntityLabelsCsvParseException::class);
    $this->importer->import('');
  }

  /**
   * @covers ::import
   */
  public function testImportThrowsOnMissingRequiredHeaders(): void {
    $this->expectException(EntityLabelsCsvParseException::class);
    $this->importer->import("entity_type,bundle\nnode,article\n");
  }

  /**
   * @covers ::import
   */
  public function testImport(): void {
    /* *** Result array has all expected keys *** */
    $result = $this->importer->import(self::HEADER);
    $this->assertArrayHasKey('updated', $result);
    $this->assertArrayHasKey('skipped', $result);
    $this->assertArrayHasKey('errors', $result);
    $this->assertArrayHasKey('null_fields', $result);

    /* *** Updates FieldConfig label and description *** */
    $this->importer->import(
      self::HEADER . "en,node,article,field_test,Updated Label,Updated description\n",
    );
    $field = FieldConfig::load('node.article.field_test');
    $this->assertNotNull($field);
    $this->assertSame('Updated Label', $field->label());
    $this->assertSame('Updated description', $field->getDescription());

    /* *** Non-existent field: skipped and identifier recorded in null_fields *** */
    $result = $this->importer->import(
      self::HEADER . "en,node,article,field_nonexistent,Label,Desc\n",
    );
    $this->assertSame(1, $result['skipped']);
    $this->assertContains('node.article.field_nonexistent', $result['null_fields']);

    /* *** field_column row when custom_field not installed: skipped with error *** */
    $result = $this->importer->import(
      "langcode,entity_type,bundle,field_name,field_column,label,description\n"
      . "en,node,article,field_test,sub_column,Label,Desc\n",
    );
    $this->assertSame(1, $result['skipped']);
    $this->assertNotEmpty($result['errors']);

    /* *** field_group row when field_group not installed: skipped with error *** */
    $result = $this->importer->import(
      "langcode,entity_type,bundle,field_name,field_type,label,description\n"
      . "en,node,article,group_one,field_group,Group Label,Group desc\n",
    );
    $this->assertSame(1, $result['skipped']);
    $this->assertNotEmpty($result['errors']);
  }

}
