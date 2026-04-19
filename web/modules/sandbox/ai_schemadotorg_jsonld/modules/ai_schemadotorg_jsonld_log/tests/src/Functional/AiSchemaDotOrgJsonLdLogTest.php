<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld_log\Functional;

use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the log admin UI.
 *
 * @group ai_schemadotorg_jsonld_log
 */
class AiSchemaDotOrgJsonLdLogTest extends BrowserTestBase {

  /**
   * The logged node ID.
   */
  protected int $nodeId;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field',
    'field_ui',
    'file',
    'options',
    'field_widget_actions',
    'json_field',
    'json_field_widget',
    'token',
    'ai',
    'ai_automators',
    'ai_schemadotorg_jsonld',
    'ai_schemadotorg_jsonld_log',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Basic page',
    ]);
    $node = Node::create([
      'type' => 'page',
      'title' => 'Log node',
      'status' => 1,
    ]);
    $node->save();
    $this->nodeId = (int) $node->id();

    for ($index = 1; $index <= 25; $index++) {
      $label = sprintf('%02d', $index);
      $this->container->get('database')
        ->insert('ai_schemadotorg_jsonld_log')
        ->fields([
          'entity_type' => 'node',
          'entity_id' => (string) $this->nodeId,
          'entity_label' => 'Log node',
          'bundle' => 'page',
          'url' => 'https://example.com/node/' . $this->nodeId,
          'prompt' => 'Valid prompt ' . $label,
          'response' => '{"@type":"Thing","name":"Valid ' . $label . '"}',
          'valid' => 1,
          'created' => 1700000000 + $index,
        ])
        ->execute();
    }

    $this->container->get('database')
      ->insert('ai_schemadotorg_jsonld_log')
      ->fields([
        'entity_type' => 'node',
        'entity_id' => '5555',
        'entity_label' => 'Stored orphan node',
        'bundle' => 'page',
        'url' => 'https://example.com/node/5555',
        'prompt' => 'Stored orphan prompt',
        'response' => '{"@type":"Thing","name":"Stored orphan"}',
        'valid' => 1,
        'created' => 1790000000,
      ])
      ->execute();

    $this->container->get('database')
      ->insert('ai_schemadotorg_jsonld_log')
      ->fields([
        'entity_type' => 'node',
        'entity_id' => '9999',
        'entity_label' => '',
        'bundle' => '',
        'url' => '',
        'prompt' => 'Orphan prompt',
        'response' => '{not valid json}',
        'valid' => 0,
        'created' => 1800000000,
      ])
      ->execute();
  }

  /**
   * Tests the log page, CSV download, and clear flow.
   */
  public function testLogPageShowsRowsAndOperations(): void {
    $admin = $this->drupalCreateUser(['administer site configuration']);
    $this->drupalLogin($admin);

    // Check that the settings and log local tasks are registered.
    $local_tasks = $this->container->get('plugin.manager.menu.local_task')
      ->getLocalTasksForRoute('ai_schemadotorg_jsonld_log.view');
    $this->assertArrayHasKey(0, $local_tasks);
    $this->assertArrayHasKey('ai_schemadotorg_jsonld.settings', $local_tasks[0]);
    $this->assertArrayHasKey('ai_schemadotorg_jsonld_log.view', $local_tasks[0]);

    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld/log');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('AI Schema.org JSON-LD');
    $this->assertSession()->pageTextContains('Created');
    $this->assertSession()->pageTextContains('Entity');
    $this->assertSession()->pageTextContains('Prompt');
    $this->assertSession()->pageTextContains('Response');
    $this->assertSession()->pageTextContains('Valid');
    $this->assertSession()->pageTextContains('2027-01-15 19:00:00');
    $this->assertSession()->linkExists('Log node');
    $this->assertSession()->pageTextContains('Log node (node:page)');
    $this->assertSession()->responseContains('<th');
    $this->assertSession()->linkExists('Download CSV');
    $this->assertSession()->linkExists('Clear log');
    $this->assertSession()->elementExists('css', '.ai-schemadotorg-jsonld-log-page');
    $this->assertSession()->elementExists('css', 'pre.ai-schemadotorg-jsonld-log-page__content');
    $this->assertSession()->pageTextContains('"name": "Valid 25"');
    $this->assertSession()->pageTextContains('Yes');
    $this->assertSession()->pageTextContains('No');
    $this->assertSession()->pageTextNotContains('Valid prompt 05');
    $this->assertSession()->elementExists('css', 'nav.pager');
    $this->assertSession()->linkExists('Next ›');
    $this->assertSession()->elementExists('css', 'tr.ai-schemadotorg-jsonld-log-page__row--warning');
    $this->assertSession()->elementAttributeContains('css', 'a[href$="/admin/config/ai/schemadotorg-jsonld/log/download"]', 'class', 'button');
    $this->assertSession()->elementAttributeContains('css', 'a[href$="/admin/config/ai/schemadotorg-jsonld/log/download"]', 'class', 'button--small');
    $this->assertSession()->elementAttributeContains('css', 'a.use-ajax[href$="/admin/config/ai/schemadotorg-jsonld/log/clear"]', 'class', 'button');
    $this->assertSession()->elementAttributeContains('css', 'a.use-ajax[href$="/admin/config/ai/schemadotorg-jsonld/log/clear"]', 'class', 'button--small');
    $this->assertSession()->elementAttributeContains('css', 'a.use-ajax[href$="/admin/config/ai/schemadotorg-jsonld/log/clear"]', 'data-dialog-type', 'modal');
    $this->assertSession()->responseContains('css/ai_schemadotorg_jsonld_log.css');
    $this->assertSession()->responseContains('https://example.com/node/' . $this->nodeId);

    $content = $this->getSession()->getPage()->getContent();
    $this->assertLessThan(
      strpos($content, '>Entity<'),
      strpos($content, '>Created<')
    );
    $this->assertLessThan(
      strpos($content, 'ai-schemadotorg-jsonld-log-operations'),
      strpos($content, '<table')
    );

    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld/log/download');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('entity_type,entity_id,entity_label,bundle,url,prompt,response,valid,created');
    $this->assertSession()->responseContains('"2027-01-15 19:00:00"');
    $this->assertSession()->responseContains('"No"');
    $this->assertSession()->responseHeaderEquals('Content-Disposition', 'attachment; filename="ai-schemadotorg-jsonld-log.csv"');

    $this->config('ai_schemadotorg_jsonld_log.settings')
      ->set('enable', FALSE)
      ->save();
    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld/log');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Prompt and response logging is disabled.');
    $this->assertSession()->pageTextContains('Enable prompt and response logging in the Schema.org JSON-LD settings to view logs.');
    $this->assertSession()->elementNotExists('css', '.ai-schemadotorg-jsonld-log-page__table');
    $this->assertSession()->linkNotExists('Download CSV');
    $this->assertSession()->linkNotExists('Clear log');

    $this->config('ai_schemadotorg_jsonld_log.settings')
      ->set('enable', TRUE)
      ->save();

    $editor = $this->drupalCreateUser([
      'access content',
      'edit any page content',
    ]);
    $this->drupalLogin($editor);
    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld/log');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld/log/download');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld/log', [
      'query' => [
        'entity_type' => 'node',
        'entity_id' => $this->nodeId,
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('AI Schema.org JSON-LD: Log node');
    $this->assertSession()->pageTextNotContains('Entity');
    $this->assertSession()->pageTextContains('Valid prompt 25');
    $this->assertSession()->pageTextNotContains('Log node (node:page)');
    $this->assertSession()->pageTextNotContains('Stored orphan prompt');
    $this->assertSession()->pageTextNotContains('Orphan prompt');
    $this->assertSession()->pageTextContains('Yes');
    $this->assertSession()->responseNotContains('<td>No</td>');
    $this->assertSession()->linkExists('Download CSV');
    $this->assertSession()->linkNotExists('Clear log');
    $this->assertSession()->linkByHrefExists('/admin/config/ai/schemadotorg-jsonld/log/download?entity_type=node&entity_id=' . $this->nodeId);
    $this->assertSession()->linkByHrefExists('?entity_type=node&entity_id=' . $this->nodeId . '&page=1');

    $filtered_content = $this->getSession()->getPage()->getContent();
    $this->assertStringNotContainsString('>Entity<', $filtered_content);
    $this->assertStringNotContainsString('Log node (node:page)', $filtered_content);

    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld/log/download', [
      'query' => [
        'entity_type' => 'node',
        'entity_id' => $this->nodeId,
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('Valid prompt 25');
    $this->assertSession()->responseNotContains('Stored orphan prompt');
    $this->assertSession()->responseNotContains('Orphan prompt');
    $this->assertSession()->responseContains('"Yes"');
    $this->assertSession()->responseNotContains('"No"');
    $this->assertSession()->responseHeaderEquals('Content-Disposition', 'attachment; filename="ai-schemadotorg-jsonld-node-' . $this->nodeId . '-log.csv"');

    $viewer = $this->drupalCreateUser([
      'access content',
    ]);
    $this->drupalLogin($viewer);
    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld/log', [
      'query' => [
        'entity_type' => 'node',
        'entity_id' => $this->nodeId,
      ],
    ]);
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld/log/download', [
      'query' => [
        'entity_type' => 'node',
        'entity_id' => $this->nodeId,
      ],
    ]);
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($editor);
    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld/log', [
      'query' => [
        'entity_type' => 'node',
        'entity_id' => '999999',
      ],
    ]);
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld/log/download', [
      'query' => [
        'entity_type' => 'node',
        'entity_id' => '999999',
      ],
    ]);
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($admin);

    $node = Node::load($this->nodeId);
    $this->assertNotNull($node);
    $node->delete();
    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld/log');
    $this->assertSession()->pageTextContains('Orphan prompt');
    $this->assertSession()->pageTextContains('Stored orphan prompt');
    $this->assertSession()->pageTextContains('node:9999 (node)');
    $this->assertSession()->pageTextNotContains('Log node');
    $this->assertSession()->pageTextContains('Stored orphan node (node:page)');
    $this->assertSession()->linkExists('Stored orphan node');
    $this->assertSession()->linkByHrefExists('https://example.com/node/5555');

    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld/log/clear');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Are you sure you want to clear the AI Schema.org JSON-LD log?');

    $this->submitForm([], 'Clear log');
    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld/log');
    $this->assertSession()->pageTextContains('No log entries available.');
  }

}
