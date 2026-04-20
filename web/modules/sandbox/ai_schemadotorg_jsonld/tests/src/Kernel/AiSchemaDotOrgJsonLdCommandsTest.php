<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld\Kernel;

use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface;
use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdManagerInterface;
use Drupal\ai_schemadotorg_jsonld\Drush\Commands\AiSchemaDotOrgJsonLdCommands;
use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\NodeType;
use Psr\Log\LoggerInterface;

/**
 * Tests the AI Schema.org JSON-LD Drush commands.
 *
 * @group ai_schemadotorg_jsonld
 */
class AiSchemaDotOrgJsonLdCommandsTest extends AiSchemaDotOrgJsonLdTestBase {

  /**
   * Tests the Drush commands.
   */
  public function testCommands(): void {
    $manager = $this->container->get(AiSchemaDotOrgJsonLdManagerInterface::class);
    $builder = $this->container->get(AiSchemaDotOrgJsonLdBuilderInterface::class);
    $commands = new AiSchemaDotOrgJsonLdCommands($manager, $builder, $this->entityTypeManager);

    // Mock the Drush logger to capture and verify success messages.
    $logger = $this->createMock(LoggerInterface::class);
    $logger_manager = new \Drush\Log\DrushLoggerManager();
    $logger_manager->add('test', $logger);
    $commands->setLogger($logger_manager);

    // Create a 'page' node type first.
    NodeType::create(['type' => 'page', 'name' => 'Basic page'])->save();

    // Expect a success message for each successful addField call.
    $logger->expects($this->exactly(2))
      ->method('log')
      ->with('success', $this->stringContains('Added Schema.org JSON-LD field to'));

    // Check that field is added successfully.
    $commands->addField('node', 'page');

    $field_config = $this->entityTypeManager
      ->getStorage('field_config')
      ->load('node.page.' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME);
    $this->assertNotNull($field_config, 'FieldConfig for node.page exists.');

    // Check that an exception is thrown for an unsupported entity type.
    try {
      $commands->addField('invalid_entity_type', 'invalid_bundle');
      $this->fail('Expected \InvalidArgumentException for unsupported entity type was not thrown.');
    }
    catch (\InvalidArgumentException $e) {
      $this->assertSame('The entity type invalid_entity_type is not supported.', $e->getMessage());
    }

    // Check that an exception is thrown for an invalid bundle on a supported entity type.
    try {
      $commands->addField('node', 'invalid_bundle');
      $this->fail('Expected \InvalidArgumentException for invalid bundle was not thrown.');
    }
    catch (\InvalidArgumentException $e) {
      $this->assertSame('The bundle invalid_bundle does not exist for the entity type node.', $e->getMessage());
    }

    // Check that omitting the bundle for a non-bundle entity type auto-sets it.
    $commands->addField('user');
    $user_field_config = $this->entityTypeManager
      ->getStorage('field_config')
      ->load('user.user.' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME);
    $this->assertNotNull($user_field_config, 'FieldConfig for user.user exists when bundle is omitted.');

    // Check that an exception is thrown for an invalid synthetic bundle on 'user'.
    try {
      $commands->addField('user', 'invalid_bundle');
      $this->fail('Expected \InvalidArgumentException for invalid synthetic bundle was not thrown.');
    }
    catch (\InvalidArgumentException $e) {
      $this->assertSame('The non-bundle entity type user requires the synthetic bundle user.', $e->getMessage());
    }
  }

}
