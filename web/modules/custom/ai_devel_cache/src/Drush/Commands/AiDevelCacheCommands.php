<?php

declare(strict_types=1);

namespace Drupal\ai_devel_cache\Drush\Commands;

use Drupal\ai_devel_cache\Cache\AiDevelCacheInterface;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for the AI Devel Cache module.
 */
class AiDevelCacheCommands extends DrushCommands {

  use AutowireTrait;

  /**
   * Constructs an AiDevelCacheCommands object.
   *
   * @param \Drupal\ai_devel_cache\Cache\AiDevelCacheInterface $cache
   *   The AI Devel Cache backend.
   */
  public function __construct(
    protected AiDevelCacheInterface $cache,
  ) {
    parent::__construct();
  }

  /**
   * Deletes every cached AI provider response.
   *
   * @command ai-devel-cache:clear
   * @usage drush ai-devel-cache:clear
   *   Delete every cached AI provider response from disk.
   */
  public function clear(): int {
    $deleted = $this->cache->clear();
    $this->output()->writeln(sprintf('Deleted %d cached AI %s.', $deleted, $deleted === 1 ? 'response' : 'responses'));
    return self::EXIT_SUCCESS;
  }

}
