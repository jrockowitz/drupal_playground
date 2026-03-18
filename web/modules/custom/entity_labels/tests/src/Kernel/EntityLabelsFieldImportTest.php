<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_labels\Kernel;

use Drupal\Core\Field\Entity\BaseFieldOverride;
use Drupal\entity_labels\EntityLabelsFieldImporterInterface;
use Drupal\entity_labels\Exception\EntityLabelsCsvParseException;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;

/**
 * Kernel tests for EntityLabelsFieldImporter using real entities.
 *
 * @group entity_labels
 */
class EntityLabelsFieldImportTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'node', 'field', 'entity_labels'];

  /**
   * CSV header row for field imports.
   */
  private const HEADER = "langcode,entity_type,bundle,field_name,label,description\n";

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
   * Returns the field importer service under test.
   */
  private function getImporter(): EntityLabelsFieldImporterInterface {
    return $this->container->get('entity_labels.field.importer');
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
   * Tests that import() throws on an empty CSV string.
   */
  public function testImportThrowsOnEmptyCsv(): void {
    $this->expectException(EntityLabelsCsvParseException::class);
    $this->getImporter()->import('');
  }

  /**
   * Tests that import() throws when required headers are missing.
   */
  public function testImportThrowsOnMissingRequiredHeaders(): void {
    $this->expectException(EntityLabelsCsvParseException::class);
    $this->getImporter()->import("entity_type,bundle\nnode,article\n");
  }

  /**
   * Tests that import() updates a FieldConfig label.
   */
  public function testImportUpdatesFieldConfigLabel(): void {
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    $this->createField('article', 'field_test', 'Original Label');

    $csv = self::HEADER
      . "en,node,article,field_test,Updated Label,Some description\n";

    $this->getImporter()->import($csv);

    $field = FieldConfig::load('node.article.field_test');
    $this->assertNotNull($field);
    $this->assertSame('Updated Label', $field->label());
  }

  /**
   * Tests that import() updates a FieldConfig description.
   */
  public function testImportUpdatesFieldConfigDescription(): void {
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    $this->createField('article', 'field_test', 'Label');

    $csv = self::HEADER
      . "en,node,article,field_test,Label,Updated description\n";

    $this->getImporter()->import($csv);

    $field = FieldConfig::load('node.article.field_test');
    $this->assertNotNull($field);
    $this->assertSame('Updated description', $field->getDescription());
  }

  /**
   * Tests that import() falls back to BaseFieldOverride for base fields.
   */
  public function testImportFallsBackToBaseFieldOverride(): void {
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    $defs = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions('node', 'article');
    $override = BaseFieldOverride::createFromBaseFieldDefinition(
      $defs['title'],
      'article',
    );
    $override->setLabel('Original Title')->save();

    $csv = self::HEADER
      . "en,node,article,title,Overridden Title,Some help\n";

    $this->getImporter()->import($csv);

    $reloaded = BaseFieldOverride::load('node.article.title');
    $this->assertNotNull($reloaded);
    $this->assertSame('Overridden Title', $reloaded->label());
  }

  /**
   * Tests that import() skips and records non-existent fields.
   */
  public function testImportSkipsAndRecordsNullFieldWhenNotFound(): void {
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    $csv = self::HEADER
      . "en,node,article,field_nonexistent,Label,Desc\n";

    $result = $this->getImporter()->import($csv);

    $this->assertSame(1, $result['skipped']);
    $this->assertContains('node.article.field_nonexistent', $result['null_fields']);
  }

  /**
   * Tests that import() skips summary rows and counts them.
   */
  public function testImportSkipsSummaryRowsAndCountsThem(): void {
    $csv = self::HEADER
      . "en,node,(default / all bundles),field_test,Label,Desc\n";

    $result = $this->getImporter()->import($csv);

    $this->assertSame(1, $result['skipped']);
    $this->assertSame(0, $result['updated']);
  }

  /**
   * Tests that summary row defaults are applied to per-bundle rows.
   */
  public function testImportAppliesDefaultsFromSummaryRowForEmptyLabel(): void {
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    $this->createField('article', 'field_shared', 'Old Article Label');
    $this->createField('page', 'field_shared', 'Old Page Label');

    // Summary row with default label; per-bundle rows with empty label.
    $csv = self::HEADER
      . "en,node,(default / all bundles),field_shared,Default Label,\n"
      . "en,node,article,field_shared,,\n"
      . "en,node,page,field_shared,,\n";

    $this->getImporter()->import($csv);

    $article_field = FieldConfig::load('node.article.field_shared');
    $page_field = FieldConfig::load('node.page.field_shared');

    $this->assertNotNull($article_field);
    $this->assertNotNull($page_field);
    $this->assertSame('Default Label', $article_field->label());
    $this->assertSame('Default Label', $page_field->label());
  }

  /**
   * Tests that custom_field column rows are skipped when module not installed.
   */
  public function testImportSkipsCustomFieldRowWhenModuleNotInstalled(): void {
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    $csv = "langcode,entity_type,bundle,field_name,field_column,label,description\n"
      . "en,node,article,field_test,sub_column,Label,Desc\n";

    $result = $this->getImporter()->import($csv);

    $this->assertSame(1, $result['skipped']);
    $this->assertNotEmpty($result['errors']);
  }

  /**
   * Tests that import() returns the expected result keys.
   */
  public function testImportReturnsCorrectResultKeys(): void {
    $result = $this->getImporter()->import(self::HEADER);

    $this->assertArrayHasKey('updated', $result);
    $this->assertArrayHasKey('skipped', $result);
    $this->assertArrayHasKey('errors', $result);
    $this->assertArrayHasKey('null_fields', $result);
  }

}
