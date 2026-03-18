<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_labels\Kernel;

use Drupal\entity_labels\EntityLabelsFieldExporterInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;

/**
 * Kernel tests for EntityLabelsFieldExporter using real entities.
 *
 * @group entity_labels
 */
class EntityLabelsFieldExportTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'node', 'field', 'entity_labels'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['node']);
    $this->installSchema('node', 'node_access');
  }

  /**
   * Returns the field exporter service under test.
   */
  private function getExporter(): EntityLabelsFieldExporterInterface {
    return $this->container->get('entity_labels.field.exporter');
  }

  /**
   * Creates a string FieldStorageConfig + FieldConfig for the given bundle.
   */
  private function createField(string $bundle, string $field_name, string $label): void {
    if (FieldStorageConfig::load('node.' . $field_name) === NULL) {
      FieldStorageConfig::create([
        'field_name'  => $field_name,
        'entity_type' => 'node',
        'type'        => 'string',
      ])->save();
    }
    FieldConfig::create([
      'field_storage' => FieldStorageConfig::load('node.' . $field_name),
      'bundle'        => $bundle,
      'label'         => $label,
    ])->save();
  }

  /**
   * Tests that getData() excludes entity types without bundle support.
   */
  public function testGetDataSkipsEntityTypesWithoutBundleSupport(): void {
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    $rows = $this->getExporter()->getData();

    $entity_types = array_column($rows, 'entity_type');
    $this->assertContains('node', $entity_types);
    $this->assertNotContains('user', $entity_types);
  }

  /**
   * Tests that getData() rows have the expected keys.
   */
  public function testGetDataReturnsExpectedRowStructure(): void {
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    $this->createField('article', 'field_test', 'Test Field');

    $rows = $this->getExporter()->getData('node');

    $field_rows = array_values(array_filter(
      $rows,
      static fn(array $r) => $r['field_name'] === 'field_test',
    ));

    $this->assertNotEmpty($field_rows);
    $row = $field_rows[0];

    $expected_keys = [
      'langcode', 'entity_type', 'bundle', 'field_name',
      'field_type', 'label', 'description', 'allowed_values', 'notes',
    ];
    foreach ($expected_keys as $key) {
      $this->assertArrayHasKey($key, $row);
    }
  }

  /**
   * Tests that getData() filters to a specific entity type.
   */
  public function testGetDataFiltersToEntityType(): void {
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    $this->createField('article', 'field_test', 'Test Field');

    $rows = $this->getExporter()->getData('node');

    foreach ($rows as $row) {
      $this->assertSame('node', $row['entity_type']);
    }
  }

  /**
   * Tests that getData() filters to a specific bundle.
   */
  public function testGetDataFiltersToBundle(): void {
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    $this->createField('article', 'field_shared', 'Shared Field');
    $this->createField('page', 'field_shared', 'Shared Field');

    $rows = $this->getExporter()->getData('node', 'article');

    // All rows with a non-null bundle must be 'article'.
    foreach ($rows as $row) {
      if ($row['bundle'] !== NULL) {
        $this->assertSame('article', $row['bundle']);
      }
    }
  }

  /**
   * Tests that getData() sorts rows by entity type, bundle, then field name.
   */
  public function testGetDataSortsByEntityTypeBundleFieldName(): void {
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    $this->createField('article', 'field_beta', 'Beta');
    $this->createField('article', 'field_alpha', 'Alpha');

    $rows = $this->getExporter()->getData();

    $custom_rows = array_values(array_filter(
      $rows,
      static fn(array $r) => $r['entity_type'] === 'node'
        && $r['bundle'] === 'article'
        && in_array($r['field_name'], ['field_alpha', 'field_beta'], TRUE),
    ));

    $this->assertCount(2, $custom_rows);
    $this->assertSame('field_alpha', $custom_rows[0]['field_name']);
    $this->assertSame('field_beta', $custom_rows[1]['field_name']);
  }

  /**
   * Tests that a base field row has is_base_field === TRUE.
   */
  public function testGetDataBaseFieldRowIncludesIsBaseFieldTrue(): void {
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    $rows = $this->getExporter()->getData('node');

    $title_rows = array_values(array_filter(
      $rows,
      static fn(array $r) => $r['field_name'] === 'title',
    ));

    $this->assertNotEmpty($title_rows);
    $this->assertTrue($title_rows[0]['is_base_field']);
  }

  /**
   * Tests that export() includes a header row as the first row.
   */
  public function testExportIncludesHeaderAsFirstRow(): void {
    $rows = $this->getExporter()->export();

    $this->assertSame('langcode', $rows[0][0]);
    $this->assertContains('entity_type', $rows[0]);
    $this->assertContains('field_name', $rows[0]);
  }

  /**
   * Tests that export() row count equals getData() count plus header.
   */
  public function testExportRowCountEqualsDataPlusHeader(): void {
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    $this->createField('article', 'field_one', 'One');
    $this->createField('article', 'field_two', 'Two');

    $exporter = $this->getExporter();
    $data = $exporter->getData();
    $rows = $exporter->export();

    $this->assertCount(count($data) + 1, $rows);
  }

}
