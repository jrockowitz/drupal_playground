<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld_log\Controller;

use Drupal\ai_schemadotorg_jsonld_log\AiSchemaDotOrgJsonLdLogStorageInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
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
   */
  public function __construct(
    protected readonly AiSchemaDotOrgJsonLdLogStorageInterface $logStorage,
    protected readonly DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(AiSchemaDotOrgJsonLdLogStorageInterface::class),
      $container->get('date.formatter'),
    );
  }

  /**
   * Displays the log table and operations.
   */
  public function index(): array {
    $rows = [];
    foreach ($this->logStorage->loadPage(10) as $row) {
      $rows[] = [
        'created' => $this->dateFormatter->format((int) $row['created'], 'custom', 'Y-m-d'),
        'entity' => [
          'data' => $this->buildEntityCell($row),
        ],
        'prompt' => $this->buildPreformattedCell($row['prompt']),
        'response' => $this->buildPreformattedCell($this->formatResponse($row['response'])),
      ];
    }

    $download_link = Link::fromTextAndUrl($this->t('Download CSV'), Url::fromRoute('ai_schemadotorg_jsonld_log.download'))
      ->toRenderable();
    $download_link['#attributes']['class'] = ['button', 'button--small'];

    $clear_link = Link::fromTextAndUrl($this->t('Clear log'), Url::fromRoute('ai_schemadotorg_jsonld_log.clear'))
      ->toRenderable();
    $clear_link['#attributes']['class'] = ['use-ajax', 'button', 'button--small'];
    $clear_link['#attributes']['data-dialog-type'] = 'modal';
    $clear_link['#attributes']['data-dialog-options'] = Json::encode(['width' => 700]);
    $clear_link['#attached']['library'][] = 'core/drupal.dialog.ajax';

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['ai-schemadotorg-jsonld-log-page']],
      '#attached' => [
        'library' => ['ai_schemadotorg_jsonld_log/ai_schemadotorg_jsonld_log'],
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [
          [
            'data' => $this->t('Created'),
            'width' => '10%',
          ],
          [
            'data' => $this->t('Entity'),
            'width' => '14%',
          ],
          [
            'data' => $this->t('Prompt'),
            'width' => '38%',
          ],
          [
            'data' => $this->t('Response'),
            'width' => '38%',
          ],
        ],
        '#rows' => $rows,
        '#empty' => $this->t('No log entries available.'),
        '#attributes' => ['class' => ['ai-schemadotorg-jsonld-log-page__table']],
      ],
      'pager' => [
        '#type' => 'pager',
      ],
      'operations' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['ai-schemadotorg-jsonld-log-operations']],
        'download' => $download_link,
        'clear' => $clear_link,
      ],
    ];
  }

  /**
   * Downloads the log as a CSV response.
   */
  public function download(): Response {
    $lines = [
      'entity_type,entity_id,entity_label,bundle,url,prompt,response,created',
    ];

    foreach ($this->logStorage->loadAll() as $row) {
      $lines[] = implode(',', [
        $this->escapeCsvValue($row['entity_type']),
        $this->escapeCsvValue($row['entity_id']),
        $this->escapeCsvValue($row['entity_label']),
        $this->escapeCsvValue($row['bundle']),
        $this->escapeCsvValue($row['url']),
        $this->escapeCsvValue($row['prompt']),
        $this->escapeCsvValue($row['response']),
        $this->escapeCsvValue((string) $row['created']),
      ]);
    }

    $response = new Response(implode("\n", $lines));
    $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="ai-schemadotorg-jsonld-log.csv"');
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

}
