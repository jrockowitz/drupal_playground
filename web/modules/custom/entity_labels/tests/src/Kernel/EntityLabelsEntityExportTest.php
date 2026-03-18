<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_labels\Kernel;

use Drupal\entity_labels\EntityLabelsEntityExporterInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;

/**
 * Kernel tests for EntityLabelsEntityExporter using real entities.
 *
 * @group entity_labels
 */
class EntityLabelsEntityExportTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'node', 'entity_labels'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['node']);
  }

  /**
   * Returns the entity exporter service under test.
   */
  private function getExporter(): EntityLabelsEntityExporterInterface {
    return $this->container->get('entity_labels.entity.exporter');
  }

  /**
   * Tests that getData() excludes entity types without bundle support.
   */
  public function testGetDataOnlyIncludesEntityTypesWithBundleSupport(): void {
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    $rows = $this->getExporter()->getData();

    $entity_types = array_column($rows, 'entity_type');
    $this->assertContains('node', $entity_types);
    $this->assertNotContains('user', $entity_types);
  }

  /**
   * Tests that getData() returns rows with the expected structure and values.
   */
  public function testGetDataReturnsExpectedStructure(): void {
    NodeType::create([
      'type'        => 'article',
      'name'        => 'Article',
      'description' => 'Article description',
      'help'        => 'Article help',
    ])->save();

    $rows = $this->getExporter()->getData();

    $article_rows = array_values(array_filter(
      $rows,
      static fn(array $r) => $r['entity_type'] === 'node' && $r['bundle'] === 'article',
    ));

    $this->assertCount(1, $article_rows);
    $row = $article_rows[0];

    $this->assertArrayHasKey('langcode', $row);
    $this->assertArrayHasKey('entity_type', $row);
    $this->assertArrayHasKey('bundle', $row);
    $this->assertArrayHasKey('label', $row);
    $this->assertArrayHasKey('description', $row);
    $this->assertArrayHasKey('help', $row);
    $this->assertArrayHasKey('notes', $row);

    $this->assertSame('node', $row['entity_type']);
    $this->assertSame('article', $row['bundle']);
    $this->assertSame('Article', $row['label']);
    $this->assertSame('Article description', $row['description']);
    $this->assertSame('Article help', $row['help']);
  }

  /**
   * Tests that getData() sorts rows by entity type then bundle.
   */
  public function testGetDataSortsByEntityTypeThenBundle(): void {
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    $rows = $this->getExporter()->getData('node');

    $bundles = array_column($rows, 'bundle');
    $this->assertSame('article', $bundles[0]);
    $this->assertSame('page', $bundles[1]);
  }

  /**
   * Tests that getData() filters to a specific entity type.
   */
  public function testGetDataFiltersToEntityType(): void {
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    $rows = $this->getExporter()->getData('node');

    foreach ($rows as $row) {
      $this->assertSame('node', $row['entity_type']);
    }
  }

  /**
   * Tests that getData() returns all bundles when filtering by entity type.
   */
  public function testGetDataWithEntityTypeReturnsAllBundles(): void {
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();

    $rows = $this->getExporter()->getData('node');

    $this->assertCount(2, $rows);
  }

  /**
   * Tests that getData() includes a notes key with an empty string.
   */
  public function testGetDataRowIncludesNotesKey(): void {
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    $rows = $this->getExporter()->getData('node');

    $this->assertNotEmpty($rows);
    $this->assertSame('', $rows[0]['notes']);
  }

  /**
   * Tests that export() returns the header row as the first row.
   */
  public function testExportReturnsHeaderRow(): void {
    $rows = $this->getExporter()->export();

    $this->assertSame(
      ['langcode', 'entity_type', 'bundle', 'label', 'description', 'help', 'notes'],
      $rows[0],
    );
  }

  /**
   * Tests that export() rows match getData() output.
   */
  public function testExportRowsMatchGetDataOutput(): void {
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    $exporter = $this->getExporter();
    $data = $exporter->getData();
    $rows = $exporter->export();

    $this->assertCount(count($data) + 1, $rows);

    // The second row (index 1) should be the first data row.
    $this->assertSame($data[0]['entity_type'], $rows[1][1]);
    $this->assertSame($data[0]['bundle'], $rows[1][2]);
    $this->assertSame($data[0]['label'], $rows[1][3]);
  }

}
