<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Hook;

use Drupal\clinical_trials_gov\ClinicalTrialsGovEntityManagerInterface;
use Drupal\clinical_trials_gov\Entity\ClinicalTrialsGovNodeAccessControlHandler;
use Drupal\clinical_trials_gov\Entity\ClinicalTrialsGovTrashNodeAccessControlHandler;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Attribute\ReorderHook;
use Drupal\Core\Hook\Order\OrderBefore;
use Drupal\Core\Session\AccountInterface;
use Drupal\trash\Hook\TrashEntityInfoHooks;

/**
 * Entity-related hook implementations for the ClinicalTrials.gov module.
 */
class ClinicalTrialsGovEntityHooks {

  /**
   * Constructs a new ClinicalTrialsGovEntityHooks instance.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected ModuleHandlerInterface $moduleHandler,
    protected ClinicalTrialsGovEntityManagerInterface $entityManager,
  ) {}

  /**
   * Implements hook_entity_type_alter().
   */
  #[Hook('entity_type_alter')]
  #[ReorderHook('entity_type_alter', class: TrashEntityInfoHooks::class, method: 'entityTypeAlter', order: new OrderBefore(['clinical_trials_gov']))]
  public function entityTypeAlter(array &$entity_types): void {
    if (!isset($entity_types['node']) || !$entity_types['node'] instanceof EntityTypeInterface) {
      return;
    }

    $handler_class = $this->moduleHandler->moduleExists('trash')
      ? ClinicalTrialsGovTrashNodeAccessControlHandler::class
      : ClinicalTrialsGovNodeAccessControlHandler::class;
    $entity_types['node']->setHandlerClass('access', $handler_class);
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for node_form.
   */
  #[Hook('form_node_form_alter')]
  public function formNodeFormAlter(array &$form, FormStateInterface $form_state): void {
    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof EntityFormInterface) {
      return;
    }

    $entity = $form_object->getEntity();
    if ($entity->getEntityTypeId() !== 'node') {
      return;
    }

    $settings = $this->configFactory->get('clinical_trials_gov.settings');
    if (
      ($settings->get('form_display_component') !== 'readonly')
      || !$this->moduleHandler->moduleExists('readonly_field_widget')
    ) {
      return;
    }

    if ($entity->bundle() !== $settings->get('type')) {
      return;
    }

    $mapped_paths = array_values($settings->get('fields'));
    if (!in_array('protocolSection.identificationModule.briefTitle', $mapped_paths, TRUE)) {
      return;
    }

    if (isset($form['title'])) {
      $form['title']['#access'] = FALSE;
    }
  }

  /**
   * Implements hook_entity_field_access().
   *
   * @param string $operation
   *   The field access operation being checked.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition being checked.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account requesting access.
   * @param \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface>|null $items
   *   The field items being checked, when available.
   */
  #[Hook('entity_field_access')]
  public function entityFieldAccess(string $operation, FieldDefinitionInterface $field_definition, AccountInterface $account, ?FieldItemListInterface $items = NULL): AccessResultInterface {
    if (($operation !== 'view') || ($items === NULL)) {
      return AccessResult::neutral();
    }

    $settings = $this->configFactory->get('clinical_trials_gov.settings');
    $entity = $items->getEntity();
    if (($entity->getEntityTypeId() !== 'node')
      || ($entity->bundle() !== $settings->get('type'))
      || ($settings->get('view_display_component') !== 'visible_update')) {
      return AccessResult::neutral()->addCacheableDependency($settings)->addCacheableDependency($entity);
    }

    $field_names = array_keys($settings->get('fields'));
    $field_names[] = $this->entityManager->getStudyUrlFieldName();
    $field_names[] = $this->entityManager->getStudyApiFieldName();
    if (!in_array($field_definition->getName(), array_unique($field_names))) {
      return AccessResult::neutral()->addCacheableDependency($settings)->addCacheableDependency($entity);
    }

    $update_access = $entity->access('update', $account, TRUE);
    if ($update_access->isAllowed()) {
      return AccessResult::allowed()->addCacheableDependency($settings)->addCacheableDependency($update_access);
    }

    return AccessResult::forbidden()->addCacheableDependency($settings)->addCacheableDependency($update_access);
  }

}
