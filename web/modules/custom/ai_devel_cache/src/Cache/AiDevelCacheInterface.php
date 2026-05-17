<?php

declare(strict_types=1);

namespace Drupal\ai_devel_cache\Cache;

use Drupal\ai\OperationType\OutputInterface;

/**
 * Stores and retrieves cached AI provider responses keyed by request hash.
 */
interface AiDevelCacheInterface {

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

}
