<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld\Kernel;

use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface;
use Drupal\ai_schemadotorg_jsonld\Drush\Commands\AiSchemaDotOrgJsonLdCommands;
use Drupal\node\Entity\NodeType;
use Drush\Log\DrushLoggerManager;
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
    $builder = $this->container->get(AiSchemaDotOrgJsonLdBuilderInterface::class);
    $commands = new AiSchemaDotOrgJsonLdCommands($builder);

    // Mock the Drush logger to capture and verify success messages.
    $logger = $this->createMock(LoggerInterface::class);
    $logger_manager = new DrushLoggerManager();
    $logger_manager->add('test', $logger);
    $commands->setLogger($logger_manager);

    // Create a 'page' node type first.
    NodeType::create(['type' => 'page', 'name' => 'Basic page'])->save();

    // Expect a success message for each successful addField call.
    $logger->expects($this->exactly(3))
      ->method('log')
      ->with('success', $this->stringContains('Added Schema.org JSON-LD field to'));

    // Check that field is added successfully.
    $commands->addField('node', 'page');

    $field_config = $this->entityTypeManager
      ->getStorage('field_config')
      ->load('node.page.' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME);
    $this->assertNotNull($field_config, 'FieldConfig for node.page exists.');

    // Check that wildcard bundle support adds the field to all current bundles.
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    $commands->addField('node', '*');

    $article_field_config = $this->entityTypeManager
      ->getStorage('field_config')
      ->load('node.article.' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME);
    $this->assertNotNull($article_field_config, 'FieldConfig for node.article exists after wildcard addField call.');

    // Check that an exception is thrown for an unsupported entity type.
    try {
      $commands->addField('invalid_entity_type', 'invalid_bundle');
      $this->fail('Expected \RuntimeException for unsupported entity type was not thrown.');
    }
    catch (\RuntimeException $exception) {
      $this->assertSame('The entity type invalid_entity_type is not supported.', $exception->getMessage());
    }

    // Check that an exception is thrown for an invalid bundle on a supported entity type.
    try {
      $commands->addField('node', 'invalid_bundle');
      $this->fail('Expected \RuntimeException for invalid bundle was not thrown.');
    }
    catch (\RuntimeException $exception) {
      $this->assertSame('The bundle invalid_bundle does not exist for the entity type node.', $exception->getMessage());
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
      $this->fail('Expected \RuntimeException for invalid synthetic bundle was not thrown.');
    }
    catch (\RuntimeException $exception) {
      $this->assertSame('The non-bundle entity type user requires the synthetic bundle user.', $exception->getMessage());
    }
  }

}
