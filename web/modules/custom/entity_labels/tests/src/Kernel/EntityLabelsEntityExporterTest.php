<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_labels\Kernel;

use Drupal\entity_labels\EntityLabelsEntityExporterInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for EntityLabelsEntityExporter.
 *
 * @coversDefaultClass \Drupal\entity_labels\EntityLabelsEntityExporter
 * @group entity_labels
 */
#[RunTestsInSeparateProcesses]
class EntityLabelsEntityExporterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'node', 'entity_labels'];

  /**
   * The entity exporter service under test.
   */
  protected EntityLabelsEntityExporterInterface $exporter;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['node']);
    $this->exporter = $this->container->get('entity_labels.entity.exporter');
  }

  /**
   * @covers ::getData
   */
  public function testGetData(): void {
    NodeType::create([
      'type' => 'article',
      'name' => 'Article',
      'description' => 'Article description',
      'help' => 'Article help',
    ])->save();
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();

    /* *** Excludes entity types without bundle support *** */
    $rows = $this->exporter->getData();
    $entity_types = array_column($rows, 'entity_type');
    $this->assertContains('node', $entity_types);
    $this->assertNotContains('user', $entity_types);

    /* *** Row has all expected keys and correct values *** */
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
    $this->assertSame('', $row['notes']);

    /* *** Sorts by entity type then bundle; filters to specified entity type *** */
    $node_rows = $this->exporter->getData('node');
    $bundles = array_column($node_rows, 'bundle');
    $this->assertSame('article', $bundles[0]);
    $this->assertSame('page', $bundles[1]);
    foreach ($node_rows as $node_row) {
      $this->assertSame('node', $node_row['entity_type']);
    }
  }

  /**
   * @covers ::export
   */
  public function testExport(): void {
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    /* *** First row is the header *** */
    $rows = $this->exporter->export();
    $this->assertSame(
      ['langcode', 'entity_type', 'bundle', 'label', 'description', 'help', 'notes'],
      $rows[0],
    );

    /* *** Row count equals getData() count plus one; data columns align *** */
    $data = $this->exporter->getData();
    $this->assertCount(count($data) + 1, $rows);
    $this->assertSame($data[0]['entity_type'], $rows[1][1]);
    $this->assertSame($data[0]['bundle'], $rows[1][2]);
    $this->assertSame($data[0]['label'], $rows[1][3]);
  }

}
