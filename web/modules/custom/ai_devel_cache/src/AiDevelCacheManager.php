<?php

declare(strict_types=1);

namespace Drupal\ai_devel_cache;

use Drupal\ai\OperationType\OutputInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Site\Settings;

/**
 * Filesystem-backed cache for AI provider responses.
 *
 * Responses are stored under the system temporary directory so that they
 * survive Drupal cache rebuilds (the `cache.ai` backend would not, which
 * makes it unsuitable for recipe re-applies).
 */
class AiDevelCacheManager implements AiDevelCacheManagerInterface {

  /**
   * Default subdirectory under sys_get_temp_dir() that holds cache files.
   *
   * Tests override this via the `ai_devel_cache_directory_name` Settings key
   * so that test runs don't trample a developer's local cache.
   */
  const DIRECTORY_NAME = 'drupal_ai_devel_cache';

  /**
   * The module's logger channel.
   */
  protected LoggerChannelInterface $logger;

  /**
   * Constructs the cache.
   *
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger channel factory.
   */
  public function __construct(
    protected FileSystemInterface $fileSystem,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('ai_devel_cache');
  }

  /**
   * {@inheritdoc}
   */
  public function get(string $hash): ?OutputInterface {
    $path = $this->payloadPath($hash);
    if (!is_file($path)) {
      return NULL;
    }
    $contents = @file_get_contents($path);
    if ($contents === FALSE || $contents === '') {
      return NULL;
    }
    // The cache is dev-only and the file was written by this module to a
    // private temp directory; allowing arbitrary classes is required because
    // provider responses include arbitrary normalized and raw output objects.
    // phpcs:ignore DrupalPractice.FunctionCalls.InsecureUnserialize.InsecureUnserialize
    $output = @unserialize($contents, ['allowed_classes' => TRUE]);
    if (!$output instanceof OutputInterface) {
      $this->logger->warning('Discarding unreadable AI cache entry at @path.', ['@path' => $path]);
      return NULL;
    }
    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function set(string $hash, OutputInterface $output, array $debug): void {
    $directory = $this->cacheDirectory();
    if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY)) {
      $this->logger->warning('Unable to prepare AI cache directory @directory.', ['@directory' => $directory]);
      return;
    }
    // Cached prompts and responses can include sensitive content; restrict the
    // directory to the owner since sys_get_temp_dir() is world-readable on
    // many systems.
    @chmod($directory, 0700);
    file_put_contents($this->payloadPath($hash), serialize($output));
    file_put_contents($this->sidecarPath($hash), json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  }

  /**
   * {@inheritdoc}
   */
  public function clear(): int {
    $directory = $this->cacheDirectory();
    if (!is_dir($directory)) {
      return 0;
    }
    $deleted = 0;
    foreach (glob($directory . '/*.bin') ?: [] as $payload) {
      $hash = basename($payload, '.bin');
      $sidecar = $this->sidecarPath($hash);
      if (@unlink($payload)) {
        $deleted++;
      }
      if (is_file($sidecar)) {
        @unlink($sidecar);
      }
    }
    return $deleted;
  }

  /**
   * {@inheritdoc}
   */
  public function list(): array {
    $directory = $this->cacheDirectory();
    if (!is_dir($directory)) {
      return [];
    }
    $entries = [];
    foreach (glob($directory . '/*.json') ?: [] as $sidecar) {
      $hash = basename($sidecar, '.json');
      $payload = $this->payloadPath($hash);
      $contents = @file_get_contents($sidecar);
      if ($contents === FALSE) {
        continue;
      }
      $metadata = json_decode($contents, TRUE);
      if (!is_array($metadata)) {
        continue;
      }
      $entries[] = [
        'hash' => $hash,
        'provider_id' => $metadata['provider_id'] ?? '',
        'operation_type' => $metadata['operation_type'] ?? '',
        'model_id' => $metadata['model_id'] ?? '',
        'tags' => is_array($metadata['tags'] ?? NULL) ? $metadata['tags'] : [],
        'input_preview' => $metadata['input_preview'] ?? '',
        'cached_at' => $metadata['cached_at'] ?? '',
        'bytes' => is_file($payload) ? (int) filesize($payload) : 0,
      ];
    }
    return $entries;
  }

  /**
   * {@inheritdoc}
   */
  public function directory(): string {
    return $this->cacheDirectory();
  }

  /**
   * Returns the cache directory path.
   *
   * @return string
   *   Absolute filesystem path to the cache directory.
   */
  protected function cacheDirectory(): string {
    $name = Settings::get('ai_devel_cache_directory_name', self::DIRECTORY_NAME);
    return rtrim(sys_get_temp_dir(), '/') . '/' . $name;
  }

  /**
   * Returns the absolute path of the payload file for a hash.
   *
   * @param string $hash
   *   The request hash.
   *
   * @return string
   *   The absolute path.
   */
  protected function payloadPath(string $hash): string {
    return $this->cacheDirectory() . '/' . $hash . '.bin';
  }

  /**
   * Returns the absolute path of the JSON debug sidecar for a hash.
   *
   * @param string $hash
   *   The request hash.
   *
   * @return string
   *   The absolute path.
   */
  protected function sidecarPath(string $hash): string {
    return $this->cacheDirectory() . '/' . $hash . '.json';
  }

}
