<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_labels\Kernel;

use Drupal\entity_labels\EntityLabelsFieldExporterInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for EntityLabelsFieldExporter.
 *
 * @coversDefaultClass \Drupal\entity_labels\EntityLabelsFieldExporter
 * @group entity_labels
 */
#[RunTestsInSeparateProcesses]
class EntityLabelsFieldExporterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'node', 'field', 'entity_labels'];

  /**
   * The field exporter service under test.
   */
  protected EntityLabelsFieldExporterInterface $exporter;

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
    $this->exporter = $this->container->get('entity_labels.field.exporter');
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
   * @covers ::getData
   */
  public function testGetData(): void {
    /* *** Excludes entity types without bundle support *** */
    $rows = $this->exporter->getData();
    $entity_types = array_column($rows, 'entity_type');
    $this->assertContains('node', $entity_types);
    $this->assertNotContains('user', $entity_types);

    /* *** Row for field_test has all expected keys *** */
    $field_rows = array_values(array_filter(
      $rows,
      static fn(array $r) => $r['field_name'] === 'field_test',
    ));
    $this->assertNotEmpty($field_rows);
    $row = $field_rows[0];
    $expected_keys = [
      'langcode', 'entity_type', 'bundle', 'field_name',
      'field_column', 'field_type', 'label', 'description',
      'allowed_values', 'is_base_field', 'notes',
    ];
    foreach ($expected_keys as $key) {
      $this->assertArrayHasKey($key, $row);
    }

    /* *** Filters to specified entity type *** */
    $node_rows = $this->exporter->getData('node');
    foreach ($node_rows as $node_row) {
      $this->assertSame('node', $node_row['entity_type']);
    }

    /* *** Filters to specified bundle; all rows match bundle *** */
    $article_rows = $this->exporter->getData('node', 'article');
    foreach ($article_rows as $article_row) {
      $this->assertSame('article', $article_row['bundle']);
    }

    /* *** Sorts field_alpha before field_beta within the same bundle *** */
    $this->createField('article', 'field_alpha', 'Alpha');
    $this->createField('article', 'field_beta', 'Beta');
    $custom_rows = array_values(array_filter(
      $this->exporter->getData(),
      static fn(array $r) => $r['entity_type'] === 'node'
        && $r['bundle'] === 'article'
        && in_array($r['field_name'], ['field_alpha', 'field_beta'], TRUE),
    ));
    $this->assertCount(2, $custom_rows);
    $this->assertSame('field_alpha', $custom_rows[0]['field_name']);
    $this->assertSame('field_beta', $custom_rows[1]['field_name']);

    /* *** Base field (title) has is_base_field === TRUE *** */
    $title_rows = array_values(array_filter(
      $this->exporter->getData('node'),
      static fn(array $r) => $r['field_name'] === 'title',
    ));
    $this->assertNotEmpty($title_rows);
    $this->assertTrue($title_rows[0]['is_base_field']);
  }

  /**
   * @covers ::export
   */
  public function testExport(): void {
    /* *** First row is the header *** */
    $rows = $this->exporter->export();
    $this->assertSame('langcode', $rows[0][0]);
    $this->assertContains('entity_type', $rows[0]);
    $this->assertContains('field_name', $rows[0]);

    /* *** Row count equals getData() count plus one *** */
    $this->createField('article', 'field_one', 'One');
    $this->createField('article', 'field_two', 'Two');
    $data = $this->exporter->getData();
    $rows = $this->exporter->export();
    $this->assertCount(count($data) + 1, $rows);
  }

}
