<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Form;

use Drupal\clinical_trials_gov\ClinicalTrialsGovEntityManagerInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovFieldManagerInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovMigrationManagerInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Step 3 of the import wizard.
 *
 * @phpstan-consistent-constructor
 */
class ClinicalTrialsGovConfigForm extends ConfigFormBase {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ClinicalTrialsGovFieldManagerInterface $fieldManager,
    protected ClinicalTrialsGovEntityManagerInterface $entityManager,
    protected ClinicalTrialsGovMigrationManagerInterface $migrationManager,
  ) {}

  /**
   * Creates the form from the service container.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('clinical_trials_gov.field_manager'),
      $container->get('clinical_trials_gov.entity_manager'),
      $container->get('clinical_trials_gov.migration_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['clinical_trials_gov.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'clinical_trials_gov_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#attributes']['class'][] = 'clinical-trials-gov';
    $form['#attached']['library'][] = 'clinical_trials_gov/clinical_trials_gov';

    $config = $this->config('clinical_trials_gov.settings');
    $paths = array_values(array_filter($config->get('paths') ?? [], 'is_string'));
    $saved_type = (string) ($config->get('type') ?? '');
    $saved_field_mappings = array_filter($config->get('fields') ?? [], 'is_string');
    $saved_fields = array_values($saved_field_mappings);
    $node_type = $this->entityTypeManager->getStorage('node_type')->load($saved_type);

    if ($paths === []) {
      $this->messenger()->addWarning($this->t('Save a studies query from the <a href=":find_url">Find</a> step before configuring the destination content type and fields.', [
        ':find_url' => Url::fromRoute('clinical_trials_gov.find')->toString(),
      ]));
      $form = parent::buildForm($form, $form_state);
      unset($form['actions']);
      return $form;
    }

    $form['content_type'] = [
      '#type' => 'details',
      '#title' => $this->t('Content type'),
      '#open' => TRUE,
    ];

    if ($node_type === NULL) {
      $form['settings_message'] = [
        '#type' => 'container',
        '#weight' => -10,
        'message' => [
          '#markup' => '<p>' . $this->t('Review the content type and fields that will be created below. Go to <a href=":url">Settings</a> to change the machine names and field prefix.', [
            ':url' => Url::fromRoute('clinical_trials_gov.settings')->toString(),
          ]) . '</p>',
        ],
      ];
    }

    if ($node_type === NULL) {
      $form['content_type']['type'] = [
        '#type' => 'item',
        '#title' => $this->t('Machine name'),
        '#markup' => $saved_type,
      ];
      $form['content_type']['label'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Label'),
        '#default_value' => $this->t('Trial'),
        '#required' => TRUE,
      ];
      $form['content_type']['description'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Description'),
        '#default_value' => $this->t('Imported ClinicalTrials.gov studies.'),
        '#rows' => 3,
      ];
    }
    else {
      $form['content_type']['type'] = [
        '#type' => 'item',
        '#title' => $this->t('Machine name'),
        '#markup' => $node_type->id(),
      ];
      $form['content_type']['label'] = [
        '#type' => 'item',
        '#title' => $this->t('Label'),
        '#markup' => $node_type->label(),
      ];
      $form['content_type']['description'] = [
        '#type' => 'item',
        '#title' => $this->t('Description'),
        '#markup' => $node_type->getDescription() ?: $this->t('No description.'),
      ];
      $form['content_type']['existing_type'] = [
        '#type' => 'value',
        '#value' => $node_type->id(),
      ];
    }

    $form['field_mapping'] = [
      '#type' => 'details',
      '#title' => $this->t('Field mapping'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];
    $form['field_mapping']['rows'] = [
      '#type' => 'table',
      '#attributes' => [
        'class' => ['clinical-trials-gov-table'],
      ],
      '#header' => [
        [
          'data' => '',
          'class' => ['select-all'],
        ],
        $this->t('Field'),
        $this->t('Piece / Path'),
        $this->t('Field name'),
        $this->t('Field type'),
      ],
      '#empty' => $this->t('No study metadata is available.'),
      '#attached' => [
        'library' => ['core/drupal.tableselect'],
      ],
    ];

    $definitions = $this->fieldManager->getAvailableFieldDefinitions();

    foreach ($definitions as $path => $definition) {
      if ($this->shouldHideFieldRow($path, $definitions) || $this->shouldHideEmptyGroupRow($path, $definitions)) {
        continue;
      }

      $row_key = md5($path);
      $existing = FALSE;
      if (($definition['destination_property'] ?? NULL) === 'title') {
        $existing = TRUE;
      }
      elseif (!empty($definition['field_name'])) {
        $existing = FieldConfig::loadByName('node', $saved_type, $definition['field_name']) !== NULL;
      }

      $selected = ($node_type === NULL)
        || in_array($path, $saved_fields, TRUE)
        || !empty($definition['required'])
        || $existing;
      $disabled = !empty($definition['required']) || $existing || empty($definition['selectable']);
      $depth = $this->calculateHierarchyDepth($path);

      $row_attributes = ['class' => []];
      if (!empty($definition['group_only'])) {
        $row_attributes['class'][] = 'clinical-trials-gov-field-group';
      }

      $label = (string) ($definition['label'] ?? '');
      if ($label === '') {
        $label = (string) ($definition['piece'] ?? $path);
      }

      if (!empty($definition['group_only'])) {
        $form['field_mapping']['rows'][$row_key]['selected'] = [
          '#markup' => '',
          '#wrapper_attributes' => $row_attributes,
        ];
      }
      else {
        $form['field_mapping']['rows'][$row_key]['selected'] = [
          '#type' => 'checkbox',
          '#default_value' => $selected,
          '#disabled' => $disabled,
          '#wrapper_attributes' => $row_attributes,
        ];
      }
      $description = (string) ($definition['description'] ?? '');
      $form['field_mapping']['rows'][$row_key]['label'] = [
        'data' => $this->buildLabelCell($label, $description, $depth),
        '#wrapper_attributes' => $row_attributes,
      ];
      $form['field_mapping']['rows'][$row_key]['identifier'] = [
        '#markup' => $this->buildPieceMarkup($definition, $path),
        '#wrapper_attributes' => $row_attributes,
      ];
      $form['field_mapping']['rows'][$row_key]['field_name'] = [
        'data' => $this->buildFieldNameCell((string) ($definition['field_name'] ?? ''), $definition['details'] ?? [], $depth),
        '#wrapper_attributes' => $row_attributes,
      ];
      $form['field_mapping']['rows'][$row_key]['type'] = [
        '#plain_text' => (string) ($definition['display_type_label'] ?? ''),
        '#wrapper_attributes' => $row_attributes,
      ];
      $form['field_mapping']['rows'][$row_key]['path'] = [
        '#type' => 'value',
        '#value' => $path,
      ];
      $form['field_mapping']['rows'][$row_key]['#attributes'] = $row_attributes;
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $paths = array_values(array_filter($this->config('clinical_trials_gov.settings')->get('paths') ?? [], 'is_string'));
    if ($paths === []) {
      $this->messenger()->addWarning($this->t('Save a studies query from the <a href=":find_url">Find</a> step before configuring the destination content type and fields.', [
        ':find_url' => Url::fromRoute('clinical_trials_gov.find')->toString(),
      ]));
      $form_state->setRedirect('clinical_trials_gov.find');
      return;
    }

    $config = $this->configFactory()->getEditable('clinical_trials_gov.settings');
    $type = (string) ($form_state->getValue('existing_type') ?: $this->config('clinical_trials_gov.settings')->get('type') ?: '');
    $label = (string) ($form_state->getValue('label') ?: 'Trial');
    $description = (string) ($form_state->getValue('description') ?: '');

    $selected_rows = [];
    foreach (($form_state->getValue(['field_mapping', 'rows']) ?? []) as $row) {
      if (!is_array($row) || empty($row['path'])) {
        continue;
      }
      $path = (string) $row['path'];
      $definition = $this->fieldManager->getFieldDefinition($path);
      $existing = (($definition['destination_property'] ?? NULL) === 'title')
        || (!empty($definition['field_name']) && FieldConfig::loadByName('node', $type, $definition['field_name']) !== NULL);
      $selected_rows[$path] = !empty($definition['required']) || $existing || !empty($row['selected']);
    }

    $selected_fields = [];
    foreach (array_keys($selected_rows) as $path) {
      $definition = $this->fieldManager->getFieldDefinition($path);
      $field_name = (string) ($definition['field_name'] ?? '');
      if ($field_name === '') {
        continue;
      }
      if (!empty($definition['group_only'])) {
        if ($this->hasSelectedDescendant($path, $selected_rows)) {
          $selected_fields[$field_name] = $path;
        }
        continue;
      }
      if (!empty($selected_rows[$path])) {
        $selected_fields[$field_name] = $path;
      }
    }

    $config
      ->set('type', $type)
      ->set('fields', $selected_fields)
      ->save();

    $this->entityManager->createContentType($type, $label, $description);
    $this->entityManager->createFields($type, array_values($selected_fields));
    $this->migrationManager->updateMigration();
    parent::submitForm($form, $form_state);
    $this->messenger()->deleteByType('status');
    $this->messenger()->addStatus($this->formatPlural(
      count($selected_fields),
      '1 field has been added to the @label content type.',
      '@count fields have been added to the @label content type.',
      ['@label' => $label]
    ));
    $form_state->setRedirect('clinical_trials_gov.import');
  }

  /**
   * Calculates the display depth for a metadata key.
   */
  protected function calculateHierarchyDepth(string $path): int {
    return max(0, substr_count($path, '.'));
  }

  /**
   * Builds a padding style for hierarchical table cells.
   */
  protected function buildIndentStyle(int $depth): string {
    if ($depth <= 0) {
      return '';
    }

    return ' style="padding-left: ' . (string) ($depth * 1.5) . 'rem;"';
  }

  /**
   * Builds shared wrapper attributes for indented cell content.
   */
  protected function buildIndentAttributes(int $depth): array {
    if ($depth <= 0) {
      return [];
    }

    return [
      'style' => 'padding-left: ' . (string) ($depth * 1.5) . 'rem;',
    ];
  }

  /**
   * Builds the label cell render array.
   */
  protected function buildLabelCell(string $label, string $description, int $depth): array {
    $cell = [
      '#type' => 'container',
      '#attributes' => $this->buildIndentAttributes($depth),
      'label' => [
        '#type' => 'html_tag',
        '#tag' => 'strong',
        '#value' => $label,
      ],
    ];

    if ($description !== '') {
      $cell['break'] = [
        '#markup' => '<br/>',
      ];
      $cell['description'] = [
        '#plain_text' => $description,
      ];
    }

    return $cell;
  }

  /**
   * Builds the field name cell render array.
   */
  protected function buildFieldNameCell(string $field_name, mixed $details, int $depth): array {
    $cell = [
      '#type' => 'container',
      '#attributes' => $this->buildIndentAttributes($depth),
      'field_name' => [
        '#type' => 'html_tag',
        '#tag' => 'small',
        '#value' => $field_name,
      ],
    ];

    if (!is_array($details) || $details === []) {
      return $cell;
    }

    $items = array_values(array_filter(array_map(
      fn(mixed $detail): string => is_scalar($detail) ? (string) $detail : '',
      $details
    )));

    if ($items === []) {
      return $cell;
    }

    $cell['details'] = [
      '#theme' => 'item_list',
      '#items' => array_map(
        fn(string $detail): array => [
          '#type' => 'html_tag',
          '#tag' => 'small',
          '#value' => $detail,
        ],
        $items
      ),
    ];

    return $cell;
  }

  /**
   * Builds the markup for the piece column.
   */
  protected function buildPieceMarkup(array $definition, string $path): string {
    $markup = Html::escape((string) ($definition['piece'] ?? ''));
    $markup .= '<br/><small>' . Html::escape($path) . '</small>';

    if (!empty($definition['details'])) {
      $properties = array_map(function (string $detail): string {
        $last_dot = strrpos($detail, '.');
        return ($last_dot === FALSE) ? $detail : substr($detail, $last_dot + 1);
      }, array_filter($definition['details'], 'is_string'));

      if ($properties !== []) {
        $markup .= '<ul><li><small>' . implode('</small></li><li><small>', array_map([Html::class, 'escape'], $properties)) . '</small></li></ul>';
      }
    }

    return $markup;
  }

  /**
   * Determines whether a metadata row has any selected descendants.
   */
  protected function hasSelectedDescendant(string $path, array $selected_rows): bool {
    $prefix = $path . '.';
    foreach ($selected_rows as $candidate_key => $selected) {
      if ($selected && str_starts_with($candidate_key, $prefix)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Determines whether a row should be hidden beneath a promoted custom field.
   */
  protected function shouldHideFieldRow(string $path, array $definitions): bool {
    $last_dot = strrpos($path, '.');
    while ($last_dot !== FALSE) {
      $parent_path = substr($path, 0, $last_dot);
      $parent_definition = $definitions[$parent_path] ?? $this->fieldManager->getFieldDefinition($parent_path);
      if (!empty($parent_definition['available']) && ($parent_definition['field_type'] ?? '') === 'custom' && empty($parent_definition['group_only'])) {
        return TRUE;
      }
      $last_dot = strrpos($parent_path, '.');
    }

    return FALSE;
  }

  /**
   * Determines whether a group-only row has any visible children.
   */
  protected function shouldHideEmptyGroupRow(string $path, array $definitions): bool {
    $definition = $definitions[$path] ?? NULL;
    if (empty($definition['group_only'])) {
      return FALSE;
    }

    $prefix = $path . '.';
    foreach (array_keys($definitions) as $candidate_path) {
      if (!str_starts_with($candidate_path, $prefix)) {
        continue;
      }
      if ($this->shouldHideFieldRow($candidate_path, $definitions)) {
        continue;
      }
      if ($this->shouldHideEmptyGroupRow($candidate_path, $definitions)) {
        continue;
      }
      return FALSE;
    }

    return TRUE;
  }

}
