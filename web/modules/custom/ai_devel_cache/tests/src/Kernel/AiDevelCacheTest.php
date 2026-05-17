<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_devel_cache\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Event\PostGenerateResponseEvent;
use Drupal\ai\Event\PreGenerateResponseEvent;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\OperationType\Embeddings\EmbeddingsInput;
use Drupal\ai\OperationType\Embeddings\EmbeddingsOutput;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Verifies that the AI Devel Cache short-circuits repeated non-chat requests.
 *
 * @group ai_devel_cache
 */
#[RunTestsInSeparateProcesses]
class AiDevelCacheTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'ai',
    'ai_devel_cache',
  ];

  /**
   * Verifies miss-then-hit on embeddings and full bypass on chat.
   */
  public function testCacheBehavior(): void {
    $dispatcher = $this->container->get('event_dispatcher');
    assert($dispatcher instanceof EventDispatcherInterface);

    // Use an isolated cache directory for this test.
    $directory = $this->siteDirectory . '/ai_devel_cache_test';
    $this->container->get('file_system')->prepareDirectory($directory, 1);
    putenv('TMPDIR=' . $directory);

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

    // Check that a different prompt is a cache miss.
    $otherInput = new EmbeddingsInput('Different prompt.');
    $preEventOther = $this->newEmbeddingsPreEvent($otherInput);
    $dispatcher->dispatch($preEventOther, PreGenerateResponseEvent::EVENT_NAME);
    $this->assertNull($preEventOther->getForcedOutputObject(), 'Distinct prompt does not hit the cache.');

    // Check that chat operations are bypassed entirely — post does not store,
    // and a subsequent pre does not hit.
    $chatInput = new ChatInput([new ChatMessage('user', 'Hello, world.')]);
    $chatPre = $this->newChatPreEvent($chatInput);
    $dispatcher->dispatch($chatPre, PreGenerateResponseEvent::EVENT_NAME);
    $this->assertNull($chatPre->getForcedOutputObject());

    $chatPost = new PostGenerateResponseEvent(
      requestThreadId: $chatPre->getRequestThreadId(),
      providerId: 'openai',
      operationType: 'chat',
      configuration: ['temperature' => 0.7],
      input: $chatInput,
      modelId: 'gpt-4o-mini',
      output: new ChatOutput(new ChatMessage('assistant', 'Hi there.'), [], []),
    );
    $dispatcher->dispatch($chatPost, PostGenerateResponseEvent::EVENT_NAME);

    $chatPreAgain = $this->newChatPreEvent($chatInput);
    $dispatcher->dispatch($chatPreAgain, PreGenerateResponseEvent::EVENT_NAME);
    $this->assertNull($chatPreAgain->getForcedOutputObject(), 'Chat operations are never cached.');
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
   *
   * @return \Drupal\ai\Event\PreGenerateResponseEvent
   *   The event ready to dispatch.
   */
  protected function newChatPreEvent(ChatInput $input): PreGenerateResponseEvent {
    return new PreGenerateResponseEvent(
      requestThreadId: bin2hex(random_bytes(8)),
      providerId: 'openai',
      operationType: 'chat',
      configuration: ['temperature' => 0.7],
      input: $input,
      modelId: 'gpt-4o-mini',
    );
  }

}
