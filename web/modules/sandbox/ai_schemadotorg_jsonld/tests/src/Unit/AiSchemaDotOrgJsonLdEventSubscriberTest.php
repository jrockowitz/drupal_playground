<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld\Unit;

use Drupal\ai\Event\PostGenerateResponseEvent;
use Drupal\ai\Event\PreGenerateResponseEvent;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
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
      [$raw_value],
      $entity,
      $field_definition,
      [],
    );
  }

  /**
   * Tests getSubscribedEvents().
   */
  public function testGetSubscribedEvents(): void {
    // Check that the pre-generate, post-generate, and values change events are subscribed.
    $this->assertSame([
      PreGenerateResponseEvent::EVENT_NAME => 'onPreGenerateResponse',
      PostGenerateResponseEvent::EVENT_NAME => 'onPostGenerateResponse',
      ValuesChangeEvent::EVENT_NAME => 'onValuesChange',
    ], AiSchemaDotOrgJsonLdEventSubscriber::getSubscribedEvents());
  }

  /**
   * Tests that onPreGenerateResponse() unescapes JSON-LD automator prompts.
   */
  public function testOnPreGenerateResponseCleansUpJsonLdPrompt(): void {
    $input = new ChatInput([
      new ChatMessage('user', "&lt;p&gt;Prompt&lt;/p&gt;\r\n\r\n\r\n&amp; &quot;quoted&quot;"),
    ]);
    $event = new PreGenerateResponseEvent(
      'request-id-jsonld',
      'test_provider',
      'chat',
      [],
      $input,
      'test-model',
      [
        'ai_automator',
        'ai_automator:field_name:' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME,
      ],
    );

    $this->subscriber->onPreGenerateResponse($event);

    $updated_input = $event->getInput();
    $this->assertInstanceOf(ChatInput::class, $updated_input);
    $messages = $updated_input->getMessages();
    $this->assertCount(1, $messages);
    $this->assertSame("<p>Prompt</p>\n\n& \"quoted\"", $messages[0]->getText());
  }

  /**
   * Tests that unrelated pre-generate requests are ignored.
   */
  public function testOnPreGenerateResponseIgnoresUnrelatedRequests(): void {
    $input = new ChatInput([
      new ChatMessage('user', '&lt;p&gt;Prompt&lt;/p&gt;'),
    ]);
    $event = new PreGenerateResponseEvent(
      'request-id-unrelated',
      'test_provider',
      'chat',
      [],
      $input,
      'test-model',
      ['ai_automator'],
    );

    $this->subscriber->onPreGenerateResponse($event);

    $updated_input = $event->getInput();
    $this->assertInstanceOf(ChatInput::class, $updated_input);
    $messages = $updated_input->getMessages();
    $this->assertCount(1, $messages);
    $this->assertSame('&lt;p&gt;Prompt&lt;/p&gt;', $messages[0]->getText());
  }

  /**
   * Tests that non-chat pre-generate payloads are ignored safely.
   */
  public function testOnPreGenerateResponseIgnoresNonChatInput(): void {
    $event = new PreGenerateResponseEvent(
      'request-id-jsonld-string',
      'test_provider',
      'chat',
      [],
      '&lt;p&gt;Prompt&lt;/p&gt;',
      'test-model',
      [
        'ai_automator',
        'ai_automator:field_name:' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME,
      ],
    );

    $this->subscriber->onPreGenerateResponse($event);

    $this->assertSame('&lt;p&gt;Prompt&lt;/p&gt;', $event->getInput());
  }

  /**
   * Provides JSON-LD value transformation test cases.
   *
   * @return array
   *   The raw value, expected value, warning count, and logger count.
   */
  public static function providerOnValuesChangeReturnsProcessedValues(): array {
    return [
      'clean json' => [
        '{"@type":"WebPage"}',
        '{"@type":"WebPage"}',
        0,
        0,
      ],
      'markdown wrapped json' => [
        "```json\n{\"@type\":\"WebPage\"}\n```",
        '{"@type":"WebPage"}',
        0,
        0,
      ],
      'json with surrounding text' => [
        'Here is the JSON: {"@type":"WebPage"} Hope that helps!',
        '{"@type":"WebPage"}',
        0,
        0,
      ],
      'json with repaired adjacent bad quote escapes' => [
        '{"@context":"https://schema.org","articleBody":"a\&quot;"drops"\&quot;c"}',
        '{"@context":"https://schema.org","articleBody":"a\"drops\"c"}',
        0,
        0,
      ],
      'json with unrepaired bad quote escapes' => [
        '{"@context":"https://schema.org","articleBody":"<p><a href=\"\\&quot;http://www.drupal.org\\&quot;\">www.drupal.org</a></p>"}',
        '{"@context":"https://schema.org","articleBody":"<p><a href=\"\\&quot;http://www.drupal.org\\&quot;\">www.drupal.org</a></p>"}',
        1,
        1,
      ],
      'invalid json' => [
        '{not valid json}',
        '{not valid json}',
        1,
        1,
      ],
      'missing json boundaries' => [
        'No JSON here at all',
        '',
        1,
        1,
      ],
    ];
  }

  /**
   * Tests onValuesChange() value processing.
   *
   * @dataProvider providerOnValuesChangeReturnsProcessedValues
   */
  public function testOnValuesChangeReturnsProcessedValue(string $rawValue, string $expectedValue, int $warningCount, int $loggerCount): void {
    $this->messenger->expects($this->exactly($warningCount))->method('addWarning');
    $this->logger->expects($this->exactly($loggerCount))->method('warning');

    $event = $this->buildEvent($rawValue);
    $this->subscriber->onValuesChange($event);

    // Check that the processed value is written back to the event response.
    $this->assertSame($expectedValue, $event->getValues()[0]);
  }

  /**
   * Tests that unrelated fields are ignored by onValuesChange().
   */
  public function testOnValuesChangeIgnoresUnrelatedFields(): void {
    // Check that unrelated fields are ignored.
    $field_definition = $this->createMock(FieldDefinitionInterface::class);
    $field_definition->method('getName')->willReturn('field_other');

    $entity = $this->createMock(ContentEntityInterface::class);
    $event = new ValuesChangeEvent(
      ['garbage'],
      $entity,
      $field_definition,
      [],
    );

    $this->messenger->expects($this->never())->method('addWarning');
    $this->subscriber->onValuesChange($event);

    // Check that the value is untouched.
    $this->assertSame('garbage', $event->getValues()[0]);
  }

  /**
   * Tests that onPostGenerateResponse() ignores unrelated AI requests.
   */
  public function testOnPostGenerateResponseIgnoresUnrelatedRequests(): void {
    $unrelated_output = new ChatOutput(
      new ChatMessage('assistant', '{"@type":"WebPage"}'),
      ['raw' => 'response'],
      [],
    );
    $unrelated_event = new PostGenerateResponseEvent(
      'request-id-unrelated',
      'test_provider',
      'chat',
      [],
      NULL,
      'test-model',
      $unrelated_output,
      ['ai_automator'],
    );

    // Check that unrelated AI responses do not produce a log notice.
    $this->logger->expects($this->never())->method('notice');
    $this->subscriber->onPostGenerateResponse($unrelated_event);
  }

  /**
   * Tests that onPostGenerateResponse() logs JSON-LD automator responses.
   */
  public function testOnPostGenerateResponseLogsJsonLdRequests(): void {
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
      NULL,
      'test-model',
      $output,
      [
        'ai_automator',
        'ai_automator:field_name:' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME,
      ],
    );

    // Check that JSON-LD automator responses are logged with the request ID.
    $this->logger->expects($this->once())->method('notice')
      ->with(
        $this->stringContains('@request_id'),
        $this->arrayHasKey('@request_id'),
      );
    $this->subscriber->onPostGenerateResponse($event);
  }

}
