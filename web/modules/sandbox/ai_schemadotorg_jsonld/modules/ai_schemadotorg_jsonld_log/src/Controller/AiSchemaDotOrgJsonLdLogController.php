<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld_log\Controller;

use Drupal\ai_schemadotorg_jsonld_log\AiSchemaDotOrgJsonLdLogStorageInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Builds the AI Schema.org JSON-LD log admin UI.
 *
 * @phpstan-consistent-constructor
 */
class AiSchemaDotOrgJsonLdLogController extends ControllerBase {

  /**
   * Constructs an AiSchemaDotOrgJsonLdLogController object.
   *
   * @param \Drupal\ai_schemadotorg_jsonld_log\AiSchemaDotOrgJsonLdLogStorageInterface $logStorage
   *   The log storage.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   */
  public function __construct(
    protected readonly AiSchemaDotOrgJsonLdLogStorageInterface $logStorage,
    protected readonly DateFormatterInterface $dateFormatter,
    protected readonly RequestStack $requestStack,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(AiSchemaDotOrgJsonLdLogStorageInterface::class),
      $container->get('date.formatter'),
      $container->get('request_stack'),
    );
  }

  /**
   * Gets the page title.
   */
  public function title(): string {
    $entity = $this->getFilteredEntity();
    if ($entity) {
      return 'AI Schema.org JSON-LD: ' . $entity->label();
    }

    return 'AI Schema.org JSON-LD';
  }

  /**
   * Checks access for the log page and CSV download.
   */
  public function access(): AccessResultInterface {
    $entity = $this->getFilteredEntity();
    if ($entity) {
      return AccessResult::allowedIf($entity->access('update'))
        ->addCacheableDependency($entity)
        ->cachePerPermissions()
        ->cachePerUser();
    }

    $query = $this->getFilterQuery();
    if ($query !== []) {
      return AccessResult::forbidden()->cachePerUser();
    }

    return AccessResult::allowedIfHasPermission($this->currentUser(), 'administer site configuration');
  }

  /**
   * Displays the log table and operations.
   */
  public function index(): array {
    if (!$this->isLoggingEnabled()) {
      return [
        '#theme' => 'status_messages',
        '#message_list' => [
          'warning' => [
            $this->t('Prompt and response logging is disabled.'),
            $this->t('Enable prompt and response logging in the Schema.org JSON-LD settings to view logs.'),
          ],
        ],
        '#status_headings' => [
          'status' => $this->t('Status message'),
          'warning' => $this->t('Warning message'),
          'error' => $this->t('Error message'),
        ],
      ];
    }

    $query = $this->getFilterQuery();
    $entity_type = $query['entity_type'] ?? '';
    $entity_id = $query['entity_id'] ?? '';
    $is_filtered = ($query !== []);
    $rows = [];
    foreach ($this->logStorage->loadMultiple($entity_type, $entity_id) as $row) {
      $data = [
        'created' => $this->formatCreated((int) $row['created']),
        'prompt' => $this->buildPreformattedCell($row['prompt']),
        'response' => $this->buildPreformattedCell($this->formatResponse($row['response'])),
        'valid' => $this->formatValid((int) $row['valid']),
      ];
      if (!$is_filtered) {
        $data = [
          'created' => $data['created'],
          'entity' => [
            'data' => $this->buildEntityCell($row),
          ],
          'prompt' => $data['prompt'],
          'response' => $data['response'],
          'valid' => $data['valid'],
        ];
      }

      $rows[] = [
        'class' => ((int) $row['valid'] === 0) ? ['ai-schemadotorg-jsonld-log-page__row--warning'] : [],
        'data' => $data,
      ];
    }

    $download_url = Url::fromRoute('ai_schemadotorg_jsonld_log.download');
    if ($query !== []) {
      $download_url->setOption('query', $query);
    }
    $download_link = Link::fromTextAndUrl($this->t('Download CSV'), $download_url)
      ->toRenderable();
    $download_link['#attributes']['class'] = ['button', 'button--small'];

    $operations = [
      '#type' => 'container',
      '#attributes' => ['class' => ['ai-schemadotorg-jsonld-log-operations']],
      'download' => $download_link,
    ];

    if ($query === []) {
      $clear_link = Link::fromTextAndUrl($this->t('Clear log'), Url::fromRoute('ai_schemadotorg_jsonld_log.clear'))
        ->toRenderable();
      $clear_link['#attributes']['class'] = ['use-ajax', 'button', 'button--small'];
      $clear_link['#attributes']['data-dialog-type'] = 'modal';
      $clear_link['#attributes']['data-dialog-options'] = Json::encode(['width' => 700]);
      $clear_link['#attached']['library'][] = 'core/drupal.dialog.ajax';
      $operations['clear'] = $clear_link;
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['ai-schemadotorg-jsonld-log-page']],
      '#attached' => [
        'library' => ['ai_schemadotorg_jsonld_log/ai_schemadotorg_jsonld_log'],
      ],
      'table' => [
        '#type' => 'table',
        '#header' => $this->buildTableHeader($is_filtered),
        '#rows' => $rows,
        '#empty' => $this->t('No log entries available.'),
        '#attributes' => ['class' => ['ai-schemadotorg-jsonld-log-page__table']],
      ],
      'pager' => [
        '#type' => 'pager',
      ],
      'operations' => $operations,
    ];
  }

  /**
   * Builds the display table header.
   *
   * @param bool $is_filtered
   *   TRUE when the log is filtered to a specific entity.
   *
   * @return array
   *   The table header definition.
   */
  protected function buildTableHeader(bool $is_filtered): array {
    $header = [
      [
        'data' => $this->t('Created'),
        'width' => '15%',
      ],
    ];

    if (!$is_filtered) {
      $header[] = [
        'data' => $this->t('Entity'),
        'width' => '15%',
      ];
    }

    $header = array_merge($header, [
      [
        'data' => $this->t('Prompt'),
        'width' => '32%',
      ],
      [
        'data' => $this->t('Response'),
        'width' => '32%',
      ],
      [
        'data' => $this->t('Valid'),
        'width' => '5%',
      ],
    ]);

    return $header;
  }

  /**
   * Downloads the log as a CSV response.
   */
  public function download(): Response {
    $query = $this->getFilterQuery();
    $entity_type = $query['entity_type'] ?? '';
    $entity_id = $query['entity_id'] ?? '';
    $lines = [
      'entity_type,entity_id,entity_label,bundle,url,prompt,response,valid,created',
    ];

    foreach ($this->getDownloadRows($entity_type, $entity_id) as $row) {
      $lines[] = implode(',', [
        $this->escapeCsvValue($row['entity_type']),
        $this->escapeCsvValue($row['entity_id']),
        $this->escapeCsvValue($row['entity_label']),
        $this->escapeCsvValue($row['bundle']),
        $this->escapeCsvValue($row['url']),
        $this->escapeCsvValue($row['prompt']),
        $this->escapeCsvValue($row['response']),
        $this->escapeCsvValue($this->formatValid((int) $row['valid'])),
        $this->escapeCsvValue($this->formatCreated((int) $row['created'])),
      ]);
    }

    $response = new Response(implode("\n", $lines));
    $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $this->buildDownloadFilename($entity_type, $entity_id) . '"');
    return $response;
  }

  /**
   * Escapes a CSV value.
   *
   * @param string $value
   *   The value to escape.
   */
  protected function escapeCsvValue(string $value): string {
    $value = str_replace('"', '""', $value);
    return '"' . $value . '"';
  }

  /**
   * Builds the entity column cell.
   *
   * @param array $row
   *   The stored log row.
   */
  protected function buildEntityCell(array $row): mixed {
    $entity_label = $row['entity_label'] ?: $row['entity_type'] . ':' . $row['entity_id'];
    $entity_type = ($row['bundle'] !== '')
      ? ($row['entity_type'] . ':' . $row['bundle'])
      : $row['entity_type'];

    $suffix = [
      '#markup' => ' (' . $entity_type . ')',
    ];

    if ($row['url'] !== '') {
      return [
        'link' => Link::fromTextAndUrl($entity_label, Url::fromUri($row['url']))->toRenderable(),
        'suffix' => $suffix,
      ];
    }

    return $entity_label . ' (' . $entity_type . ')';
  }

  /**
   * Builds a preformatted table cell.
   *
   * @param string $value
   *   The string value to display.
   *
   * @return array
   *   A render array for the table cell.
   */
  protected function buildPreformattedCell(string $value): array {
    return [
      'data' => [
        '#type' => 'inline_template',
        '#template' => '<pre class="ai-schemadotorg-jsonld-log-page__content">{{ value }}</pre>',
        '#context' => [
          'value' => $value,
        ],
      ],
    ];
  }

  /**
   * Pretty-prints a JSON response when possible.
   *
   * @param string $response
   *   The stored response text.
   *
   * @return string
   *   The formatted response.
   */
  protected function formatResponse(string $response): string {
    try {
      $decoded = json_decode($response, TRUE, 512, JSON_THROW_ON_ERROR);
      return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
    catch (\JsonException) {
      return $response;
    }
  }

  /**
   * Formats the created timestamp.
   *
   * @param int $created
   *   The created timestamp.
   *
   * @return string
   *   The formatted timestamp.
   */
  protected function formatCreated(int $created): string {
    return $this->dateFormatter->format($created, 'custom', 'Y-m-d H:i:s');
  }

  /**
   * Formats the valid flag for display and export.
   *
   * @param int $valid
   *   The valid flag.
   *
   * @return string
   *   Yes when valid, otherwise No.
   */
  protected function formatValid(int $valid): string {
    return ($valid === 1) ? 'Yes' : 'No';
  }

  /**
   * Checks if prompt and response logging is enabled.
   */
  protected function isLoggingEnabled(): bool {
    return (bool) $this->config('ai_schemadotorg_jsonld_log.settings')->get('enable');
  }

  /**
   * Gets the active entity filter query parameters.
   *
   * @return array
   *   The active filter query, or an empty array.
   */
  protected function getFilterQuery(): array {
    $request = $this->requestStack->getCurrentRequest();
    $entity_type = (string) $request->query->get('entity_type', '');
    $entity_id = (string) $request->query->get('entity_id', '');

    if ($entity_type === '' || $entity_id === '') {
      return [];
    }

    return [
      'entity_type' => $entity_type,
      'entity_id' => $entity_id,
    ];
  }

  /**
   * Gets the filtered entity when one can be loaded.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity, or NULL.
   */
  protected function getFilteredEntity(): ?EntityInterface {
    $query = $this->getFilterQuery();
    if ($query === []) {
      return NULL;
    }

    $entity_type = $query['entity_type'];
    $entity_id = $query['entity_id'];
    if (!$this->entityTypeManager()->hasDefinition($entity_type)) {
      return NULL;
    }

    return $this->entityTypeManager()
      ->getStorage($entity_type)
      ->load($entity_id);
  }

  /**
   * Gets the rows to export.
   *
   * @param string $entity_type
   *   The entity type ID.
   * @param string $entity_id
   *   The entity ID.
   *
   * @return array
   *   The rows to export.
   */
  protected function getDownloadRows(string $entity_type = '', string $entity_id = ''): array {
    if ($entity_type === '' || $entity_id === '') {
      return $this->logStorage->loadAll();
    }

    $rows = [];
    foreach ($this->logStorage->loadAll() as $row) {
      if ($row['entity_type'] !== $entity_type || $row['entity_id'] !== $entity_id) {
        continue;
      }
      $rows[] = $row;
    }

    return $rows;
  }

  /**
   * Builds the CSV download filename.
   *
   * @param string $entity_type
   *   The entity type ID.
   * @param string $entity_id
   *   The entity ID.
   *
   * @return string
   *   The CSV filename.
   */
  protected function buildDownloadFilename(string $entity_type = '', string $entity_id = ''): string {
    if ($entity_type !== '' && $entity_id !== '') {
      return 'ai-schemadotorg-jsonld-' . $entity_type . '-' . $entity_id . '-log.csv';
    }

    return 'ai-schemadotorg-jsonld-log.csv';
  }

}
