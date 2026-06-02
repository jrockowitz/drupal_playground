<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Controller;

use Drupal\clinical_trials_gov\ClinicalTrialsGovPathsManagerInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovStudyManagerInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;

/**
 * Shared metadata page controller logic.
 */
abstract class ClinicalTrialsGovMetadataBaseController extends ControllerBase {

  /**
   * Metadata paths to display.
   *
   * NULL displays all metadata. An empty array loads saved query paths.
   */
  protected ?array $queryPaths = [];

  /**
   * Constructs a new ClinicalTrialsGovMetadataBaseController instance.
   */
  public function __construct(
    protected MessengerInterface $messageHandler,
    protected ConfigFactoryInterface $configurationFactory,
    protected ClinicalTrialsGovStudyManagerInterface $studyManager,
    protected ClinicalTrialsGovPathsManagerInterface $pathsManager,
  ) {}

  /**
   * Builds the metadata page.
   */
  public function index(): array {
    if (($this->queryPaths === []) && !$this->getQuery()) {
      $this->messageHandler->addWarning($this->t('No saved query was found. Start with the <a href=":find_url">Find</a> step.', [
        ':find_url' => Url::fromRoute('clinical_trials_gov.find')->toString(),
      ]));
      return [];
    }

    $metadata = $this->getDisplayedMetadata();
    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => $this->getContainerClasses(),
      ],
      '#attached' => [
        'library' => $this->getAttachedLibraries(),
      ],
      'intro' => $this->buildIntro(),
      'summary' => $this->buildSummary($metadata),
      'results' => $this->buildMetadataTable($metadata),
    ];

    $footer = $this->buildFooter();
    if ($footer) {
      $build['footer'] = $footer;
    }

    return $build;
  }

  /**
   * Builds the page intro section.
   */
  abstract protected function buildIntro(): array;

  /**
   * Builds a summary output based on the provided metadata.
   *
   * @param array $metadata
   *   An array of metadata fields to be summarized.
   *
   * @return array
   *   A renderable array containing the summary of the metadata fields.
   */
  protected function buildSummary(array $metadata): array {
    return [
      '#type' => 'item',
      '#markup' => $this->t('Showing @count fields', ['@count' => count($metadata)]),
    ];
  }

  /**
   * Builds the page footer section.
   */
  abstract protected function buildFooter(): array;

  /**
   * Returns the libraries attached to the page.
   */
  protected function getAttachedLibraries(): array {
    return ['clinical_trials_gov/clinical_trials_gov'];
  }

  /**
   * Returns the page container classes.
   */
  protected function getContainerClasses(): array {
    return ['clinical-trials-gov-report-metadata'];
  }

  /**
   * Returns the metadata rows that should be displayed.
   */
  protected function getDisplayedMetadata(): array {
    $metadata = $this->studyManager->getMetadataByPath();
    $path_lookup = $this->getDisplayedPathLookup();
    if ($path_lookup === NULL) {
      return $metadata;
    }

    if (!$path_lookup) {
      return [];
    }

    $displayed_metadata = [];
    foreach ($metadata as $path => $row) {
      if (!is_array($row) || !isset($path_lookup[$path])) {
        continue;
      }

      $displayed_metadata[$path] = $row;
    }

    return $displayed_metadata;
  }

  /**
   * Builds the metadata table.
   */
  protected function buildMetadataTable(array $metadata): array {
    $rows = [];

    foreach ($metadata as $row) {
      if (!is_array($row)) {
        continue;
      }

      $rows[] = [
        'data' => $this->buildRow($row),
        'class' => [],
      ];
    }

    return [
      '#type' => 'table',
      '#attributes' => [
        'class' => ['clinical-trials-gov-table'],
      ],
      '#header' => [
        $this->buildHeader('Field Name', 'Piece Name'),
        $this->buildHeader('Field Title', 'Description/Notes/Definition'),
        $this->t('Path'),
        $this->t('Type'),
        $this->t('Operations'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No metadata returned.'),
    ];
  }

  /**
   * Builds one metadata row in documentation-table format.
   */
  protected function buildRow(array $row): array {
    $depth = substr_count((string) ($row['path'] ?? ''), '.');
    $classic_type = (string) ($row['sourceType'] ?? '');
    $max_chars = $row['maxChars'] ?? NULL;
    if (is_int($max_chars) && $classic_type) {
      $classic_type .= ' (max ' . $max_chars . ' chars)';
    }

    return [
      $this->buildPrimarySecondaryCell(
        primary: (string) ($row['name'] ?? ''),
        secondary: (string) ($row['piece'] ?? ''),
        depth: $depth,
      ),
      $this->buildFieldTitleCell(
        title: (string) ($row['title'] ?? ''),
        description: (string) ($row['description'] ?? ''),
        notes: (string) ($row['rules'] ?? ''),
        definition_label: (string) ($row['dedLinkLabel'] ?? ''),
        definition_url: (string) ($row['dedLinkUrl'] ?? ''),
      ),
      $this->buildPathCell((string) ($row['path'] ?? '')),
      $this->buildTypeCell($classic_type, $row),
      $this->buildOperationsCell($row),
    ];
  }

  /**
   * Builds a header cell with optional secondary text.
   */
  protected function buildHeader(string $primary, string $secondary): array {
    return [
      'data' => [
        'primary' => [
          '#type' => 'html_tag',
          '#tag' => 'strong',
          '#value' => $primary,
        ],
        'line_break' => [
          '#type' => 'html_tag',
          '#tag' => 'br',
          '#access' => ($secondary),
        ],
        'secondary' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $secondary,
          '#attributes' => [
            'class' => ['clinical-trials-gov-report-metadata__secondary'],
          ],
          '#access' => ($secondary),
        ],
      ],
    ];
  }

  /**
   * Builds a cell with primary and secondary lines.
   */
  protected function buildPrimarySecondaryCell(string $primary, string $secondary, int $depth = 0): array {
    $style = ($depth > 0) ? 'padding-left:' . ($depth * 1.5) . 'rem' : '';

    $cell = [
      'primary' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'class' => ['clinical-trials-gov-report-metadata__primary'],
        ],
        'content' => [
          '#type' => 'html_tag',
          '#tag' => 'strong',
          '#value' => $primary,
        ],
      ],
    ];

    if ($style) {
      $cell['primary']['#attributes']['style'] = $style;
    }

    if ($secondary) {
      $cell['secondary'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'class' => ['clinical-trials-gov-report-metadata__secondary'],
        ],
        'content' => [
          '#type' => 'html_tag',
          '#tag' => 'small',
          '#value' => $secondary,
        ],
      ];

      if ($style) {
        $cell['secondary']['#attributes']['style'] = $style;
      }
    }

    return [
      'data' => $cell,
    ];
  }

  /**
   * Builds a plain text cell preserving line breaks.
   */
  protected function buildTextCell(string $value): array|string {
    if (!$value) {
      return '';
    }

    return [
      'data' => [
        '#markup' => nl2br(Html::escape($value)),
      ],
    ];
  }

  /**
   * Builds the combined field title, description, and notes cell.
   */
  protected function buildFieldTitleCell(string $title, string $description, string $notes, string $definition_label, string $definition_url): array|string {
    if (!$title && !$description && !$notes && !$definition_label) {
      return '';
    }

    $content = [];
    if ($title) {
      $content['title'] = [
        '#type' => 'html_tag',
        '#tag' => 'strong',
        '#value' => $title,
      ];
      $content['title_break'] = [
        '#type' => 'html_tag',
        '#tag' => 'br',
      ];
    }
    if ($description) {
      $content['description'] = [
        '#markup' => '<small>' . nl2br(Html::escape($description)) . '</small>',
      ];
      $content['description_break'] = [
        '#type' => 'html_tag',
        '#tag' => 'br',
      ];
    }
    if ($notes) {
      $content['notes'] = [
        '#type' => 'html_tag',
        '#tag' => 'em',
        'content' => [
          '#markup' => '<small>' . nl2br(Html::escape($notes)) . '</small>',
        ],
      ];
      $content['notes_break'] = [
        '#type' => 'html_tag',
        '#tag' => 'br',
      ];
    }
    if ($definition_label) {
      $content['definition'] = [
        '#type' => 'html_tag',
        '#tag' => 'small',
      ];
      if ($definition_url) {
        $content['definition']['content'] = [
          '#type' => 'link',
          '#title' => $definition_label,
          '#url' => Url::fromUri($definition_url),
        ];
      }
      else {
        $content['definition']['#value'] = $definition_label;
      }
      $content['definition_break'] = [
        '#type' => 'html_tag',
        '#tag' => 'br',
      ];
    }

    return [
      'data' => $content,
    ];
  }

  /**
   * Builds the combined type cell.
   */
  protected function buildTypeCell(string $classic_type, array $row): array|string {
    $value = (string) ($row['type'] ?? '');
    $suffixes = [];

    if (!empty($row['synonyms'])) {
      $suffixes[] = 'synonyms';
    }

    if ($suffixes) {
      $value .= ' (' . implode(', ', $suffixes) . ')';
    }

    if (!$classic_type && !$value) {
      return '';
    }

    $content = [];
    if ($classic_type) {
      $content['classic_type'] = [
        '#markup' => Html::escape($classic_type),
      ];
    }
    if ($classic_type && $value) {
      $content['line_break'] = [
        '#type' => 'html_tag',
        '#tag' => 'br',
      ];
    }
    if ($value) {
      $content['data_type'] = [
        '#type' => 'html_tag',
        '#tag' => 'small',
        '#value' => $value,
      ];
    }

    return [
      'data' => $content,
    ];
  }

  /**
   * Builds the definition cell.
   */
  protected function buildPathCell(string $path): array|string {
    if (!$path) {
      return '';
    }

    return [
      'data' => [
        '#type' => 'html_tag',
        '#tag' => 'small',
        '#value' => $path,
      ],
    ];
  }

  /**
   * Builds the operations cell.
   */
  protected function buildOperationsCell(array $row): array|string {
    if (($row['sourceType'] ?? '') === 'STRUCT') {
      return '';
    }

    $piece = (string) ($row['piece'] ?? '');
    if (!$piece) {
      return '';
    }

    return [
      'data' => [
        '#type' => 'link',
        '#title' => $this->t('View values'),
        '#url' => Url::fromUri('https://clinicaltrials.gov/api/v2/stats/field/values?fields=' . rawurlencode($piece)),
        '#attributes' => [
          'class' => ['button', 'button--small'],
        ],
      ],
    ];
  }

  /**
   * Returns the saved query string.
   */
  protected function getQuery(): string {
    return $this->configurationFactory->get('clinical_trials_gov.settings')->get('query');
  }

  /**
   * Returns configured metadata paths.
   */
  protected function getQueryPaths(): array {
    return $this->pathsManager->getQueryPaths();
  }

  /**
   * Returns configured required metadata paths.
   */
  protected function getRequiredPaths(): array {
    return $this->pathsManager->getRequiredPaths();
  }

  /**
   * Returns the metadata path lookup for the current page.
   */
  protected function getDisplayedPathLookup(): ?array {
    if ($this->queryPaths === NULL) {
      return NULL;
    }

    $paths = ($this->queryPaths === []) ? $this->getQueryPaths() : $this->queryPaths;
    if (!$paths) {
      return [];
    }

    return array_fill_keys($paths, TRUE);
  }

}
