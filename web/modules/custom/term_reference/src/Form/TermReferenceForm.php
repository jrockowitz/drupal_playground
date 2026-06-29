<?php

namespace Drupal\term_reference\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AnnounceCommand;
use Drupal\Core\Ajax\FocusFirstCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\taxonomy\TermInterface;
use Drupal\term_reference\TermReferenceDiscoveryInterface;
use Drupal\term_reference\TermReferenceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for managing entities that reference a taxonomy term.
 */
class TermReferenceForm extends FormBase {

  /**
   * The AJAX wrapper ID.
   */
  protected const AJAX_WRAPPER_ID = 'term-reference-form-wrapper';

  /**
   * The current taxonomy term.
   */
  protected ?TermInterface $term = NULL;

  /**
   * The current field.
   */
  protected array $field = [];

  /**
   * Constructs a TermReferenceForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param \Drupal\term_reference\TermReferenceDiscoveryInterface $termReferenceDiscovery
   *   The term reference discovery service.
   * @param \Drupal\term_reference\TermReferenceManagerInterface $termReferenceManager
   *   The term reference manager.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountInterface $currentUser,
    protected TermReferenceDiscoveryInterface $termReferenceDiscovery,
    protected TermReferenceManagerInterface $termReferenceManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('term_reference.discovery'),
      $container->get('term_reference.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'term_reference_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?TermInterface $taxonomy_term = NULL, string $field = ''): array {
    $this->term = $taxonomy_term;
    [$entity_type_id, $field_name] = $this->splitField($field);
    $field = $this->termReferenceDiscovery->getField($taxonomy_term->bundle(), $entity_type_id, $field_name);
    $this->field = $field;
    $bundle_labels = array_column($field['bundles'], 'label');

    $form['#prefix'] = '<div id="' . static::AJAX_WRAPPER_ID . '">';
    $form['#suffix'] = '</div>';
    $form['messages'] = [
      '#type' => 'status_messages',
      '#weight' => -100,
    ];

    $form['add'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Add @entity_type references to @term', [
        '@entity_type' => $field['entity_type_label_plural'],
        '@term' => $taxonomy_term->label(),
      ]),
    ];
    $form['add']['entities'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('@entity_type entities', ['@entity_type' => $field['entity_type_label_plural']]),
      '#description' => $this->t('Enter one or more existing @entity_type entities. Eligible bundles: @bundles.', [
        '@entity_type' => $field['entity_type_label_plural'],
        '@bundles' => implode(', ', $bundle_labels),
      ]),
      '#target_type' => $field['entity_type_id'],
      '#tags' => TRUE,
      '#selection_handler' => 'default',
      '#selection_settings' => [
        'target_bundles' => array_keys($field['bundles']),
      ],
      '#validate_reference' => TRUE,
    ];
    $form['add']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add'),
      '#submit' => ['::addReferenceSubmit'],
      '#ajax' => [
        'callback' => '::ajaxRefresh',
        'wrapper' => static::AJAX_WRAPPER_ID,
      ],
    ];

    $form['existing'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Existing @entity_type references to @term', [
        '@entity_type' => $field['entity_type_label_plural'],
        '@term' => $taxonomy_term->label(),
      ]),
      '#attributes' => [
        'id' => 'term-reference-existing',
        'tabindex' => '-1',
      ],
    ];
    $form['existing']['references'] = [
      '#type' => 'table',
      '#parents' => ['references'],
      '#tree' => TRUE,
      '#header' => [
        '',
        $this->t('Label'),
        $this->t('ID'),
        $this->t('Bundle'),
        $this->t('Published'),
        $this->t('Operations'),
      ],
      '#empty' => $this->t('No references are available.'),
    ];

    $entities = $this->termReferenceManager->loadReferencingEntities($taxonomy_term, $field);
    foreach ($entities as $entity) {
      $form['existing']['references'][$entity->id()] = $this->buildReferenceRow($entity, $field);
    }
    if ($entities) {
      $form['existing']['remove'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove'),
        '#submit' => ['::removeReferenceSubmit'],
        '#ajax' => [
          'callback' => '::ajaxRefresh',
          'wrapper' => static::AJAX_WRAPPER_ID,
        ],
      ];
    }

    return $form;
  }

  /**
   * Refreshes the term reference form after an AJAX submission.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function ajaxRefresh(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#' . static::AJAX_WRAPPER_ID, $form));

    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new AnnounceCommand($this->t('The form contains errors. Review the highlighted fields.'), AnnounceCommand::PRIORITY_ASSERTIVE));
      $response->addCommand(new FocusFirstCommand('#' . static::AJAX_WRAPPER_ID . ' .form-item--error'));
      return $response;
    }

    $operation = $this->getTriggeredOperation($form_state);
    if ($operation === (string) $this->t('Remove')) {
      $response->addCommand(new AnnounceCommand($this->t('The selected references were removed.')));
      $response->addCommand(new InvokeCommand('#term-reference-existing', 'focus'));
      return $response;
    }

    $response->addCommand(new AnnounceCommand($this->t('The selected references were added.')));
    $response->addCommand(new InvokeCommand('[data-drupal-selector="edit-entities"]', 'focus'));
    return $response;
  }

  /**
   * Builds a reference table row.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The referenced entity.
   * @param array $field
   *   The field.
   *
   * @return array
   *   The table row render array.
   */
  protected function buildReferenceRow(ContentEntityInterface $entity, array $field): array {
    $operations = [];
    if ($entity->access('view', $this->currentUser)) {
      $operations['view'] = [
        'title' => $this->t('View'),
        'url' => $entity->toUrl(),
      ];
    }
    if ($entity->access('update', $this->currentUser) && $entity->hasLinkTemplate('edit-form')) {
      $operations['edit'] = [
        'title' => $this->t('Edit'),
        'url' => $entity->toUrl('edit-form'),
      ];
    }

    return [
      'remove' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Remove @label', ['@label' => $entity->label()]),
        '#title_display' => 'invisible',
        '#access' => $this->entityCanBeManaged($entity, $field['field_name']),
      ],
      'label' => [
        '#plain_text' => $entity->label(),
      ],
      'id' => [
        '#plain_text' => $entity->id(),
      ],
      'bundle' => [
        '#plain_text' => $field['bundles'][$entity->bundle()]['label'] ?? $entity->bundle(),
      ],
      'published' => [
        '#plain_text' => $this->getPublishedLabel($entity),
      ],
      'operations' => [
        'data' => [
          '#type' => 'operations',
          '#links' => $operations,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $operation = $this->getTriggeredOperation($form_state);
    if ($operation === (string) $this->t('Add')) {
      $this->validateAddReference($form_state);
    }
    if ($operation === (string) $this->t('Remove')) {
      $this->validateRemoveReference($form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * Validates the entities selected for adding.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function validateAddReference(FormStateInterface $form_state): void {
    $values = $form_state->getValue('entities') ?? [];
    if (!$values) {
      $form_state->setErrorByName('entities', $this->t('Select at least one entity from the autocomplete suggestions.'));
      return;
    }

    $entity_ids = array_column($values, 'target_id');
    $entities = $this->entityTypeManager->getStorage($this->field['entity_type_id'])->loadMultiple($entity_ids);
    foreach ($entity_ids as $entity_id) {
      $entity = $entities[$entity_id] ?? NULL;
      if (!$entity instanceof ContentEntityInterface) {
        $form_state->setErrorByName('entities', $this->t('Select entities from the autocomplete suggestions.'));
        return;
      }
      if (!$this->entityCanBeManaged($entity, $this->field['field_name'])) {
        $form_state->setErrorByName('entities', $this->t('The selected entity cannot be managed.'));
        return;
      }
    }
    $form_state->set('term_reference_entities', $entities);
  }

  /**
   * Validates entities selected for removal.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function validateRemoveReference(FormStateInterface $form_state): void {
    $selected_ids = $this->getSelectedReferenceIds($form_state);
    if (!$selected_ids) {
      $form_state->setErrorByName('references', $this->t('Select at least one reference to remove.'));
    }
  }

  /**
   * Adds the current term to the selected entities.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function addReferenceSubmit(array &$form, FormStateInterface $form_state): void {
    $entities = $form_state->get('term_reference_entities') ?? [];
    foreach ($entities as $entity) {
      $this->termReferenceManager->addReference($entity, $this->term, $this->field['field_name']);
    }
    $first_entity = reset($entities);
    $this->messenger()->addStatus($this->formatPlural(count($entities), '@label now references @term.', '@count entities now reference @term.', [
      '@label' => $first_entity->label(),
      '@term' => $this->term->label(),
    ]));
    $form_state->setValue('entities', []);
    $user_input = $form_state->getUserInput();
    unset($user_input['entities']);
    $form_state->setUserInput($user_input);
    $form_state->setRebuild();
  }

  /**
   * Removes the current term from selected entities.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function removeReferenceSubmit(array &$form, FormStateInterface $form_state): void {
    $storage = $this->entityTypeManager->getStorage($this->field['entity_type_id']);
    $removed_count = 0;
    foreach ($storage->loadMultiple($this->getSelectedReferenceIds($form_state)) as $entity) {
      if ($entity instanceof ContentEntityInterface && $this->entityCanBeManaged($entity, $this->field['field_name'])) {
        $this->termReferenceManager->removeReference($entity, $this->term, $this->field['field_name']);
        $removed_count++;
      }
    }
    if ($removed_count) {
      $this->messenger()->addStatus($this->formatPlural($removed_count, 'Removed 1 reference from @term.', 'Removed @count references from @term.', [
        '@term' => $this->term->label(),
      ]));
    }
    $form_state->setRebuild();
  }

  /**
   * Gets selected reference IDs.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The selected entity IDs.
   */
  protected function getSelectedReferenceIds(FormStateInterface $form_state): array {
    $selected_ids = [];
    foreach ($form_state->getValue('references', []) as $entity_id => $row) {
      if (!empty($row['remove'])) {
        $selected_ids[] = $entity_id;
      }
    }
    return $selected_ids;
  }

  /**
   * Gets the triggered operation label.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return string
   *   The triggered operation label.
   */
  protected function getTriggeredOperation(FormStateInterface $form_state): string {
    $triggering_element = $form_state->getTriggeringElement();
    if (isset($triggering_element['#value'])) {
      return (string) $triggering_element['#value'];
    }
    return (string) $form_state->getValue('op');
  }

  /**
   * Checks whether an entity can be managed.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param string $field_name
   *   The field name.
   *
   * @return bool
   *   TRUE when the entity can be managed.
   */
  protected function entityCanBeManaged(ContentEntityInterface $entity, string $field_name): bool {
    if (!$entity->access('update', $this->currentUser) || !$entity->hasField($field_name)) {
      return FALSE;
    }
    $entity_type = $this->entityTypeManager->getDefinition($this->field['entity_type_id']);
    $bundle_key = $entity_type->getKey('bundle');
    if ($bundle_key && !isset($this->field['bundles'][$entity->bundle()])) {
      return FALSE;
    }
    $access_handler = $this->entityTypeManager->getAccessControlHandler($this->field['entity_type_id']);
    return $access_handler->fieldAccess('edit', $entity->get($field_name)->getFieldDefinition(), $this->currentUser, $entity->get($field_name));
  }

  /**
   * Gets the published label for an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The published state label.
   */
  protected function getPublishedLabel(ContentEntityInterface $entity): TranslatableMarkup {
    if (!$entity instanceof EntityPublishedInterface) {
      return $this->t('N/A');
    }
    return $entity->isPublished() ? $this->t('Published') : $this->t('Unpublished');
  }

  /**
   * Splits a field ID into route parts.
   *
   * @param string $field
   *   The field ID.
   *
   * @return array
   *   The entity type ID and field name.
   */
  protected function splitField(string $field): array {
    return explode('.', $field, 2) + ['', ''];
  }

}
