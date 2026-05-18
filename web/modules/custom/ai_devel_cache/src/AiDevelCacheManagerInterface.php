<?php

declare(strict_types=1);

namespace Drupal\ai_devel_cache;

use Drupal\ai\OperationType\OutputInterface;

/**
 * Stores and retrieves cached AI provider responses keyed by request hash.
 */
interface AiDevelCacheManagerInterface {

  /**
   * Returns the cached output for a hash, or NULL if not cached.
   *
   * @param string $hash
   *   The request hash.
   *
   * @return \Drupal\ai\OperationType\OutputInterface|null
   *   The cached output, or NULL on miss.
   */
  public function get(string $hash): ?OutputInterface;

  /**
   * Stores an output for a hash.
   *
   * @param string $hash
   *   The request hash.
   * @param \Drupal\ai\OperationType\OutputInterface $output
   *   The output to store.
   * @param array $debug
   *   Human-readable metadata about the request (provider, operation type,
   *   model, truncated input preview) written next to the payload for hand
   *   inspection.
   */
  public function set(string $hash, OutputInterface $output, array $debug): void;

  /**
   * Deletes every entry from the cache.
   *
   * @return int
   *   The number of cache entries that were deleted.
   */
  public function clear(): int;

  /**
   * Returns metadata for every cached entry.
   *
   * @return array
   *   A list of associative arrays, one per cached entry, with keys:
   *   - hash: The request hash.
   *   - provider_id: The provider that produced the response.
   *   - operation_type: The operation type (e.g. embeddings).
   *   - model_id: The model identifier.
   *   - tags: The tags supplied by the calling code.
   *   - input_preview: A truncated preview of the request input.
   *   - cached_at: Timestamp the entry was written, formatted Y-m-d H:i:s.
   *   - bytes: Size of the serialized payload on disk in bytes.
   */
  public function list(): array;

  /**
   * Returns the absolute path of the cache directory.
   *
   * @return string
   *   The filesystem path where cache entries are stored.
   */
  public function directory(): string;

}
