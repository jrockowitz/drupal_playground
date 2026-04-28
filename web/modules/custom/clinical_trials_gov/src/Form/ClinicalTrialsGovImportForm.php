<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Form;

use Drupal\clinical_trials_gov\Traits\ClinicalTrialsGovMessageTrait;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
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

    $form['studies_query'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Studies query'),
    ];
    $form['studies_query']['summary'] = [
      '#type' => 'clinical_trials_gov_studies_query_summary',
      '#query' => $query,
    ];
    $form['studies_query']['links'] = $this->buildActionLinks([
      'find' => [
        'title' => $this->t('Edit studies query'),
        'url' => Url::fromRoute('clinical_trials_gov.find'),
      ],
      'review' => [
        'title' => $this->t('Review trials'),
        'url' => Url::fromRoute('clinical_trials_gov.review'),
      ],
    ]);

    $form['content_type'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Content type'),
    ];
    $form['content_type']['summary'] = [
      '#type' => 'table',
      '#header' => [
        [
          'data' => $this->t('Setting'),
          'style' => 'width: 50%',
        ],
        [
          'data' => $this->t('Value'),
          'style' => 'width: 50%',
        ],
      ],
      '#rows' => [
        [$this->t('Content type'), $type !== '' ? $type : $this->t('Not configured')],
        [$this->t('Selected fields'), (string) count($fields)],
      ],
    ];
    $form['content_type']['links'] = $this->buildActionLinks([
      'configure' => [
        'title' => $this->t('Configure content type and fields'),
        'url' => Url::fromRoute('clinical_trials_gov.configure'),
      ],
    ]);

    $form['migration_status'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Migration status'),
    ];

    $migration = NULL;
    if ($ready) {
      try {
        $migration = $this->migrationPluginManager->createInstance('clinical_trials_gov');
        if (!is_object($migration)) {
          $migration = NULL;
        }
      }
      catch (\Throwable) {
        $migration = NULL;
      }
    }

    if ($migration !== NULL) {
      $id_map = $migration->getIdMap();
      $form['migration_status']['stats'] = [
        '#type' => 'table',
        '#header' => [
          [
            'data' => $this->t('Metric'),
            'style' => 'width: 50%',
          ],
          [
            'data' => $this->t('Count'),
            'style' => 'width: 50%',
          ],
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
      $form['migration_status']['message'] = $this->buildMessages('Complete the Find and Configure steps before running the import.', 'warning');
    }
    $form['migration_status']['links'] = $this->buildActionLinks([
      'overview' => [
        'title' => $this->t('View migration overview'),
        'url' => Url::fromUri('https://drupal-playground.ddev.site/admin/structure/migrate/manage/default/migrations/clinical_trials_gov'),
      ],
    ]);

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

  /**
   * Builds a row of button-style action links.
   */
  protected function buildActionLinks(array $links): array {
    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['clinical-trials-gov__section-links'],
      ],
    ];

    foreach ($links as $key => $link) {
      $build[$key] = [
        '#type' => 'link',
        '#title' => $link['title'],
        '#url' => $link['url'],
        '#attributes' => [
          'class' => ['button'],
        ],
      ];
    }

    return $build;
  }

}
