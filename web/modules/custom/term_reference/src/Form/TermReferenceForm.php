<?php

namespace Drupal\term_reference\Form;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Element\EntityAutocomplete;
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
   * The current taxonomy term.
   */
  protected ?TermInterface $term = NULL;

  /**
   * The current reference field.
   */
  protected array $referenceField = [];

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
  public function buildForm(array $form, FormStateInterface $form_state, ?TermInterface $taxonomy_term = NULL, string $reference_field = ''): array {
    $this->term = $taxonomy_term;
    [$entity_type_id, $field_name] = $this->splitReferenceField($reference_field);
    $field = $this->termReferenceDiscovery->getReferenceField($taxonomy_term->bundle(), $entity_type_id, $field_name);
    $this->referenceField = $field;
    $bundle_labels = array_column($field['bundles'], 'label');

    $form['add'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Add @entity_type references to @term', [
        '@entity_type' => $field['entity_type_label_plural'],
        '@term' => $taxonomy_term->label(),
      ]),
    ];
    $form['add']['entity'] = [
      '#type' => 'textfield',
      '#title' => $this->t('@entity_type entity', ['@entity_type' => $field['entity_type_label_plural']]),
      '#description' => $this->t('Enter the label or ID of an existing @entity_type entity. Eligible bundles: @bundles.', [
        '@entity_type' => $field['entity_type_label_plural'],
        '@bundles' => implode(', ', $bundle_labels),
      ]),
      '#autocomplete_route_name' => 'term_reference.autocomplete',
      '#autocomplete_route_parameters' => [
        'taxonomy_term' => $taxonomy_term->id(),
        'reference_field' => $field['id'],
      ],
    ];
    $form['add']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add'),
      '#submit' => ['::addReferenceSubmit'],
    ];

    $form['existing'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Existing @entity_type references to @term', [
        '@entity_type' => $field['entity_type_label_plural'],
        '@term' => $taxonomy_term->label(),
      ]),
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
      ];
    }

    return $form;
  }

  /**
   * Builds a reference table row.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The referenced entity.
   * @param array $field
   *   The reference field.
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
    $operation = (string) $form_state->getValue('op');
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
   * Validates the entity selected for adding.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function validateAddReference(FormStateInterface $form_state): void {
    $input = trim((string) $form_state->getValue('entity'));
    if ($input === '') {
      $form_state->setErrorByName('entity', $this->t('Select an entity from the autocomplete suggestions.'));
      return;
    }
    $entity_id = EntityAutocomplete::extractEntityIdFromAutocompleteInput($input) ?: $input;
    $entity = $this->entityTypeManager->getStorage($this->referenceField['entity_type_id'])->load($entity_id);
    if (!$entity instanceof ContentEntityInterface) {
      $form_state->setErrorByName('entity', $this->t('Select an entity from the autocomplete suggestions.'));
      return;
    }
    if (!$this->entityCanBeManaged($entity, $this->referenceField['field_name'])) {
      $form_state->setErrorByName('entity', $this->t('The selected entity cannot be managed.'));
      return;
    }
    $form_state->set('term_reference_entity', $entity);
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
   * Adds the current term to the selected entity.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function addReferenceSubmit(array &$form, FormStateInterface $form_state): void {
    $entity = $form_state->get('term_reference_entity');
    $this->termReferenceManager->addReference($entity, $this->term, $this->referenceField['field_name']);
    $this->messenger()->addStatus($this->t('@label now references @term.', [
      '@label' => $entity->label(),
      '@term' => $this->term->label(),
    ]));
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
    $storage = $this->entityTypeManager->getStorage($this->referenceField['entity_type_id']);
    foreach ($storage->loadMultiple($this->getSelectedReferenceIds($form_state)) as $entity) {
      if ($entity instanceof ContentEntityInterface && $this->entityCanBeManaged($entity, $this->referenceField['field_name'])) {
        $this->termReferenceManager->removeReference($entity, $this->term, $this->referenceField['field_name']);
      }
    }
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
    $entity_type = $this->entityTypeManager->getDefinition($this->referenceField['entity_type_id']);
    $bundle_key = $entity_type->getKey('bundle');
    if ($bundle_key && !isset($this->referenceField['bundles'][$entity->bundle()])) {
      return FALSE;
    }
    $access_handler = $this->entityTypeManager->getAccessControlHandler($this->referenceField['entity_type_id']);
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
   * Splits a reference field ID into route parts.
   *
   * @param string $reference_field
   *   The reference field ID.
   *
   * @return array
   *   The entity type ID and field name.
   */
  protected function splitReferenceField(string $reference_field): array {
    return explode('.', $reference_field, 2) + ['', ''];
  }

}
