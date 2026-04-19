<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld_log\Unit;

use Drupal\ai\Event\PostGenerateResponseEvent;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface;
use Drupal\ai_schemadotorg_jsonld_log\AiSchemaDotOrgJsonLdLogStorageInterface;
use Drupal\ai_schemadotorg_jsonld_log\EventSubscriber\AiSchemaDotOrgJsonLdLogEventSubscriber;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests the log event subscriber.
 *
 * @group ai_schemadotorg_jsonld_log
 */
class AiSchemaDotOrgJsonLdLogEventSubscriberTest extends UnitTestCase {

  /**
   * The log storage mock.
   */
  protected AiSchemaDotOrgJsonLdLogStorageInterface&MockObject $logStorage;

  /**
   * The config factory mock.
   */
  protected ConfigFactoryInterface&MockObject $configFactory;

  /**
   * The entity type manager mock.
   */
  protected EntityTypeManagerInterface&MockObject $entityTypeManager;

  /**
   * The subscriber under test.
   */
  protected AiSchemaDotOrgJsonLdLogEventSubscriber $subscriber;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->logStorage = $this->createMock(AiSchemaDotOrgJsonLdLogStorageInterface::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')
      ->with('enable')
      ->willReturn(TRUE);
    $this->configFactory->method('get')
      ->with('ai_schemadotorg_jsonld_log.settings')
      ->willReturn($config);

    $this->subscriber = new AiSchemaDotOrgJsonLdLogEventSubscriber(
      $this->configFactory,
      $this->entityTypeManager,
      $this->logStorage,
    );
  }

  /**
   * Tests the subscriber stores prompt, response, and entity metadata.
   */
  public function testSubscriberStoresPromptResponseAndEntityMetadataWhenEnabled(): void {
    $entity_type = $this->createMock(EntityTypeInterface::class);
    $entity_type->method('hasLinkTemplate')
      ->with('canonical')
      ->willReturn(TRUE);

    $url = $this->createMock(Url::class);
    $url->method('setAbsolute')->willReturnSelf();
    $url->method('toString')->willReturn('https://example.com/node/99');

    $entity = $this->createMock(EntityInterface::class);
    $entity->method('label')->willReturn('Stored label');
    $entity->method('bundle')->willReturn('page');
    $entity->method('getEntityType')->willReturn($entity_type);
    $entity->method('toUrl')->with('canonical')->willReturn($url);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('99')->willReturn($entity);

    $this->entityTypeManager->method('hasDefinition')
      ->with('node')
      ->willReturn(TRUE);
    $this->entityTypeManager->method('getStorage')
      ->with('node')
      ->willReturn($storage);

    $output = new ChatOutput(
      new ChatMessage('assistant', '{"@type":"WebPage"}'),
      ['raw' => 'response'],
      [],
    );
    $event = new PostGenerateResponseEvent(
      'request-id-jsonld',
      'test_provider',
      'chat',
      [],
      'Prompt text',
      'test-model',
      $output,
      [
        'ai_automator',
        'ai_automator:field_name:' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME,
        'ai_automator:entity_type:node',
        'ai_automator:entity:99',
      ],
    );

    $this->logStorage->expects($this->once())
      ->method('insert')
      ->with([
        'entity_type' => 'node',
        'entity_id' => '99',
        'entity_label' => 'Stored label',
        'bundle' => 'page',
        'url' => 'https://example.com/node/99',
        'prompt' => 'Prompt text',
        'response' => '{"@type":"WebPage"}',
      ]);

    $this->subscriber->onPostGenerateResponse($event);
  }

  /**
   * Tests the subscriber stores empty metadata when the entity is unavailable.
   */
  public function testSubscriberStoresEmptyMetadataWhenEntityCannotBeLoaded(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('99')->willReturn(NULL);

    $this->entityTypeManager->method('hasDefinition')
      ->with('node')
      ->willReturn(TRUE);
    $this->entityTypeManager->method('getStorage')
      ->with('node')
      ->willReturn($storage);

    $output = new ChatOutput(
      new ChatMessage('assistant', '{"@type":"WebPage"}'),
      ['raw' => 'response'],
      [],
    );
    $event = new PostGenerateResponseEvent(
      'request-id-jsonld-missing-entity',
      'test_provider',
      'chat',
      [],
      'Prompt text',
      'test-model',
      $output,
      [
        'ai_automator',
        'ai_automator:field_name:' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME,
        'ai_automator:entity_type:node',
        'ai_automator:entity:99',
      ],
    );

    $this->logStorage->expects($this->once())
      ->method('insert')
      ->with([
        'entity_type' => 'node',
        'entity_id' => '99',
        'entity_label' => '',
        'bundle' => '',
        'url' => '',
        'prompt' => 'Prompt text',
        'response' => '{"@type":"WebPage"}',
      ]);

    $this->subscriber->onPostGenerateResponse($event);
  }

  /**
   * Tests unrelated requests are ignored.
   */
  public function testSubscriberIgnoresUnrelatedRequests(): void {
    $output = new ChatOutput(
      new ChatMessage('assistant', '{"@type":"WebPage"}'),
      ['raw' => 'response'],
      [],
    );
    $event = new PostGenerateResponseEvent(
      'request-id-unrelated',
      'test_provider',
      'chat',
      [],
      'Prompt text',
      'test-model',
      $output,
      ['ai_automator'],
    );

    $this->logStorage->expects($this->never())->method('insert');
    $this->subscriber->onPostGenerateResponse($event);
  }

}
