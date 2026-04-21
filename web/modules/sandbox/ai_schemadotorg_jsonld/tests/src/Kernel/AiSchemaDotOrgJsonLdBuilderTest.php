<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld\Kernel;

use Drupal\ai_automators\AiAutomatorStatusField;
use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\NodeType;

/**
 * Tests AiSchemaDotOrgJsonLdBuilder.
 *
 * @group ai_schemadotorg_jsonld
 */
class AiSchemaDotOrgJsonLdBuilderTest extends AiSchemaDotOrgJsonLdTestBase {

  /**
   * Tests bundle-aware field creation and repair behavior.
   */
  public function testAddField(): void {
    // Create a page content type and add the Schema.org JSON-LD field to it.
    /** @var \Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface $builder */
    $builder = $this->container->get(AiSchemaDotOrgJsonLdBuilderInterface::class);
    NodeType::create(['type' => 'page', 'name' => 'Basic page'])->save();
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    /** @var \Drupal\Core\Config\ConfigFactoryInterface $config_factory */
    $config_factory = $this->container->get('config.factory');
    $config = $config_factory->getEditable('ai_schemadotorg_jsonld.settings');
    $entity_types = $config->get('entity_types') ?? [];
    unset($entity_types['node']);
    $config->set('entity_types', $entity_types)->save();

    $builder->addFieldToBundle('node', 'page');

    // Check that missing entity type settings are initialized on demand.
    $entity_type_settings = $config_factory
      ->get('ai_schemadotorg_jsonld.settings')
      ->get('entity_types.node');
    $this->assertNotNull($entity_type_settings, 'Node entity type settings are initialized on demand.');

    // Check that field storage exists.
    $field_storage = $this->entityTypeManager
      ->getStorage('field_storage_config')
      ->load('node.' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME);
    $this->assertNotNull($field_storage, 'FieldStorageConfig exists.');

    // Check that field instance exists and is translatable.
    $field_config = $this->entityTypeManager
      ->getStorage('field_config')
      ->load('node.page.' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME);
    $this->assertNotNull($field_config, 'FieldConfig exists.');
    $this->assertTrue($field_config->isTranslatable(), 'FieldConfig is translatable.');

    // Check that AI automator config exists.
    $automator = $this->entityTypeManager
      ->getStorage('ai_automator')
      ->load('node.page.' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME . '.default');
    $this->assertNotNull($automator, 'AiAutomator config entity exists.');

    // Check that AI automator status field storage exists.
    $status_field_storage = $this->entityTypeManager
      ->getStorage('field_storage_config')
      ->load('node.' . AiAutomatorStatusField::FIELD_NAME);
    $this->assertNotNull($status_field_storage, 'AI automator status FieldStorageConfig exists.');

    // Check that AI automator status field config exists.
    $status_field_config = $this->entityTypeManager
      ->getStorage('field_config')
      ->load('node.page.' . AiAutomatorStatusField::FIELD_NAME);
    $this->assertNotNull($status_field_config, 'AI automator status FieldConfig exists.');

    // Check that form display includes the field at weight 99.
    $form_display = $this->entityTypeManager
      ->getStorage('entity_form_display')
      ->load('node.page.default');
    $component = $form_display->getComponent(AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME);
    $this->assertNotNull($component, 'Form display component exists.');
    $this->assertSame(99, $component['weight'], 'Form display weight is 99.');

    // Check that view display includes the field at weight 99.
    $view_display = $this->entityTypeManager
      ->getStorage('entity_view_display')
      ->load('node.page.default');
    $component = $view_display->getComponent(AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME);
    $this->assertNotNull($component, 'View display component exists.');
    $this->assertSame('json', $component['type'], 'View display uses the JSON formatter.');
    $this->assertSame(99, $component['weight'], 'View display weight is 99.');

    // Delete downstream config to confirm repeated calls repair partial state.
    $automator->delete();
    $form_display->removeComponent(AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME)->save();
    $view_display->removeComponent(AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME)->save();

    $this->entityTypeManager->getStorage('ai_automator')->resetCache();

    // Check idempotency and repair support — calling again must recreate missing config.
    $builder->addFieldToBundle('node', 'page');

    $repaired_automator = $this->entityTypeManager
      ->getStorage('ai_automator')
      ->load('node.page.' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME . '.default');
    $this->assertNotNull($repaired_automator, 'AiAutomator is recreated when missing.');

    $repaired_form_display = $this->entityTypeManager
      ->getStorage('entity_form_display')
      ->load('node.page.default');
    $this->assertNotNull($repaired_form_display->getComponent(AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME), 'Form display component is recreated when missing.');

    $repaired_view_display = $this->entityTypeManager
      ->getStorage('entity_view_display')
      ->load('node.page.default');
    $this->assertNotNull($repaired_view_display->getComponent(AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME), 'View display component is recreated when missing.');

    // Check that wildcard bundle resolution adds the field to all current bundles.
    $builder->addFieldToBundles('node', ['*']);

    $article_field_config = $this->entityTypeManager
      ->getStorage('field_config')
      ->load('node.article.' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME);
    $this->assertNotNull($article_field_config, 'Wildcard bundle resolution adds the field to all current bundles.');

    // Check invalid bundle lists are rejected.
    try {
      $builder->addFieldToBundles('node', ['*', 'page']);
      $this->fail('Expected an InvalidArgumentException for mixed wildcard and explicit bundles.');
    }
    catch (\InvalidArgumentException $exception) {
      $this->assertSame('The bundles list for node cannot mix "*" with explicit bundle names.', $exception->getMessage());
    }

    // Check cascade delete — deleting FieldConfig should remove the automator.
    FieldConfig::load('node.page.' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME)->delete();
    $this->entityTypeManager->getStorage('ai_automator')->resetCache();
    $automator_after_delete = $this->entityTypeManager
      ->getStorage('ai_automator')
      ->load('node.page.' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME . '.default');
    $this->assertNull($automator_after_delete, 'AiAutomator is deleted when FieldConfig is deleted.');

    // Check that non-bundle entity types can use a synthetic bundle.
    $builder->addFieldToBundles('user', ['*']);

    $user_field_config = $this->entityTypeManager
      ->getStorage('field_config')
      ->load('user.user.' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME);
    $this->assertNotNull($user_field_config, 'User FieldConfig exists.');

    $user_automator = $this->entityTypeManager
      ->getStorage('ai_automator')
      ->load('user.user.' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME . '.default');
    $this->assertNotNull($user_automator, 'User AiAutomator config entity exists.');

    $user_form_display = $this->entityTypeManager
      ->getStorage('entity_form_display')
      ->load('user.user.default');
    $this->assertNotNull($user_form_display->getComponent(AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME), 'User form display component exists.');

    $user_view_display = $this->entityTypeManager
      ->getStorage('entity_view_display')
      ->load('user.user.default');
    $user_component = $user_view_display->getComponent(AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME);
    $this->assertNotNull($user_component, 'User view display component exists.');
    $this->assertSame('json', $user_component['type'], 'User view display uses the JSON formatter.');
  }

}
