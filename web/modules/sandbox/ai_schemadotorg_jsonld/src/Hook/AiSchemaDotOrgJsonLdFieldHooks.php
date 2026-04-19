<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld\Hook;

use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Field hook implementations for the ai_schemadotorg_jsonld module.
 */
class AiSchemaDotOrgJsonLdFieldHooks {

  use StringTranslationTrait;

  /**
   * Constructs an AiSchemaDotOrgJsonLdFieldHooks object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Routing\RedirectDestinationInterface $redirectDestination
   *   The redirect destination service.
   */
  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly AccountProxyInterface $currentUser,
    protected readonly ModuleHandlerInterface $moduleHandler,
    protected readonly RedirectDestinationInterface $redirectDestination,
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
      $t_args = [
        '@entity' => $entity->getEntityType()->getSingularLabel(),
      ];
      $field_widget_complete_form = $this->buildMessages(
        $this->t('Schema.org JSON-LD can be generated after the @entity is saved.', $t_args),
        'warning',
      );
      return;
    }

    $field_widget_complete_form['widget'][0]['copy_jsonld_description'] = $this->buildCopyJsonLdDescription();
    $field_widget_complete_form['copy_jsonld'] = $this->buildCopyJsonLdButton($field_name);
    $field_widget_complete_form['edit_prompt'] = $this->buildEditPromptLink($entity);
  }

  /**
   * Implements hook_field_widget_single_element_json_textarea_form_alter().
   *
   * @param array $element
   *   The field widget form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $context
   *   The widget context.
   */
  #[Hook('field_widget_single_element_json_textarea_form_alter')]
  #[Hook('field_widget_single_element_json_editor_form_alter')]
  public function fieldWidgetSingleElementFormAlter(array &$element, FormStateInterface $form_state, array $context): void {
  }

  /**
   * Builds a theme-native inline message render array.
   *
   * @param string|array|\Drupal\Component\Render\MarkupInterface $messages
   *   The message text, markup, or list of messages.
   * @param string $type
   *   The message type.
   */
  protected function buildMessages(string|array|MarkupInterface $messages, string $type): array {
    if ($messages instanceof MarkupInterface) {
      $messages = (string) $messages;
    }
    $messages = (array) $messages;

    $type = in_array($type, ['status', 'warning', 'error']) ? $type : 'status';

    return [
      '#theme' => 'status_messages',
      '#message_list' => [
        $type => $messages,
      ],
      '#status_headings' => [
        'status' => $this->t('Status message'),
        'warning' => $this->t('Warning message'),
        'error' => $this->t('Error message'),
      ],
    ];
  }

  /**
   * Builds the edit prompt link.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity whose prompt should be edited.
   */
  protected function buildEditPromptLink(ContentEntityInterface $entity): array {
    $url = Url::fromRoute('ai_schemadotorg_jsonld.prompt', [
      'entity_type' => $entity->getEntityTypeId(),
      'bundle' => $entity->bundle(),
    ]);
    $url->setOption('query', [
      'destination' => $this->redirectDestination->get(),
    ]);

    return [
      '#type' => 'link',
      '#title' => $this->t('Edit prompt'),
      '#url' => $url,
      '#attributes' => [
        'class' => ['use-ajax', 'button', 'button--small'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode(['width' => 900]),
      ],
      '#attached' => [
        'library' => ['core/drupal.dialog.ajax'],
      ],
      '#weight' => 100,
      '#access' => (
        (bool) $this->configFactory->get('ai_schemadotorg_jsonld.settings')->get('development.edit_prompt')
        && $this->currentUser->hasPermission('administer site configuration')
      ),
    ];
  }

  /**
   * Builds the copy JSON-LD description.
   */
  protected function buildCopyJsonLdDescription(): array {
    $description = new TranslatableMarkup('Please copy and paste the above Schema.org JSON-LD into the <a href=":schema_href">Schema Markup Validator</a> or <a href=":google_href">Google\'s Rich Results Test</a>.', [
      ':schema_href' => 'https://validator.schema.org/',
      ':google_href' => 'https://search.google.com/test/rich-results',
    ]);

    return [
      '#markup' => $description,
      '#prefix' => '<div class="description form-item__description">',
      '#suffix' => '</div>',
      '#weight' => 0,
    ];
  }

  /**
   * Builds the copy JSON-LD button.
   *
   * @param string $field_name
   *   The field machine name.
   */
  protected function buildCopyJsonLdButton(string $field_name): array {
    return [
      '#type' => 'button',
      '#value' => $this->t('Copy JSON-LD'),
      '#attributes' => [
        'class' => ['ai-schemadotorg-jsonld-copy-button', 'button--small'],
        'data-field-name' => $field_name,
      ],
      '#attached' => ['library' => ['ai_schemadotorg_jsonld/copy']],
      '#weight' => 99,
    ];
  }

}
