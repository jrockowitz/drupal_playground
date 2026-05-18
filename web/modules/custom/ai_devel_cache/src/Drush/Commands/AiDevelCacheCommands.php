<?php

declare(strict_types=1);

namespace Drupal\ai_devel_cache\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\ai_devel_cache\AiDevelCacheManagerInterface;
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
   * @param \Drupal\ai_devel_cache\AiDevelCacheManagerInterface $cache
   *   The AI Devel Cache backend.
   */
  public function __construct(
    protected AiDevelCacheManagerInterface $cache,
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
    $this->output()->writeln(sprintf('Deleted %d cached AI %s.', $deleted, ($deleted === 1) ? 'response' : 'responses'));
    return self::EXIT_SUCCESS;
  }

  /**
   * Lists every cached AI provider response.
   *
   * @command ai-devel-cache:list
   * @field-labels
   *   hash: Hash
   *   cached_at: Cached at
   *   provider_id: Provider
   *   operation_type: Operation
   *   model_id: Model
   *   tags: Tags
   *   bytes: Bytes
   *   input_preview: Input preview
   * @default-fields cached_at,provider_id,operation_type,model_id,tags,bytes,hash
   * @usage drush ai-devel-cache:list
   *   List every cached AI provider response written to disk.
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   Structured rows of cache entries.
   */
  public function list(): RowsOfFields {
    $rows = array_map(static function (array $entry): array {
      $entry['tags'] = implode(', ', $entry['tags']);
      return $entry;
    }, $this->cache->list());
    return new RowsOfFields($rows);
  }

}
