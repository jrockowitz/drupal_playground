<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
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

  public function __construct(
    protected MigrationPluginManagerInterface $migrationPluginManager,
    protected KeyValueFactoryInterface $keyValue,
    protected TimeInterface $time,
    protected TranslationInterface $translation,
    protected DateFormatterInterface $dateFormatter,
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
      $container->get('date.formatter'),
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
    $paths = array_values(array_filter($config->get('paths') ?? [], 'is_string'));
    $type = (string) ($config->get('type') ?? '');
    $field_mappings = array_filter($config->get('fields') ?? [], 'is_string');
    $fields = array_values($field_mappings);
    $ready = ($query !== '' && $paths !== [] && $type !== '' && $fields !== []);

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
      '#attributes' => [
        'class' => ['clinical-trials-gov-table'],
      ],
      '#header' => [],
      '#rows' => [
        [
          [
            'data' => ['#markup' => '<strong>' . $this->t('Content type') . '</strong>'],
            'style' => 'width: 50%',
          ],
          [
            'data' => ($type !== '' ? $type : $this->t('Not configured')),
            'style' => 'width: 50%',
          ],
        ],
        [
          [
            'data' => ['#markup' => '<strong>' . $this->t('Selected fields') . '</strong>'],
            'style' => 'width: 50%',
          ],
          [
            'data' => (string) count($fields),
            'style' => 'width: 50%',
          ],
        ],
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
      $source_rows = $this->t('N/A');
      $unprocessed = $this->t('N/A');
      try {
        $source_count = $migration->getSourcePlugin()->count();
        if ($source_count !== -1) {
          $source_rows = (string) $source_count;
          $unprocessed = (string) ($source_count - $id_map->processedCount());
        }
      }
      catch (\Throwable) {
      }

      $last_imported = '';
      $last_imported_timestamp = $this->keyValue->get('migrate_last_imported')->get($migration->id(), FALSE);
      if ($last_imported_timestamp) {
        $last_imported = $this->dateFormatter->format(
          (int) ($last_imported_timestamp / 1000),
          'custom',
          'Y-m-d H:i:s'
        );
      }

      $form['migration_status']['stats'] = [
        '#type' => 'table',
        '#attributes' => [
          'class' => ['clinical-trials-gov-table'],
        ],
        '#header' => [],
        '#rows' => [
          [
            [
              'data' => ['#markup' => '<strong>' . $this->t('Migration') . '</strong>'],
              'style' => 'width: 50%',
            ],
            [
              'data' => $migration->label(),
              'style' => 'width: 50%',
            ],
          ],
          [
            [
              'data' => ['#markup' => '<strong>' . $this->t('Machine Name') . '</strong>'],
              'style' => 'width: 50%',
            ],
            [
              'data' => $migration->id(),
              'style' => 'width: 50%',
            ],
          ],
          [
            [
              'data' => ['#markup' => '<strong>' . $this->t('Status') . '</strong>'],
              'style' => 'width: 50%',
            ],
            [
              'data' => $migration->getStatusLabel(),
              'style' => 'width: 50%',
            ],
          ],
          [
            [
              'data' => ['#markup' => '<strong>' . $this->t('Total') . '</strong>'],
              'style' => 'width: 50%',
            ],
            [
              'data' => $source_rows,
              'style' => 'width: 50%',
            ],
          ],
          [
            [
              'data' => ['#markup' => '<strong>' . $this->t('Imported') . '</strong>'],
              'style' => 'width: 50%',
            ],
            [
              'data' => (string) $id_map->importedCount(),
              'style' => 'width: 50%',
            ],
          ],
          [
            [
              'data' => ['#markup' => '<strong>' . $this->t('Unprocessed') . '</strong>'],
              'style' => 'width: 50%',
            ],
            [
              'data' => $unprocessed,
              'style' => 'width: 50%',
            ],
          ],
          [
            [
              'data' => ['#markup' => '<strong>' . $this->t('Messages') . '</strong>'],
              'style' => 'width: 50%',
            ],
            [
              'data' => (string) $id_map->messageCount(),
              'style' => 'width: 50%',
            ],
          ],
          [
            [
              'data' => ['#markup' => '<strong>' . $this->t('Last Imported') . '</strong>'],
              'style' => 'width: 50%',
            ],
            [
              'data' => $last_imported,
              'style' => 'width: 50%',
            ],
          ],
        ],
      ];
    }
    elseif (!$ready) {
      $this->messenger()->addWarning($this->t('Complete the <a href=":find_url">Find</a> and <a href=":configure_url">Configure</a> steps before running the import.', [
        ':find_url' => Url::fromRoute('clinical_trials_gov.find')->toString(),
        ':configure_url' => Url::fromRoute('clinical_trials_gov.configure')->toString(),
      ]));
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
