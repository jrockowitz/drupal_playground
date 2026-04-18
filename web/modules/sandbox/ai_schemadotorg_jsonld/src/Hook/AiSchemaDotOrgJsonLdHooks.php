<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld\Hook;

use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBreadcrumbListInterface;
use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for the ai_schemadotorg_jsonld module.
 */
class AiSchemaDotOrgJsonLdHooks {

  use StringTranslationTrait;

  /**
   * Constructs an AiSchemaDotOrgJsonLdHooks object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   * @param \Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBreadcrumbListInterface $breadcrumbList
   *   The breadcrumb list service.
   */
  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly ModuleHandlerInterface $moduleHandler,
    protected readonly RouteMatchInterface $routeMatch,
    protected readonly AiSchemaDotOrgJsonLdBreadcrumbListInterface $breadcrumbList,
  ) {}

  /**
   * Implements hook_field_widget_action_info_alter().
   */
  #[Hook('field_widget_action_info_alter')]
  public function fieldWidgetActionInfoAlter(array &$definitions): void {
    if (!$this->moduleHandler->moduleExists('json_field_widget')) {
      return;
    }
    if (!isset($definitions['automator_json'])) {
      return;
    }
    if (!in_array('json_editor', $definitions['automator_json']['widget_types'])) {
      $definitions['automator_json']['widget_types'][] = 'json_editor';
    }
  }

  /**
   * Implements hook_page_attachments().
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    $config = $this->configFactory->get('ai_schemadotorg_jsonld.settings');

    // Attach breadcrumb JSON-LD.
    if ($config->get('breadcrumb_jsonld')) {
      $bubbleable_metadata = new BubbleableMetadata();
      $breadcrumb_data = $this->breadcrumbList->build($this->routeMatch, $bubbleable_metadata);
      if ($breadcrumb_data !== NULL) {
        $bubbleable_metadata->applyTo($attachments);
        $attachments['#attached']['html_head'][] = [
          [
            '#type' => 'html_tag',
            '#tag' => 'script',
            '#value' => json_encode($breadcrumb_data),
            '#attributes' => ['type' => 'application/ld+json'],
          ],
          'ai_schemadotorg_jsonld_breadcrumb',
        ];
      }
    }

    // Attach entity field JSON-LD on canonical entity routes.
    $entity = $this->getCurrentEntity();
    if (!$entity instanceof ContentEntityInterface) {
      return;
    }
    if (!$entity->hasField(AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME)) {
      return;
    }
    $field_value = $entity->get(AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME)->value;
    if (empty($field_value)) {
      $field_value = NULL;
    }

    $default_jsonld = $config->get('entity_types.' . $entity->getEntityTypeId() . '.default_jsonld');
    if (!empty($default_jsonld)) {
      $attachments['#attached']['html_head'][] = [
        [
          '#type' => 'html_tag',
          '#tag' => 'script',
          '#value' => $default_jsonld,
          '#attributes' => ['type' => 'application/ld+json'],
        ],
        'ai_schemadotorg_jsonld_default_' . $entity->getEntityTypeId(),
      ];
    }

    if ($field_value !== NULL) {
      $attachments['#attached']['html_head'][] = [
        [
          '#type' => 'html_tag',
          '#tag' => 'script',
          '#value' => $field_value,
          '#attributes' => ['type' => 'application/ld+json'],
        ],
        'ai_schemadotorg_jsonld_' . $entity->getEntityTypeId() . '_' . $entity->id(),
      ];
    }
  }

  /**
   * Returns the current page's canonical content entity, or NULL.
   *
   * Canonical routes follow the pattern: entity.{entity_type_id}.canonical.
   */
  protected function getCurrentEntity(): ?ContentEntityInterface {
    $route_name = $this->routeMatch->getRouteName();

    if ($route_name === NULL) {
      return NULL;
    }

    if (preg_match('/^entity\.(\w+)\.canonical$/', $route_name, $matches)) {
      $entity_type_id = $matches[1];
      return $this->routeMatch->getParameter($entity_type_id);
    }

    return NULL;
  }

  /**
   * Implements hook_field_widget_complete_form_alter().
   */
  #[Hook('field_widget_complete_form_alter')]
  public function fieldWidgetCompleteFormAlter(array &$field_widget_complete_form, FormStateInterface $form_state, array $context): void {
    $widget_id = $context['widget']->getPluginId();
    $field_name = $context['items']->getFieldDefinition()->getName();

    if ($field_name !== AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME) {
      return;
    }

    $allowed_widgets = ['json_textarea'];
    if ($this->moduleHandler->moduleExists('json_field_widget')) {
      $allowed_widgets[] = 'json_editor';
    }

    if (!in_array($widget_id, $allowed_widgets)) {
      return;
    }

    $field_widget_complete_form['copy_jsonld'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['ai-schemadotorg-jsonld-copy']],
      'button' => [
        '#type' => 'button',
        '#value' => $this->t('Copy JSON-LD'),
        '#attributes' => [
          'class' => ['ai-schemadotorg-jsonld-copy-button', 'button--extrasmall'],
          'data-field-name' => $field_name,
        ],
      ],
      'message' => [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#attributes' => ['class' => ['ai-schemadotorg-jsonld-copy-message']],
        '#plain_text' => $this->t('JSON-LD copied to clipboard…'),
      ],
      '#attached' => ['library' => ['ai_schemadotorg_jsonld/copy']],
    ];
  }

  /**
   * Implements hook_entity_field_access().
   *
   * @param string $operation
   *   The operation to be performed.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface>|null $items
   *   The field item list, or NULL if field access is checked without context.
   */
  #[Hook('entity_field_access')]
  public function entityFieldAccess(string $operation, FieldDefinitionInterface $field_definition, AccountInterface $account, ?FieldItemListInterface $items = NULL): AccessResultInterface {
    if ($field_definition->getName() !== AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME) {
      return AccessResult::neutral();
    }

    if ($operation === 'edit' && $items !== NULL) {
      $entity = $items->getEntity();
      if ($entity->isNew()) {
        return AccessResult::forbidden()
          ->addCacheableDependency($entity)
          ->setReason('Cannot edit Schema.org JSON-LD on unsaved entities.');
      }
    }

    if ($operation === 'view' && $items !== NULL) {
      $entity = $items->getEntity();
      if (!$entity->access('update', $account)) {
        return AccessResult::forbidden()
          ->cachePerUser()
          ->addCacheableDependency($entity);
      }
    }

    return AccessResult::neutral();
  }

}
