<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld\Functional;

use Drupal\ai_automators\Entity\AiAutomator;
use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the AI Schema.org JSON-LD prompt form.
 *
 * @group ai_schemadotorg_jsonld
 */
class AiSchemaDotOrgJsonLdPromptFormTest extends BrowserTestBase {

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
    'ai',
    'ai_automators',
    'ai_schemadotorg_jsonld',
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

    $admin = $this->drupalCreateUser(['administer site configuration']);
    $this->drupalLogin($admin);

    $this->container->get(AiSchemaDotOrgJsonLdBuilderInterface::class)
      ->addFieldToEntity('node', 'page');
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
  }

  /**
   * Tests the prompt form loads and updates the matching automator.
   */
  public function testPromptFormUpdatesAutomatorPrompt(): void {
    $route = $this->container->get('router.route_provider')
      ->getRouteByName('ai_schemadotorg_jsonld.prompt');
    $this->assertSame('/admin/config/ai/schemadotorg-jsonld/prompt/{entity_type}/{bundle}', $route->getPath());

    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld/prompt/node/page');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('prompt');
    $prompt_field = $this->getSession()->getPage()->findField('prompt');
    $this->assertNotNull($prompt_field);
    $this->assertNotSame('', $prompt_field->getValue());
    $this->assertSession()->fieldNotExists('label');

    $this->submitForm([
      'prompt' => 'Updated prompt token',
    ], 'Save');

    $automator = $this->container->get('entity_type.manager')
      ->getStorage('ai_automator')
      ->load('node.page.' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME . '.default');
    $this->assertInstanceOf(AiAutomator::class, $automator);
    $this->assertSame('Updated prompt token', $automator->get('token'));
  }

}
