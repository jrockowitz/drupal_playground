<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld\Kernel;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Provides shared setup for AI Schema.org JSON-LD kernel tests.
 */
abstract class AiSchemaDotOrgJsonLdTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'file',
    'options',
    'token',
    'field_widget_actions',
    'json_field',
    'ai',
    'ai_automators',
    'ai_schemadotorg_jsonld',
  ];

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('field_storage_config');
    $this->installEntitySchema('field_config');
    $this->installEntitySchema('entity_form_display');
    $this->installEntitySchema('entity_view_display');
    $this->installEntitySchema('ai_automator');
    $this->installConfig(self::$modules);

    $this->entityTypeManager = $this->container->get('entity_type.manager');
  }

}
