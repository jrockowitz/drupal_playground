<?php

declare(strict_types=1);

namespace Drupal\ai_devel_cache\Cache;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\ai\OperationType\OutputInterface;

/**
 * Filesystem-backed cache for AI provider responses.
 *
 * Responses are stored under the system temporary directory so that they
 * survive Drupal cache rebuilds (the `cache.ai` backend would not, which
 * makes it unsuitable for recipe re-applies).
 */
class AiDevelCacheFilesystemBackend implements AiDevelCacheInterface {

  /**
   * Subdirectory under sys_get_temp_dir() that holds cache files.
   */
  const DIRECTORY_NAME = 'drupal_ai_devel_cache';

  /**
   * The module's logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
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
    file_put_contents($this->payloadPath($hash), serialize($output));
    file_put_contents($this->sidecarPath($hash), json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  }

  /**
   * Returns the cache directory path.
   *
   * @return string
   *   Absolute filesystem path to the cache directory.
   */
  protected function cacheDirectory(): string {
    return rtrim(sys_get_temp_dir(), '/') . '/' . self::DIRECTORY_NAME;
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
