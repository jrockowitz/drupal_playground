<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_devel_cache\Kernel;

use Drupal\ai\OperationType\Embeddings\EmbeddingsOutput;
use Drupal\ai_devel_cache\AiDevelCacheManager;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Covers every method on AiDevelCacheManagerInterface.
 *
 * @coversDefaultClass \Drupal\ai_devel_cache\AiDevelCacheManager
 *
 * @group ai_devel_cache
 */
#[RunTestsInSeparateProcesses]
class AiDevelCacheManagerTest extends KernelTestBase {

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
  }

  /**
   * Round-trips every public method on the manager interface.
   */
  public function testManager(): void {
    $manager = $this->container->get('ai_devel_cache.manager');

    // Start from a known-empty cache; the on-disk directory under
    // sys_get_temp_dir() persists across runs in DDEV.
    $manager->clear();

    // Check directory(): returns an absolute path ending with the test-only
    // subdirectory name (proving the Settings override is honoured).
    $directory = $manager->directory();
    $this->assertNotSame('', $directory);
    $this->assertStringStartsWith('/', $directory);
    $this->assertStringEndsWith('/drupal_ai_devel_cache_test', $directory);
    $this->assertNotSame(AiDevelCacheManager::DIRECTORY_NAME, basename($directory));

    // Check get(): returns NULL for an unknown hash.
    $this->assertNull($manager->get('does-not-exist'));

    // Check list(): returns an empty array when nothing is cached.
    $this->assertSame([], $manager->list());

    // Check clear(): returns 0 when the cache is empty.
    $this->assertSame(0, $manager->clear());

    // Check set() + get() round-trip with a real OutputInterface.
    $hashA = str_repeat('a', 64);
    $outputA = new EmbeddingsOutput([0.1, 0.2, 0.3], ['raw' => TRUE], []);
    $debugA = [
      'provider_id' => 'openai',
      'operation_type' => 'embeddings',
      'model_id' => 'text-embedding-3-small',
      'tags' => ['unit_test'],
      'input_preview' => 'hello',
      'cached_at' => '2026-05-19 02:37:55',
    ];
    $manager->set($hashA, $outputA, $debugA);

    $retrievedA = $manager->get($hashA);
    $this->assertInstanceOf(EmbeddingsOutput::class, $retrievedA);
    $this->assertSame([0.1, 0.2, 0.3], $retrievedA->getNormalized());

    // Check that the directory was created and locked down to 0700.
    $this->assertDirectoryExists($directory);
    $this->assertSame('0700', substr(sprintf('%o', fileperms($directory)), -4));

    // Check list(): a single set() produces one entry whose metadata round
    // trips, whose tags survive as an array, and whose bytes count is > 0.
    $entriesAfterFirst = $manager->list();
    $this->assertCount(1, $entriesAfterFirst);
    $entry = $entriesAfterFirst[0];
    $this->assertSame($hashA, $entry['hash']);
    $this->assertSame('openai', $entry['provider_id']);
    $this->assertSame('embeddings', $entry['operation_type']);
    $this->assertSame('text-embedding-3-small', $entry['model_id']);
    $this->assertSame(['unit_test'], $entry['tags']);
    $this->assertSame('hello', $entry['input_preview']);
    $this->assertSame('2026-05-19 02:37:55', $entry['cached_at']);
    $this->assertGreaterThan(0, $entry['bytes']);

    // Check list(): a second set() adds another row.
    $hashB = str_repeat('b', 64);
    $outputB = new EmbeddingsOutput([0.9], [], []);
    $manager->set($hashB, $outputB, [
      'provider_id' => 'anthropic',
      'operation_type' => 'moderation',
      'model_id' => 'claude-moderation',
      'tags' => [],
      'input_preview' => 'second',
      'cached_at' => '2026-05-19 02:38:00',
    ]);
    $entriesAfterSecond = $manager->list();
    $this->assertCount(2, $entriesAfterSecond);

    // Check that list() falls back to [] for tags when the sidecar JSON has
    // no tags key (or a non-array value), so older entries keep loading.
    $hashLegacy = str_repeat('c', 64);
    $outputLegacy = new EmbeddingsOutput([0.5], [], []);
    $manager->set($hashLegacy, $outputLegacy, [
      'provider_id' => 'legacy',
      'operation_type' => 'embeddings',
      'model_id' => 'legacy-model',
      // 'tags' intentionally omitted.
      'input_preview' => 'legacy',
      'cached_at' => '2026-05-19 02:38:30',
    ]);
    $legacyEntry = array_values(array_filter(
      $manager->list(),
      static fn(array $row): bool => $row['hash'] === $hashLegacy,
    ))[0];
    $this->assertSame([], $legacyEntry['tags']);

    // Check clear(): returns the count of payloads deleted and empties the
    // listing afterwards.
    $deleted = $manager->clear();
    $this->assertSame(3, $deleted);
    $this->assertSame([], $manager->list());
    $this->assertNull($manager->get($hashA));
  }

}
