<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_devel_cache\Kernel;

use Drupal\ai\Event\PostGenerateResponseEvent;
use Drupal\ai\Event\PreGenerateResponseEvent;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\OperationType\Embeddings\EmbeddingsInput;
use Drupal\ai\OperationType\Embeddings\EmbeddingsOutput;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies the event subscriber's hashing and tag-driven chat bypass.
 *
 * @coversDefaultClass \Drupal\ai_devel_cache\EventSubscriber\AiDevelCacheSubscriber
 *
 * @group ai_devel_cache
 */
#[RunTestsInSeparateProcesses]
class AiDevelCacheSubscriberTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'ai',
    'ai_devel_cache',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Isolate test data from a developer's local cache.
    $this->setSetting('ai_devel_cache_directory_name', 'drupal_ai_devel_cache_test');
    $this->installConfig(['ai_devel_cache']);
  }

  /**
   * Verifies miss-then-hit on embeddings and tag-driven chat bypass.
   */
  public function testCacheBehavior(): void {
    $dispatcher = $this->container->get('event_dispatcher');
    $cache = $this->container->get('ai_devel_cache.manager');

    // Start from a known-empty cache so we can count entries deterministically.
    $cache->clear();

    $input = new EmbeddingsInput('Hello, world.');

    // Check that the first embeddings dispatch is a cache miss.
    $preEvent = $this->newEmbeddingsPreEvent($input);
    $dispatcher->dispatch($preEvent, PreGenerateResponseEvent::EVENT_NAME);
    $this->assertNull($preEvent->getForcedOutputObject(), 'First request is a cache miss.');

    // Store an output via the post event.
    $output = new EmbeddingsOutput([0.1, 0.2, 0.3], ['raw' => TRUE], []);
    $postEvent = new PostGenerateResponseEvent(
      requestThreadId: $preEvent->getRequestThreadId(),
      providerId: 'openai',
      operationType: 'embeddings',
      configuration: [],
      input: $input,
      modelId: 'text-embedding-3-small',
      output: $output,
    );
    $dispatcher->dispatch($postEvent, PostGenerateResponseEvent::EVENT_NAME);

    // Check that re-dispatching the same request returns a cache hit.
    $preEventAgain = $this->newEmbeddingsPreEvent($input);
    $dispatcher->dispatch($preEventAgain, PreGenerateResponseEvent::EVENT_NAME);
    $forced = $preEventAgain->getForcedOutputObject();
    $this->assertInstanceOf(EmbeddingsOutput::class, $forced);
    $this->assertSame([0.1, 0.2, 0.3], $forced->getNormalized());

    // Check that the sidecar JSON is written next to the payload with the
    // expected metadata fields, and that list() and directory() surface it.
    $entries = $cache->list();
    $this->assertCount(1, $entries);
    $entry = reset($entries);
    $this->assertSame('openai', $entry['provider_id']);
    $this->assertSame('embeddings', $entry['operation_type']);
    $this->assertSame('text-embedding-3-small', $entry['model_id']);
    $this->assertNotEmpty($entry['hash']);
    $this->assertNotEmpty($entry['cached_at']);
    $this->assertGreaterThan(0, $entry['bytes']);
    $this->assertNotEmpty($cache->directory());

    // Check that a different prompt is a cache miss.
    $otherInput = new EmbeddingsInput('Different prompt.');
    $preEventOther = $this->newEmbeddingsPreEvent($otherInput);
    $dispatcher->dispatch($preEventOther, PreGenerateResponseEvent::EVENT_NAME);
    $this->assertNull($preEventOther->getForcedOutputObject(), 'Distinct prompt does not hit the cache.');

    // Check that chat tagged with an uncacheable tag is bypassed entirely:
    // post does not store and a subsequent pre does not hit.
    $chatInput = new ChatInput([new ChatMessage('user', 'Hello, world.')]);
    $chatPre = $this->newChatPreEvent($chatInput, ['ai_assistant_api_assistant_message']);
    $dispatcher->dispatch($chatPre, PreGenerateResponseEvent::EVENT_NAME);
    $this->assertNull($chatPre->getForcedOutputObject());

    $chatPost = new PostGenerateResponseEvent(
      requestThreadId: $chatPre->getRequestThreadId(),
      providerId: 'openai',
      operationType: 'chat',
      configuration: ['temperature' => 0.7],
      input: $chatInput,
      modelId: 'gpt-4o-mini',
      tags: ['ai_assistant_api_assistant_message'],
      output: new ChatOutput(new ChatMessage('assistant', 'Hi there.'), [], []),
    );
    $dispatcher->dispatch($chatPost, PostGenerateResponseEvent::EVENT_NAME);

    $chatPreAgain = $this->newChatPreEvent($chatInput, ['ai_assistant_api_assistant_message']);
    $dispatcher->dispatch($chatPreAgain, PreGenerateResponseEvent::EVENT_NAME);
    $this->assertNull($chatPreAgain->getForcedOutputObject(), 'Chat with an uncacheable tag is never cached.');

    // Check that chat without any matching tag IS cached (automator workflow).
    $automatorChatInput = new ChatInput([new ChatMessage('user', 'Automator prompt.')]);
    $automatorPre = $this->newChatPreEvent($automatorChatInput, ['ai_automators']);
    $dispatcher->dispatch($automatorPre, PreGenerateResponseEvent::EVENT_NAME);
    $this->assertNull($automatorPre->getForcedOutputObject(), 'First automator chat is a miss.');

    $automatorPost = new PostGenerateResponseEvent(
      requestThreadId: $automatorPre->getRequestThreadId(),
      providerId: 'openai',
      operationType: 'chat',
      configuration: ['temperature' => 0.7],
      input: $automatorChatInput,
      modelId: 'gpt-4o-mini',
      tags: ['ai_automators'],
      output: new ChatOutput(new ChatMessage('assistant', 'Generated value.'), [], []),
    );
    $dispatcher->dispatch($automatorPost, PostGenerateResponseEvent::EVENT_NAME);

    $automatorPreAgain = $this->newChatPreEvent($automatorChatInput, ['ai_automators']);
    $dispatcher->dispatch($automatorPreAgain, PreGenerateResponseEvent::EVENT_NAME);
    $forcedAutomator = $automatorPreAgain->getForcedOutputObject();
    $this->assertInstanceOf(ChatOutput::class, $forcedAutomator, 'Untagged-or-allowed chat hits the cache.');

    // Check that the sidecar records the tags supplied with the request.
    $entriesWithTags = array_values(array_filter(
      $cache->list(),
      static fn(array $entry): bool => $entry['operation_type'] === 'chat',
    ));
    $this->assertCount(1, $entriesWithTags);
    $this->assertSame(['ai_automators'], $entriesWithTags[0]['tags']);

    // Check that configuration key order does not affect the hash. Two
    // events with the same configuration in different key orders should
    // resolve to the same cached payload.
    $configOrderA = [
      'temperature' => 0,
      'response_format' => 'json',
      'custom_provider_option' => 'value',
    ];
    $configOrderB = [
      'custom_provider_option' => 'value',
      'response_format' => 'json',
      'temperature' => 0,
    ];

    $orderInput = new EmbeddingsInput('Stable hash check.');
    $orderPre = new PreGenerateResponseEvent(
      requestThreadId: bin2hex(random_bytes(8)),
      providerId: 'openai',
      operationType: 'embeddings',
      configuration: $configOrderA,
      input: $orderInput,
      modelId: 'text-embedding-3-small',
    );
    $dispatcher->dispatch($orderPre, PreGenerateResponseEvent::EVENT_NAME);
    $orderPost = new PostGenerateResponseEvent(
      requestThreadId: $orderPre->getRequestThreadId(),
      providerId: 'openai',
      operationType: 'embeddings',
      configuration: $configOrderA,
      input: $orderInput,
      modelId: 'text-embedding-3-small',
      output: new EmbeddingsOutput([0.9], [], []),
    );
    $dispatcher->dispatch($orderPost, PostGenerateResponseEvent::EVENT_NAME);

    $orderPreAgain = new PreGenerateResponseEvent(
      requestThreadId: bin2hex(random_bytes(8)),
      providerId: 'openai',
      operationType: 'embeddings',
      configuration: $configOrderB,
      input: $orderInput,
      modelId: 'text-embedding-3-small',
    );
    $dispatcher->dispatch($orderPreAgain, PreGenerateResponseEvent::EVENT_NAME);
    $this->assertInstanceOf(EmbeddingsOutput::class, $orderPreAgain->getForcedOutputObject(), 'Hash is stable across configuration key order.');
  }

  /**
   * Builds an embeddings PreGenerateResponseEvent.
   *
   * @param \Drupal\ai\OperationType\Embeddings\EmbeddingsInput $input
   *   The embeddings input.
   *
   * @return \Drupal\ai\Event\PreGenerateResponseEvent
   *   The event ready to dispatch.
   */
  protected function newEmbeddingsPreEvent(EmbeddingsInput $input): PreGenerateResponseEvent {
    return new PreGenerateResponseEvent(
      requestThreadId: bin2hex(random_bytes(8)),
      providerId: 'openai',
      operationType: 'embeddings',
      configuration: [],
      input: $input,
      modelId: 'text-embedding-3-small',
    );
  }

  /**
   * Builds a chat PreGenerateResponseEvent.
   *
   * @param \Drupal\ai\OperationType\Chat\ChatInput $input
   *   The chat input.
   * @param array $tags
   *   Tags to set on the event.
   *
   * @return \Drupal\ai\Event\PreGenerateResponseEvent
   *   The event ready to dispatch.
   */
  protected function newChatPreEvent(ChatInput $input, array $tags = []): PreGenerateResponseEvent {
    return new PreGenerateResponseEvent(
      requestThreadId: bin2hex(random_bytes(8)),
      providerId: 'openai',
      operationType: 'chat',
      configuration: ['temperature' => 0.7],
      input: $input,
      modelId: 'gpt-4o-mini',
      tags: $tags,
    );
  }

}
