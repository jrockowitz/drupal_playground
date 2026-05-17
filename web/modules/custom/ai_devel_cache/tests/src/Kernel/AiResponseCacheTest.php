<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_devel_cache\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Event\PostGenerateResponseEvent;
use Drupal\ai\Event\PreGenerateResponseEvent;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Verifies that the AI Devel Cache short-circuits repeated requests.
 *
 * @group ai_devel_cache
 */
#[RunTestsInSeparateProcesses]
class AiResponseCacheTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'ai',
    'ai_devel_cache',
  ];

  /**
   * Verifies cache miss-then-hit, hash stability, and streaming bypass.
   */
  public function testCacheMissThenHit(): void {
    $dispatcher = $this->container->get('event_dispatcher');
    assert($dispatcher instanceof EventDispatcherInterface);

    // Use an isolated cache directory for this test.
    $directory = $this->siteDirectory . '/ai_devel_cache_test';
    $this->container->get('file_system')->prepareDirectory($directory, 1);
    putenv('TMPDIR=' . $directory);

    $input = new ChatInput([new ChatMessage('user', 'Hello, world.')]);

    // Check that the first dispatch is a cache miss.
    $preEvent = $this->newPreEvent($input);
    $dispatcher->dispatch($preEvent, PreGenerateResponseEvent::EVENT_NAME);
    $this->assertNull($preEvent->getForcedOutputObject(), 'First request is a cache miss.');

    // Store an output via the post event.
    $message = new ChatMessage('assistant', 'Hi there.');
    $output = new ChatOutput($message, ['raw' => TRUE], []);
    $postEvent = new PostGenerateResponseEvent(
      requestThreadId: $preEvent->getRequestThreadId(),
      providerId: 'openai',
      operationType: 'chat',
      configuration: ['temperature' => 0.7],
      input: $input,
      modelId: 'gpt-4o-mini',
      output: $output,
    );
    $dispatcher->dispatch($postEvent, PostGenerateResponseEvent::EVENT_NAME);

    // Check that re-dispatching the same request returns a cache hit.
    $preEventAgain = $this->newPreEvent($input);
    $dispatcher->dispatch($preEventAgain, PreGenerateResponseEvent::EVENT_NAME);
    $forced = $preEventAgain->getForcedOutputObject();
    $this->assertInstanceOf(ChatOutput::class, $forced);
    $this->assertSame('Hi there.', $forced->getNormalized()->getText());

    // Check that a different prompt is a cache miss.
    $otherInput = new ChatInput([new ChatMessage('user', 'Different prompt.')]);
    $preEventOther = $this->newPreEvent($otherInput);
    $dispatcher->dispatch($preEventOther, PreGenerateResponseEvent::EVENT_NAME);
    $this->assertNull($preEventOther->getForcedOutputObject(), 'Distinct prompt does not hit the cache.');

    // Check that streaming inputs bypass the cache entirely.
    $streamInput = new ChatInput([new ChatMessage('user', 'Hello, world.')]);
    $streamInput->setStreamedOutput(TRUE);
    $preEventStream = $this->newPreEvent($streamInput);
    $dispatcher->dispatch($preEventStream, PreGenerateResponseEvent::EVENT_NAME);
    $this->assertNull($preEventStream->getForcedOutputObject(), 'Streaming requests bypass the cache.');
  }

  /**
   * Builds a PreGenerateResponseEvent matching the test scenario.
   *
   * @param \Drupal\ai\OperationType\Chat\ChatInput $input
   *   The chat input.
   *
   * @return \Drupal\ai\Event\PreGenerateResponseEvent
   *   The event ready to dispatch.
   */
  protected function newPreEvent(ChatInput $input): PreGenerateResponseEvent {
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
