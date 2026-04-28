<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Form;

use Drupal\clinical_trials_gov\Traits\ClinicalTrialsGovMessageTrait;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\migrate_tools\MigrateBatchExecutable;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Step 4 of the import wizard.
 */
class ClinicalTrialsGovImportForm extends FormBase {

  use ClinicalTrialsGovMessageTrait;

  public function __construct(
    protected MigrationPluginManagerInterface $migrationPluginManager,
    protected KeyValueFactoryInterface $keyValue,
    protected TimeInterface $time,
    protected TranslationInterface $translation,
  ) {}

  /**
   * Creates the form from the service container.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('plugin.manager.migration'),
      $container->get('keyvalue'),
      $container->get('datetime.time'),
      $container->get('string_translation'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'clinical_trials_gov_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('clinical_trials_gov.settings');
    $query = (string) ($config->get('query') ?? '');
    $type = (string) ($config->get('type') ?? '');
    $fields = array_values(array_filter($config->get('fields') ?? [], 'is_string'));
    $ready = ($query !== '' && $type !== '' && $fields !== []);

    $form['summary'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Setting'),
        $this->t('Value'),
      ],
      '#rows' => [
        [$this->t('Query'), $query !== '' ? $query : $this->t('Not configured')],
        [$this->t('Content type'), $type !== '' ? $type : $this->t('Not configured')],
        [$this->t('Selected fields'), (string) count($fields)],
      ],
    ];

    $migration = NULL;
    if ($ready) {
      try {
        $migration = $this->migrationPluginManager->createInstance('clinical_trials_gov');
      }
      catch (\Throwable) {
        $migration = NULL;
      }
    }

    if ($migration !== NULL) {
      $id_map = $migration->getIdMap();
      $form['stats'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Metric'),
          $this->t('Count'),
        ],
        '#rows' => [
          [$this->t('Processed'), (string) $id_map->processedCount()],
          [$this->t('Imported'), (string) $id_map->importedCount()],
          [$this->t('Updated'), (string) $id_map->updateCount()],
          [$this->t('Errors'), (string) $id_map->errorCount()],
        ],
      ];
    }
    elseif (!$ready) {
      $form['message'] = $this->buildMessages('Complete the Find and Configure steps before running the import.', 'warning');
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Run Import'),
      '#disabled' => !$ready,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $migration = $this->migrationPluginManager->createInstance('clinical_trials_gov');
    $message = new MigrateMessage();
    $executable = new MigrateBatchExecutable(
      $migration,
      $message,
      $this->keyValue,
      $this->time,
      $this->translation,
      $this->migrationPluginManager,
      [
        'sync' => TRUE,
      ],
    );
    $executable->batchImport();
  }

}
