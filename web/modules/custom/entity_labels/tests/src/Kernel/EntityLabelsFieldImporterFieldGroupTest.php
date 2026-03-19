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
 * Kernel tests for EntityLabelsFieldImporter field_group error branches.
 *
 * Covers the path where field_group is installed but the named group is absent
 * from the form display — the importer should skip the row with an error.
 *
 * @coversDefaultClass \Drupal\entity_labels\EntityLabelsFieldImporter
 * @group entity_labels
 */
#[RunTestsInSeparateProcesses]
class EntityLabelsFieldImporterFieldGroupTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'entity_labels',
    'field_group',
    'field_ui',
  ];

  /**
   * CSV header row for field imports with field_type column.
   */
  private const HEADER = "langcode,entity_type,bundle,field_name,field_type,label,description\n";

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
    $this->installEntitySchema('entity_form_display');
    $this->installConfig(['node', 'field_ui']);
    $this->installSchema('node', 'node_access');
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

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
  public function testImportFieldGroupNotFound(): void {
    // field_group is installed, but no group named 'group_missing' exists on
    // the article form display → the importer must skip with an error.
    $result = $this->importer->import(
      self::HEADER
      . "en,node,article,group_missing,field_group,Group Label,Group desc\n",
    );

    $this->assertSame(1, $result['skipped']);
    $this->assertNotEmpty($result['errors']);
    $this->assertStringContainsString('group_missing', $result['errors'][0]);
    $this->assertStringContainsString('not found', $result['errors'][0]);
  }

}
