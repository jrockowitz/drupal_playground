<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld_log\Hook;

use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Field hook implementations for the AI Schema.org JSON-LD log module.
 */
class AiSchemaDotOrgJsonLdLogFieldHooks {

  use StringTranslationTrait;

  /**
   * Constructs an AiSchemaDotOrgJsonLdLogFieldHooks object.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(
    protected readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * Implements hook_field_widget_complete_json_textarea_form_alter().
   *
   * @param array $field_widget_complete_form
   *   The complete field widget form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $context
   *   The widget context.
   */
  #[Hook('field_widget_complete_json_textarea_form_alter')]
  #[Hook('field_widget_complete_json_editor_form_alter')]
  public function fieldWidgetCompleteFormAlter(array &$field_widget_complete_form, FormStateInterface $form_state, array $context): void {
    $field_name = $context['items']->getFieldDefinition()->getName();
    $entity = $context['items']->getEntity();

    if ($field_name !== AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME) {
      return;
    }
    if ($entity->isNew()) {
      return;
    }

    $field_widget_complete_form['view_log'] = $this->buildLogLink($entity);
  }

  /**
   * Builds the log link.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The logged entity.
   *
   * @return array
   *   The render array.
   */
  protected function buildLogLink(ContentEntityInterface $entity): array {
    $url = Url::fromRoute('ai_schemadotorg_jsonld_log.view', [], [
      'query' => [
        'entity_type' => $entity->getEntityTypeId(),
        'entity_id' => (string) $entity->id(),
      ],
    ]);

    return [
      '#type' => 'link',
      '#title' => $this->t('View log'),
      '#url' => $url,
      '#attributes' => [
        'class' => ['use-ajax', 'button', 'button--small'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode(['width' => '90%']),
      ],
      '#attached' => [
        'library' => ['core/drupal.dialog.ajax'],
      ],
      '#weight' => 101,
      '#access' => $entity->access('update', $this->currentUser),
    ];
  }

}
