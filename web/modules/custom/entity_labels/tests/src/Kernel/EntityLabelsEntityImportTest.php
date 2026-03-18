<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_labels\Kernel;

use Drupal\entity_labels\Exception\EntityLabelsCsvParseException;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;

/**
 * Kernel tests for EntityLabelsEntityImporter using real entities.
 *
 * @group entity_labels
 */
class EntityLabelsEntityImportTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'node', 'entity_labels'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node_type');
    $this->installEntitySchema('user');
    $this->installConfig(['node']);
  }

  /**
   * Tests that import() updates a NodeType's human-readable name.
   */
  public function testImportUpdatesNodeTypeName(): void {
    NodeType::create(['type' => 'article', 'name' => 'Original Name'])->save();

    $csv = "langcode,entity_type,bundle,label,description\n"
      . "en,node,article,Imported Name,Some description\n";

    $importer = $this->container->get('entity_labels.entity.importer');
    $result = $importer->import($csv);

    $this->assertSame(1, $result['updated']);
    $this->assertSame(0, $result['skipped']);

    $node_type = NodeType::load('article');
    $this->assertNotNull($node_type);
    $this->assertSame('Imported Name', $node_type->label());
  }

  /**
   * Tests that import() updates a NodeType's description.
   */
  public function testImportUpdatesNodeTypeDescription(): void {
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    $csv = "langcode,entity_type,bundle,label,description\n"
      . "en,node,article,Article,Updated description\n";

    $importer = $this->container->get('entity_labels.entity.importer');
    $importer->import($csv);

    $node_type = NodeType::load('article');
    $this->assertNotNull($node_type);
    $this->assertSame('Updated description', $node_type->getDescription());
  }

  /**
   * Tests that import() skips a bundle that does not exist.
   */
  public function testImportSkipsMissingBundle(): void {
    $csv = "langcode,entity_type,bundle,label,description\n"
      . "en,node,nonexistent_bundle,Label,Desc\n";

    $importer = $this->container->get('entity_labels.entity.importer');
    $result = $importer->import($csv);

    $this->assertSame(0, $result['updated']);
    $this->assertSame(1, $result['skipped']);
    $this->assertContains('node.nonexistent_bundle', $result['null_fields']);
  }

  /**
   * Tests that import() throws when required headers are missing.
   */
  public function testImportThrowsOnMissingHeaders(): void {
    $this->expectException(EntityLabelsCsvParseException::class);

    $importer = $this->container->get('entity_labels.entity.importer');
    $importer->import("entity_type,bundle\nnode,article\n");
  }

  /**
   * Tests that import() throws when the CSV is empty.
   */
  public function testImportThrowsOnEmptyCsv(): void {
    $this->expectException(EntityLabelsCsvParseException::class);

    $importer = $this->container->get('entity_labels.entity.importer');
    $importer->import('');
  }

  /**
   * Tests that import() skips a non-existent entity type.
   */
  public function testImportSkipsUnknownEntityType(): void {
    $csv = "langcode,entity_type,bundle,label,description\n"
      . "en,nonexistent_type,bundle,Label,Desc\n";

    $importer = $this->container->get('entity_labels.entity.importer');
    $result = $importer->import($csv);

    $this->assertSame(0, $result['updated']);
    $this->assertSame(1, $result['skipped']);
  }

  /**
   * Tests that import() sets help text when the value is non-empty.
   */
  public function testImportSetsHelpWhenValueNonEmpty(): void {
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    $csv = "langcode,entity_type,bundle,label,description,help\n"
      . "en,node,article,Article,Desc,Helpful text\n";

    $importer = $this->container->get('entity_labels.entity.importer');
    $importer->import($csv);

    $node_type = NodeType::load('article');
    $this->assertNotNull($node_type);
    $this->assertSame('Helpful text', $node_type->getHelp());
  }

  /**
   * Tests that import() returns correct counts for multiple rows.
   */
  public function testImportReturnsCorrectCountsForMultipleRows(): void {
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    $csv = "langcode,entity_type,bundle,label,description\n"
      . "en,node,article,Article,Desc\n"
      . "en,node,page,Page,Desc\n";

    $importer = $this->container->get('entity_labels.entity.importer');
    $result = $importer->import($csv);

    $this->assertSame(1, $result['updated']);
    $this->assertSame(1, $result['skipped']);
  }

}
