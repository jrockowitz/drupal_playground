<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld_log\Controller;

use Drupal\ai_schemadotorg_jsonld\Traits\AiSchemaDotOrgJsonLdMessageTrait;
use Drupal\ai_schemadotorg_jsonld_log\AiSchemaDotOrgJsonLdLogStorageInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Builds the AI Schema.org JSON-LD log admin UI.
 *
 * @phpstan-consistent-constructor
 */
class AiSchemaDotOrgJsonLdLogController extends ControllerBase {

  use AiSchemaDotOrgJsonLdMessageTrait;

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
  public function title(): TranslatableMarkup {
    $entity = $this->getFilteredEntity();
    if ($entity) {
      return $this->t('AI Schema.org JSON-LD: @label', ['@label' => $entity->label()]);
    }

    return $this->t('AI Schema.org JSON-LD');
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
      return $this->buildMessages([
        $this->t('Prompt and response logging is disabled.'),
        $this->t('Enable prompt and response logging in the Schema.org JSON-LD settings to view logs.'),
      ], 'warning');
    }

    $filter_query = $this->getFilterQuery();
    $entity_type = $filter_query['entity_type'] ?? '';
    $entity_id = $filter_query['entity_id'] ?? '';

    // Builds rows.
    $rows = [];
    foreach ($this->logStorage->loadMultiple($entity_type, $entity_id) as $row) {
      $data = [
        'created' => $this->formatCreated((int) $row['created']),
        'prompt' => $this->buildPreformattedCell($row['prompt']),
        'response' => $this->buildPreformattedCell($this->formatResponse($row['response'])),
        'value' => $this->formatYesNo((int) $row['valid']),
      ];
      if (!$filter_query) {
        $data = [
          'created' => $data['created'],
          'entity' => [
            'data' => $this->buildEntityCell($row),
          ],
          'prompt' => $data['prompt'],
          'response' => $data['response'],
          'value' => $data['value'],
        ];
      }

      $rows[] = [
        'class' => ((int) $row['valid'] === 0) ? ['ai-schemadotorg-jsonld-log-page__row--warning'] : [],
        'data' => $data,
      ];
    }

    // Build operations.
    $operations = [
      '#type' => 'container',
    ];

    $download_url = Url::fromRoute('ai_schemadotorg_jsonld_log.download');
    if ($filter_query) {
      $download_url->setOption('query', $filter_query);
    }
    $download_link = Link::fromTextAndUrl($this->t('Download CSV'), $download_url)
      ->toRenderable();
    $download_link['#attributes']['class'] = ['button', 'button--small'];

    $operations['download'] = $download_link;

    if (!$filter_query) {
      $clear_link = Link::fromTextAndUrl($this->t('Clear log'), Url::fromRoute('ai_schemadotorg_jsonld_log.clear'))
        ->toRenderable();
      $clear_link['#attributes']['class'] = ['use-ajax', 'button', 'button--small'];
      $clear_link['#attributes']['data-dialog-type'] = 'modal';
      $clear_link['#attributes']['data-dialog-options'] = Json::encode(['width' => 700]);
      $clear_link['#attached']['library'][] = 'core/drupal.dialog.ajax';
      $operations['clear'] = $clear_link;
    }

    $header = [];
    $header['created'] = ['data' => $this->t('Created'), 'width' => '15%'];
    if (!$filter_query) {
      $header['entity'] = ['data' => $this->t('Entity'), 'width' => '15%'];
    }
    $header['prompt'] = ['data' => $this->t('Prompt'), 'width' => '32%'];
    $header['response'] = ['data' => $this->t('Response'), 'width' => '32%'];
    $header['value'] = ['data' => $this->t('Valid'), 'width' => '5%'];


    return [
      '#type' => 'container',
      '#attached' => [
        'library' => ['ai_schemadotorg_jsonld_log/ai_schemadotorg_jsonld_log'],
      ],
      'table' => [
        '#type' => 'table',
        '#header' => $header,
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
   * Downloads the log as a CSV response.
   *
   * Uses StreamedResponse and fputcsv to efficiently handle potentially large
   * logs and ensure correct escaping of multiline prompts and responses.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The CSV download response.
   */
  public function download(): Response {
    $query = $this->getFilterQuery();
    $entity_type = $query['entity_type'] ?? '';
    $entity_id = $query['entity_id'] ?? '';
    $filename = $this->buildDownloadFilename($entity_type, $entity_id);

    $response = new StreamedResponse(function () use ($entity_type, $entity_id): void {
      $handle = fopen('php://output', 'w');

      // Write the CSV header row.
      fputcsv($handle, [
        'entity_type',
        'entity_id',
        'entity_label',
        'bundle',
        'url',
        'prompt',
        'response',
        'valid',
        'created',
      ]);

      // Write each log row, formatting the 'value' and 'created' columns.
      foreach ($this->getDownloadRows($entity_type, $entity_id) as $row) {
        fputcsv($handle, [
          $row['entity_type'],
          $row['entity_id'],
          $row['entity_label'],
          $row['bundle'],
          $row['url'],
          $row['prompt'],
          $row['response'],
          $this->formatYesNo((int) $row['valid']),
          $this->formatCreated((int) $row['created']),
        ]);
      }
      fclose($handle);
    });

    $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
    return $response;
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

    $label_element = $row['url']
      ? Link::fromTextAndUrl($entity_label, Url::fromUri($row['url']))->toRenderable()
      : ['#plain_text' => $entity_label];

    return $label_element + ['#suffix' => ' (' . $entity_type . ')'];
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
        '#plain_text' => $value,
        '#prefix' => '<pre>',
        '#suffix' => '</pre>',
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
   * Formats a value as Yes/No.
   *
   * @param int $value
   *   The value.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Yes when value is TRUE, otherwise No.
   */
  protected function formatYesNo(int $value): TranslatableMarkup {
    return ($value) ? $this->t('Yes') : $this->t('No');
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
    $entity_type = $request->query->get('entity_type');
    $entity_id = $request->query->get('entity_id');
    return ($entity_type && $entity_id)
      ? ['entity_type' => $entity_type, 'entity_id' => $entity_id]
      : [];
  }

  /**
   * Gets the filtered entity when one can be loaded.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity, or NULL.
   */
  protected function getFilteredEntity(): ?EntityInterface {
    $query = $this->getFilterQuery();
    if (!$query) {
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
    if (!$entity_type && !$entity_id) {
      return $this->logStorage->loadAll();
    }

    return $this->logStorage->loadAllByEntity($entity_type, $entity_id);
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
    return ($entity_type  && $entity_id)
      ? 'ai-schemadotorg-jsonld-' . $entity_type . '-' . $entity_id . '-log.csv'
      : 'ai-schemadotorg-jsonld-log.csv';
  }

}
