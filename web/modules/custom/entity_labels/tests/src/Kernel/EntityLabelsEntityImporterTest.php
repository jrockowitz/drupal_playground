<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_labels\Kernel;

use Drupal\entity_labels\EntityLabelsEntityImporterInterface;
use Drupal\entity_labels\Exception\EntityLabelsCsvParseException;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for EntityLabelsEntityImporter.
 *
 * @coversDefaultClass \Drupal\entity_labels\EntityLabelsEntityImporter
 * @group entity_labels
 */
#[RunTestsInSeparateProcesses]
class EntityLabelsEntityImporterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'node', 'entity_labels'];

  /**
   * The entity importer service under test.
   */
  protected EntityLabelsEntityImporterInterface $importer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node_type');
    $this->installEntitySchema('user');
    $this->installConfig(['node']);
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    $this->importer = $this->container->get('entity_labels.entity.importer');
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
  public function testImportThrowsOnMissingHeaders(): void {
    $this->expectException(EntityLabelsCsvParseException::class);
    $this->importer->import("entity_type,bundle\nnode,article\n");
  }

  /**
   * @covers ::import
   */
  public function testImport(): void {
    /* *** Result array has expected keys *** */
    $result = $this->importer->import("langcode,entity_type,bundle,label,description\n");
    $this->assertArrayHasKey('updated', $result);
    $this->assertArrayHasKey('skipped', $result);
    $this->assertArrayHasKey('errors', $result);
    $this->assertArrayHasKey('null_fields', $result);

    /* *** Updates NodeType label *** */
    $result = $this->importer->import(
      "langcode,entity_type,bundle,label,description\n"
      . "en,node,article,Imported Name,Some description\n",
    );
    $this->assertSame(1, $result['updated']);
    $this->assertSame(0, $result['skipped']);
    $node_type = NodeType::load('article');
    $this->assertNotNull($node_type);
    $this->assertSame('Imported Name', $node_type->label());

    /* *** Updates NodeType description *** */
    $this->importer->import(
      "langcode,entity_type,bundle,label,description\n"
      . "en,node,article,Imported Name,Updated description\n",
    );
    $node_type = NodeType::load('article');
    $this->assertSame('Updated description', $node_type->getDescription());

    /* *** Sets help text when column is present and non-empty *** */
    $this->importer->import(
      "langcode,entity_type,bundle,label,description,help\n"
      . "en,node,article,Imported Name,Updated description,Helpful text\n",
    );
    $node_type = NodeType::load('article');
    $this->assertSame('Helpful text', $node_type->getHelp());

    /* *** Skips missing bundle; identifier recorded in null_fields *** */
    $result = $this->importer->import(
      "langcode,entity_type,bundle,label,description\n"
      . "en,node,nonexistent_bundle,Label,Desc\n",
    );
    $this->assertSame(0, $result['updated']);
    $this->assertSame(1, $result['skipped']);
    $this->assertContains('node.nonexistent_bundle', $result['null_fields']);

    /* *** Skips unknown entity type *** */
    $result = $this->importer->import(
      "langcode,entity_type,bundle,label,description\n"
      . "en,nonexistent_type,bundle,Label,Desc\n",
    );
    $this->assertSame(0, $result['updated']);
    $this->assertSame(1, $result['skipped']);

    /* *** Correct counts across multiple rows *** */
    $result = $this->importer->import(
      "langcode,entity_type,bundle,label,description\n"
      . "en,node,article,Article,Desc\n"
      . "en,node,page,Page,Desc\n",
    );
    $this->assertSame(1, $result['updated']);
    $this->assertSame(1, $result['skipped']);
  }

}
