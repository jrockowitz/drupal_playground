<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\ConfigTarget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\RedundantEditableConfigNamesTrait;
use Drupal\Core\Render\Markup;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Advanced settings for the ClinicalTrials.gov workflow.
 *
 * @phpstan-consistent-constructor
 */
class ClinicalTrialsGovSettingsForm extends ConfigFormBase {
  use RedundantEditableConfigNamesTrait;

  /**
   * The editable config names.
   */
  protected function editableConfigNames(): array {
    return ['clinical_trials_gov.settings'];
  }

  public function __construct(
    ConfigFactoryInterface $configFactory,
    TypedConfigManagerInterface $typedConfigManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct($configFactory, $typedConfigManager);
  }

  /**
   * Creates the form from the service container.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'clinical_trials_gov_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('clinical_trials_gov.settings');
    $structure_locked = $this->isStructureLocked();
    $query = (string) $config->get('query');
    $query_paths = $config->get('query_paths');
    $find_url = Url::fromRoute('clinical_trials_gov.find')->toString();

    $form['studies_query'] = [
      '#type' => 'details',
      '#title' => $this->t('Studies query'),
      '#description' => $this->t('Review the saved studies query and the discovered query paths from the <a href=":find_url">Find</a> page.', [
        ':find_url' => $find_url,
      ]),
      '#open' => FALSE,
    ];
    $form['studies_query']['query'] = [
      '#type' => 'item',
      '#title' => $this->t('Query'),
      '#markup' => '<small>' . htmlspecialchars($query ?: (string) $this->t('No query saved.')) . '</small>',
      '#description' => $this->t('The above query is defined when you update the Studies query on the <a href=":find_url">Find</a> page.', [
        ':find_url' => $find_url,
      ]),
    ];
    $form['studies_query']['query_paths'] = [
      '#type' => 'item',
      '#title' => $this->t('Query paths'),
      '#markup' => $query_paths
        ? Markup::create('<small><pre>' . implode("\n", array_map('htmlspecialchars', $query_paths)) . '</pre></small>')
        : '<small>' . htmlspecialchars((string) $this->t('No query paths saved.')) . '</small>',
      '#description' => $this->t('The above query paths are discovered when you update the Studies query on the <a href=":find_url">Find</a> page.', [
        ':find_url' => $find_url,
      ]),
    ];

    $form['metadata'] = [
      '#type' => 'details',
      '#title' => $this->t('Metadata'),
      '#description' => $this->t('Configure how ClinicalTrials.gov metadata maps into the Drupal title and required field list.'),
      '#open' => TRUE,
    ];
    $form['metadata']['title_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title metadata path'),
      '#description' => $this->t('Metadata path that will populate the Drupal node title.'),
      '#config_target' => 'clinical_trials_gov.settings:title_path',
      '#required' => TRUE,
    ];
    $form['metadata']['required_paths'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Required metadata paths'),
      '#description' => $this->t('Enter one metadata path per line. These paths are always available in Configure.'),
      '#rows' => 6,
      '#config_target' => new ConfigTarget(
        'clinical_trials_gov.settings',
        'required_paths',
        [static::class, 'formatRequiredPaths'],
        [static::class, 'parseRequiredPaths'],
      ),
      '#required' => TRUE,
    ];

    $form['content_type'] = [
      '#type' => 'details',
      '#title' => $this->t('Content type'),
      '#description' => $this->t('Configure the destination content type and generated field behavior for imported studies.'),
      '#open' => TRUE,
    ];
    $form['content_type']['type'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Content type machine name'),
      '#maxlength' => 32,
      '#description' => $this->t('Machine name for the destination content type. Limited to 32 characters. Common values include trial, study, or nct. This setting is locked after the destination content type and fields are created.'),
      '#config_target' => 'clinical_trials_gov.settings:type',
      '#required' => !$structure_locked,
      '#disabled' => $structure_locked,
    ];
    $form['content_type']['field_prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field prefix'),
      '#field_suffix' => '_{metadata_field_name}',
      '#description' => $this->t('Prefix used when generating Drupal field machine names. Common values include trial, study, nct, or trial_version_holder. For example, trial produces trial_brief_title. This setting is locked after the destination content type and fields are created.'),
      '#config_target' => 'clinical_trials_gov.settings:field_prefix',
      '#required' => !$structure_locked,
      '#disabled' => $structure_locked,
    ];
    $form['content_type']['readonly'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Read-only imported fields'),
      '#description' => $this->t('Display imported ClinicalTrials.gov fields as readonly on edit forms and hide the editable node title when the generated title mapping is present.'),
      '#config_target' => 'clinical_trials_gov.settings:readonly',
      '#access' => $this->moduleHandler->moduleExists('readonly_field_widget'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Formats required paths for textarea display.
   */
  public static function formatRequiredPaths(?array $paths): string {
    return implode("\n", $paths ?? []);
  }

  /**
   * Parses newline-delimited required paths for config storage.
   */
  public static function parseRequiredPaths(string $paths): array {
    $required_paths = preg_split('/\r\n|\r|\n/', trim($paths)) ?: [];
    $required_paths = array_map('trim', $required_paths);
    $required_paths = array_filter($required_paths, static fn (string $path): bool => $path !== '');
    return array_values(array_unique($required_paths));
  }

  /**
   * {@inheritdoc}
   */
  protected function formatMultipleViolationsMessage(string $form_element_name, array $violations): MarkupInterface|\Stringable {
    if ($form_element_name !== 'required_paths') {
      return parent::formatMultipleViolationsMessage($form_element_name, $violations);
    }

    $messages = [];
    foreach ($violations as $index => $violation) {
      assert($violation instanceof ConstraintViolationInterface);
      $messages[] = (string) $this->t('Line @line: @message', [
        '@line' => $index + 1,
        '@message' => $violation->getMessage(),
      ]);
    }

    return Markup::create(implode("\n", $messages));
  }

  /**
   * Determines whether the destination structure already exists.
   */
  protected function isStructureLocked(): bool {
    $config = $this->config('clinical_trials_gov.settings');
    $type = trim((string) $config->get('type'));

    if (!$type) {
      return FALSE;
    }

    return (bool) $this->entityTypeManager->getStorage('node_type')->load($type);
  }

}
