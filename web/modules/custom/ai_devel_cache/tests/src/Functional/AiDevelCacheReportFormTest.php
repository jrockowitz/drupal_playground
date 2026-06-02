<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_devel_cache\Functional;

use Drupal\ai\OperationType\Embeddings\EmbeddingsInput;
use Drupal\Tests\BrowserTestBase;

/**
 * Verifies the AI Devel Cache report form end-to-end.
 *
 * @group ai_devel_cache
 */
class AiDevelCacheReportFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'key',
    'file',
    'field',
    'ai',
    'ai_test',
    'ai_devel_cache',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Isolate test data from a developer's local cache by pointing the
    // manager at a test-only subdirectory under sys_get_temp_dir().
    $this->writeSettings([
      'settings' => [
        'ai_devel_cache_directory_name' => (object) [
          'value' => 'drupal_ai_devel_cache_test',
          'required' => TRUE,
        ],
      ],
    ]);
  }

  /**
   * Exercises empty state, population via mock provider, clear, and access.
   */
  public function testReport(): void {
    // Start from a known-empty test cache.
    $cache = $this->container->get('ai_devel_cache.manager');
    $cache->clear();

    $url = '/admin/config/ai/devel-cache';

    // Check that an anonymous user cannot reach the report.
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->drupalCreateUser(['administer site configuration']));

    // Check that the empty state renders the page with no entries and hides
    // the Clear cache button.
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('AI Devel Cache');
    $this->assertSession()->pageTextContains('Entries: 0');
    $this->assertSession()->pageTextContains('No AI provider responses are currently cached.');
    $this->assertSession()->buttonNotExists('Clear cache');

    // Check that calling the mock provider routes through ProviderProxy and
    // populates the cache via the subscriber.
    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    // The provider is a ProviderProxy that forwards to plugin operation
    // methods via __call(); static analysis can't see those signatures.
    // @phpstan-ignore-next-line method.notFound
    $provider->embeddings(new EmbeddingsInput('hello world'), 'test');
    // @phpstan-ignore-next-line method.notFound
    $provider->embeddings(new EmbeddingsInput('another prompt'), 'test');

    // Check that the populated report lists both entries, identifies the
    // provider and operation, and exposes the Clear cache button.
    $this->drupalGet($url);
    $this->assertSession()->pageTextContains('Entries: 2');
    $this->assertSession()->pageTextContains('echoai');
    $this->assertSession()->pageTextContains('embeddings');
    $this->assertSession()->buttonExists('Clear cache');

    // Check that submitting the Clear cache button wipes the cache and shows
    // a status message.
    $this->submitForm([], 'Clear cache');
    $this->assertSession()->pageTextContains('Deleted 2 cached AI responses.');
    $this->assertSession()->pageTextContains('Entries: 0');
    $this->assertSession()->buttonNotExists('Clear cache');
  }

}
