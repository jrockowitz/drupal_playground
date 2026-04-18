<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld\Unit;

use Drupal\ai_automators\Event\ValuesChangeEvent;
use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface;
use Drupal\ai_schemadotorg_jsonld\EventSubscriber\AiSchemaDotOrgJsonLdEventSubscriber;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests AiSchemaDotOrgJsonLdEventSubscriber.
 *
 * @group ai_schemadotorg_jsonld
 */
class AiSchemaDotOrgJsonLdEventSubscriberTest extends UnitTestCase {

  /**
   * The event subscriber under test.
   */
  private AiSchemaDotOrgJsonLdEventSubscriber $subscriber;

  /**
   * The messenger mock.
   */
  private MessengerInterface&MockObject $messenger;

  /**
   * The logger mock.
   */
  private LoggerChannelInterface&MockObject $logger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->messenger = $this->createMock(MessengerInterface::class);
    $this->logger = $this->createMock(LoggerChannelInterface::class);

    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->logger);

    $this->subscriber = new AiSchemaDotOrgJsonLdEventSubscriber(
      $this->messenger,
      $logger_factory,
      $this->getStringTranslationStub(),
    );
  }

  /**
   * Builds a ValuesChangeEvent for the JSON-LD field.
   */
  protected function buildEvent(string $raw_value): ValuesChangeEvent {
    $field_definition = $this->createMock(FieldDefinitionInterface::class);
    $field_definition->method('getName')
      ->willReturn(AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME);

    $entity = $this->createMock(ContentEntityInterface::class);

    return new ValuesChangeEvent(
      [['value' => $raw_value]],
      $entity,
      $field_definition,
      [],
    );
  }

  /**
   * Tests that clean JSON is returned unchanged.
   */
  public function testValidJson(): void {
    $event = $this->buildEvent('{"@type":"WebPage"}');
    $this->messenger->expects($this->never())->method('addWarning');
    $this->subscriber->onValuesChange($event);

    // Check that the value is returned unchanged.
    $this->assertSame('{"@type":"WebPage"}', $event->getValues()[0]['value']);
  }

  /**
   * Tests JSON wrapped in markdown fences is extracted.
   */
  public function testJsonInMarkdownFence(): void {
    $event = $this->buildEvent("```json\n{\"@type\":\"WebPage\"}\n```");
    $this->subscriber->onValuesChange($event);

    // Check that the extracted value equals the inner JSON.
    $this->assertSame('{"@type":"WebPage"}', $event->getValues()[0]['value']);
  }

  /**
   * Tests JSON surrounded by explanatory text is extracted.
   */
  public function testJsonWithSurroundingText(): void {
    $event = $this->buildEvent('Here is the JSON: {"@type":"WebPage"} Hope that helps!');
    $this->subscriber->onValuesChange($event);

    // Check that only the JSON object is kept.
    $this->assertSame('{"@type":"WebPage"}', $event->getValues()[0]['value']);
  }

  /**
   * Tests that invalid JSON results in empty value with warnings.
   */
  public function testInvalidJson(): void {
    $this->logger->expects($this->once())->method('warning');
    $this->messenger->expects($this->once())->method('addWarning');

    $event = $this->buildEvent('{not valid json}');
    $this->subscriber->onValuesChange($event);

    // Check that the value is cleared.
    $this->assertSame('', $event->getValues()[0]['value']);
  }

  /**
   * Tests that a response with no JSON object triggers warnings.
   */
  public function testNoJsonFound(): void {
    $this->logger->expects($this->once())->method('warning');
    $this->messenger->expects($this->once())->method('addWarning');

    $event = $this->buildEvent('No JSON here at all');
    $this->subscriber->onValuesChange($event);

    // Check that the value is cleared.
    $this->assertSame('', $event->getValues()[0]['value']);
  }

  /**
   * Tests that unrelated fields are ignored.
   */
  public function testUnrelatedFieldIsIgnored(): void {
    $field_definition = $this->createMock(FieldDefinitionInterface::class);
    $field_definition->method('getName')->willReturn('field_other');

    $entity = $this->createMock(ContentEntityInterface::class);
    $event = new ValuesChangeEvent(
      [['value' => 'garbage']],
      $entity,
      $field_definition,
      [],
    );

    $this->messenger->expects($this->never())->method('addWarning');
    $this->subscriber->onValuesChange($event);

    // Check that the value is untouched.
    $this->assertSame('garbage', $event->getValues()[0]['value']);
  }

}
