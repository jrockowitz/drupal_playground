<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_labels\Unit;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\entity_labels\Form\EntityLabelsImportForm;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * @coversDefaultClass \Drupal\entity_labels\Form\EntityLabelsImportForm
 * @group entity_labels
 */
#[Group('entity_labels')]
class EntityLabelsImportFormTest extends UnitTestCase {

  /**
   * Creates a testable anonymous subclass of EntityLabelsImportForm.
   *
   * The subclass exposes getRouteParametersFromFilename() as public via
   * EntityLabelsImportFormTestInterface.
   *
   * @param string $type
   *   The form type ('entity' or 'field').
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager mock.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundleInfo
   *   The bundle info service mock.
   *
   * @return \Drupal\Tests\entity_labels\Unit\EntityLabelsImportFormTestInterface&\Drupal\entity_labels\Form\EntityLabelsImportForm
   *   A subclassed form instance with getRouteParametersFromFilename() public.
   */
  private function createForm(string $type, EntityTypeManagerInterface $entityTypeManager, EntityTypeBundleInfoInterface $bundleInfo): EntityLabelsImportFormTestInterface {
    $fileSystem = $this->createMock(FileSystemInterface::class);
    return new class($type, $fileSystem, $entityTypeManager, $bundleInfo) extends EntityLabelsImportForm implements EntityLabelsImportFormTestInterface {

      /**
       * {@inheritdoc}
       */
      public function getRouteParametersFromFilename(string $filename): array {
        return parent::getRouteParametersFromFilename($filename);
      }

    };
  }

  /**
   * @covers ::getRouteParametersFromFilename
   */
  public function testNoPrefix(): void {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $bundleInfo = $this->createMock(EntityTypeBundleInfoInterface::class);
    $form = $this->createForm('entity', $entityTypeManager, $bundleInfo);

    // Filename with no recognized prefix returns empty array.
    $this->assertSame([], $form->getRouteParametersFromFilename('some-random-file.csv'));
    $this->assertSame([], $form->getRouteParametersFromFilename(''));
    // Field prefix does not match an entity form.
    $this->assertSame([], $form->getRouteParametersFromFilename('entity-labels-fields-node.csv'));
  }

  /**
   * @covers ::getRouteParametersFromFilename
   */
  public function testEntityTypeNotInManager(): void {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('hasDefinition')->with('nonexistent')->willReturn(FALSE);
    $bundleInfo = $this->createMock(EntityTypeBundleInfoInterface::class);
    $form = $this->createForm('entity', $entityTypeManager, $bundleInfo);

    $this->assertSame(
      [],
      $form->getRouteParametersFromFilename('entity-labels-entities-nonexistent.csv'),
    );
  }

  /**
   * @covers ::getRouteParametersFromFilename
   */
  public function testEntityTypeOnly(): void {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('hasDefinition')->with('node')->willReturn(TRUE);
    $bundleInfo = $this->createMock(EntityTypeBundleInfoInterface::class);
    // No matching bundle → only entity_type returned.
    $bundleInfo->method('getBundleInfo')->with('node')->willReturn([]);
    $form = $this->createForm('entity', $entityTypeManager, $bundleInfo);

    $this->assertSame(
      ['entity_type' => 'node'],
      $form->getRouteParametersFromFilename('entity-labels-entities-node.csv'),
    );
  }

  /**
   * @covers ::getRouteParametersFromFilename
   */
  public function testEntityTypeAndBundle(): void {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('hasDefinition')->with('node')->willReturn(TRUE);
    $bundleInfo = $this->createMock(EntityTypeBundleInfoInterface::class);
    $bundleInfo->method('getBundleInfo')->with('node')->willReturn([
      'article' => ['label' => 'Article'],
    ]);
    $form = $this->createForm('entity', $entityTypeManager, $bundleInfo);

    $this->assertSame(
      ['entity_type' => 'node', 'bundle' => 'article'],
      $form->getRouteParametersFromFilename('entity-labels-entities-node-article.csv'),
    );
  }

  /**
   * @covers ::getRouteParametersFromFilename
   */
  public function testFieldPrefix(): void {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('hasDefinition')->with('node')->willReturn(TRUE);
    $bundleInfo = $this->createMock(EntityTypeBundleInfoInterface::class);
    $bundleInfo->method('getBundleInfo')->with('node')->willReturn([
      'article' => ['label' => 'Article'],
    ]);
    $form = $this->createForm('field', $entityTypeManager, $bundleInfo);

    // Field prefix matches for a field-type form.
    $this->assertSame(
      ['entity_type' => 'node', 'bundle' => 'article'],
      $form->getRouteParametersFromFilename('entity-labels-fields-node-article.csv'),
    );

    // Entity prefix does not match a field-type form.
    $this->assertSame(
      [],
      $form->getRouteParametersFromFilename('entity-labels-entities-node-article.csv'),
    );
  }

  /**
   * @covers ::getRouteParametersFromFilename
   */
  public function testNumericSuffixStripped(): void {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('hasDefinition')->with('node')->willReturn(TRUE);
    $bundleInfo = $this->createMock(EntityTypeBundleInfoInterface::class);
    $bundleInfo->method('getBundleInfo')->with('node')->willReturn([
      'article' => ['label' => 'Article'],
    ]);
    $form = $this->createForm('entity', $entityTypeManager, $bundleInfo);

    // Browser download suffixes like " (1)" and " (42)" must be stripped.
    $this->assertSame(
      ['entity_type' => 'node', 'bundle' => 'article'],
      $form->getRouteParametersFromFilename('entity-labels-entities-node-article (1).csv'),
    );
    $this->assertSame(
      ['entity_type' => 'node', 'bundle' => 'article'],
      $form->getRouteParametersFromFilename('entity-labels-entities-node-article (42).csv'),
    );
  }

}

/**
 * Provides an interface for testing the EntityLabelsImportForm.
 */
interface EntityLabelsImportFormTestInterface extends FormInterface {

  /**
   * Extracts route parameters from a given filename.
   *
   * @param string $filename
   *   The name of the file from which to extract route parameters.
   *
   * @return array
   *   An associative array containing the extracted route parameters.
   */
  public function getRouteParametersFromFilename(string $filename): array;

}
