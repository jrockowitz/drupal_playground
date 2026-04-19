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
   * Tests onValuesChange().
   */
  public function testOnValuesChange(): void {
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

    // Check that clean JSON is returned unchanged.
    $valid_event = $this->buildEvent('{"@type":"WebPage"}');
    $this->subscriber->onValuesChange($valid_event);
    $this->assertSame('{"@type":"WebPage"}', $valid_event->getValues()[0]);

    // Check that JSON wrapped in markdown fences is extracted.
    $markdown_event = $this->buildEvent("```json\n{\"@type\":\"WebPage\"}\n```");
    $this->subscriber->onValuesChange($markdown_event);
    $this->assertSame('{"@type":"WebPage"}', $markdown_event->getValues()[0]);

    // Check that JSON surrounded by explanatory text is extracted.
    $surrounding_text_event = $this->buildEvent('Here is the JSON: {"@type":"WebPage"} Hope that helps!');
    $this->subscriber->onValuesChange($surrounding_text_event);
    $this->assertSame('{"@type":"WebPage"}', $surrounding_text_event->getValues()[0]);
  }

  /**
   * Tests onValuesChange() warnings.
   */
  public function testOnValuesChangeWarnings(): void {
    $this->logger->expects($this->exactly(2))->method('warning');
    $this->messenger->expects($this->exactly(2))->method('addWarning');

    // Check that a response with no JSON object triggers warnings.
    $no_json_event = $this->buildEvent('No JSON here at all');
    $this->subscriber->onValuesChange($no_json_event);
    $this->assertSame('', $no_json_event->getValues()[0]);

    // Check that invalid JSON results in an empty value with warnings.
    $invalid_json_event = $this->buildEvent('{not valid json}');
    $this->subscriber->onValuesChange($invalid_json_event);
    $this->assertSame('', $invalid_json_event->getValues()[0]);
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
