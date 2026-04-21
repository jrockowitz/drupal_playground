<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld\Kernel;

use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdManagerInterface;

/**
 * Tests the AI Schema.org JSON-LD manager.
 *
 * @group ai_schemadotorg_jsonld
 */
class AiSchemaDotOrgJsonLdManagerTest extends AiSchemaDotOrgJsonLdTestBase {

  /**
   * Tests supported entity type checks.
   */
  public function testIsSupportedEntityType(): void {
    /** @var \Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdManagerInterface $manager */
    $manager = $this->container->get(AiSchemaDotOrgJsonLdManagerInterface::class);

    $this->assertTrue($manager->isSupportedEntityType('node'));
    $this->assertTrue($manager->isSupportedEntityType('user'));
    $this->assertFalse($manager->isSupportedEntityType('invalid_entity_type'));
    $this->assertFalse($manager->isSupportedEntityType('shortcut'));
  }

}
