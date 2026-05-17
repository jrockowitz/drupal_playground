<?php

declare(strict_types=1);

namespace Drupal\ai_devel_cache\EventSubscriber;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\ai\Event\PostGenerateResponseEvent;
use Drupal\ai\Event\PreGenerateResponseEvent;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\InputBase;
use Drupal\ai_devel_cache\Cache\AiDevelCacheInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Caches AI provider responses around pre/post generate events.
 *
 * On the pre-event the request is hashed; on a hit the cached output is
 * forced onto the event so the provider is never called. On a miss the
 * post-event stores the response under the same hash.
 */
class AiDevelCacheSubscriber implements EventSubscriberInterface {

  /**
   * Configuration keys that influence model output and belong in the hash.
   *
   * Other configuration keys (API keys, request identifiers, retry counters)
   * are intentionally excluded so that ephemeral changes do not invalidate
   * the cache.
   */
  const HASHABLE_CONFIGURATION_KEYS = [
    'temperature',
    'top_p',
    'top_k',
    'max_tokens',
    'max_output_tokens',
    'frequency_penalty',
    'presence_penalty',
    'seed',
    'stop',
    'response_format',
  ];

  /**
   * Maximum bytes of input preview written to the JSON sidecar.
   */
  const INPUT_PREVIEW_LIMIT = 500;

  /**
   * Hashes computed in the pre-event, keyed by request thread id.
   *
   * Used by the post-event so it doesn't have to recompute the hash.
   *
   * @var array
   */
  protected array $pendingHashes = [];

  /**
   * The module's logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * Constructs the subscriber.
   *
   * @param \Drupal\ai_devel_cache\Cache\AiDevelCacheInterface $cache
   *   The response cache backend.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger channel factory.
   */
  public function __construct(
    protected AiDevelCacheInterface $cache,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('ai_devel_cache');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PreGenerateResponseEvent::EVENT_NAME => ['onPreGenerate', 1000],
      PostGenerateResponseEvent::EVENT_NAME => ['onPostGenerate', -1000],
    ];
  }

  /**
   * Returns a cached response, if one exists.
   *
   * @param \Drupal\ai\Event\PreGenerateResponseEvent $event
   *   The pre-generate event.
   */
  public function onPreGenerate(PreGenerateResponseEvent $event): void {
    if ($event->getOperationType() === 'chat') {
      return;
    }
    if ($this->isStreaming($event->getInput())) {
      return;
    }
    $hash = $this->hash($event);
    if ($hash === NULL) {
      return;
    }
    $this->pendingHashes[$event->getRequestThreadId()] = $hash;
    $cached = $this->cache->get($hash);
    if ($cached !== NULL) {
      $this->logger->info('AI cache hit for @provider/@operation/@model (@hash).', [
        '@provider' => $event->getProviderId(),
        '@operation' => $event->getOperationType(),
        '@model' => $event->getModelId(),
        '@hash' => $hash,
      ]);
      $event->setForcedOutputObject($cached);
    }
  }

  /**
   * Stores the response from a cache miss.
   *
   * @param \Drupal\ai\Event\PostGenerateResponseEvent $event
   *   The post-generate event.
   */
  public function onPostGenerate(PostGenerateResponseEvent $event): void {
    if ($event->getOperationType() === 'chat') {
      return;
    }
    $thread = $event->getRequestThreadId();
    if (!isset($this->pendingHashes[$thread])) {
      return;
    }
    $hash = $this->pendingHashes[$thread];
    unset($this->pendingHashes[$thread]);
    if ($this->isStreaming($event->getInput())) {
      return;
    }
    $debug = [
      'provider_id' => $event->getProviderId(),
      'operation_type' => $event->getOperationType(),
      'model_id' => $event->getModelId(),
      'input_preview' => $this->inputPreview($event->getInput()),
      'cached_at' => date(\DateTimeInterface::ATOM),
    ];
    $this->cache->set($hash, $event->getOutput(), $debug);
  }

  /**
   * Computes a stable SHA-256 hash for the request.
   *
   * @param \Drupal\ai\Event\PreGenerateResponseEvent $event
   *   The pre-generate event.
   *
   * @return string|null
   *   The hash, or NULL if the input could not be normalized.
   */
  protected function hash(PreGenerateResponseEvent $event): ?string {
    $normalized = $this->normalizeInput($event->getInput());
    if ($normalized === NULL) {
      return NULL;
    }
    $configuration = array_intersect_key(
      $event->getConfiguration(),
      array_flip(self::HASHABLE_CONFIGURATION_KEYS)
    );
    ksort($configuration);
    $material = [
      'provider_id' => $event->getProviderId(),
      'operation_type' => $event->getOperationType(),
      'model_id' => $event->getModelId(),
      'input' => $normalized,
      'configuration' => $configuration,
    ];
    return hash('sha256', json_encode($material, JSON_UNESCAPED_SLASHES));
  }

  /**
   * Normalizes an input value to a stable array representation.
   *
   * @param mixed $input
   *   The input from the event.
   *
   * @return array|string|null
   *   A normalized representation, or NULL if the input cannot be cached.
   */
  protected function normalizeInput(mixed $input): array|string|null {
    if ($input === NULL) {
      return ['_kind' => 'null'];
    }
    if (is_string($input)) {
      return ['_kind' => 'string', 'value' => $input];
    }
    if ($input instanceof InputBase) {
      $array = $input->toArray();
      // Debug data is per-request bookkeeping; it must not change the hash.
      unset($array['debug_data']);
      return $array;
    }
    if (is_object($input) && method_exists($input, 'toArray')) {
      return $input->toArray();
    }
    if (is_array($input)) {
      return $input;
    }
    return NULL;
  }

  /**
   * Returns TRUE if the input requests a streaming response.
   *
   * Streaming responses cannot be replayed from a cache and are skipped.
   *
   * @param mixed $input
   *   The input from the event.
   *
   * @return bool
   *   TRUE if streaming.
   */
  protected function isStreaming(mixed $input): bool {
    return $input instanceof ChatInput && $input->isStreamedOutput();
  }

  /**
   * Builds a truncated human-readable preview of the input for the sidecar.
   *
   * @param mixed $input
   *   The input from the event.
   *
   * @return string
   *   A short preview string.
   */
  protected function inputPreview(mixed $input): string {
    if ($input instanceof InputBase && method_exists($input, 'toString')) {
      $text = $input->toString();
    }
    elseif (is_string($input)) {
      $text = $input;
    }
    else {
      $text = json_encode($this->normalizeInput($input), JSON_UNESCAPED_SLASHES) ?: '';
    }
    if (mb_strlen($text) > self::INPUT_PREVIEW_LIMIT) {
      $text = mb_substr($text, 0, self::INPUT_PREVIEW_LIMIT) . '…';
    }
    return $text;
  }

}
