<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Controller;

use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays filtered metadata for the review step.
 */
class ClinicalTrialsGovReviewMetadataController extends ClinicalTrialsGovMetadataBaseController {

  /**
   * Creates the controller from the container.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('clinical_trials_gov.study_manager'),
      $container->get('config.factory'),
      $container->get('clinical_trials_gov.paths_manager'),
      $container->get('messenger'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function index(): array {
    $build = parent::index();
    if (!$build) {
      return $build;
    }

    $saved_query = $this->getSavedQuery();
    $build['studies_query'] = [
      '#type' => 'details',
      '#title' => $this->t('Studies query'),
      '#open' => FALSE,
      'summary' => [
        '#type' => 'clinical_trials_gov_studies_query_summary',
        '#query' => $saved_query,
      ],
      'links' => $this->buildActionLinks([
        'find' => [
          'title' => $this->t('Edit studies query'),
          'url' => Url::fromRoute('clinical_trials_gov.find'),
        ],
      ]),
    ];

    return [
      '#type' => $build['#type'],
      '#attributes' => $build['#attributes'],
      '#attached' => $build['#attached'],
      'intro' => $build['intro'],
      'studies_query' => $build['studies_query'],
      'summary' => $build['summary'],
      'results' => $build['results'],
    ] + (isset($build['footer']) ? ['footer' => $build['footer']] : []);
  }

  /**
   * {@inheritdoc}
   */
  protected function buildIntro(): array {
    return [
      '#markup' => '<p>' . $this->t('Below are fields that are current being used by your selected studies.') . '</p>',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function buildFooter(): array {
    $paths = $this->getQueryPaths();
    $path_items = array_map(static fn(string $path): array => [
      '#type' => 'html_tag',
      '#tag' => 'small',
      '#value' => $path,
    ], $paths);

    return [
      'field_paths' => [
        '#type' => 'details',
        '#title' => $this->t('Field paths'),
        '#open' => FALSE,
        'intro' => [
          '#markup' => '<p>' . $this->t('Below are all field paths used by queried studies from ClinicalTrials.gov.') . '</p>',
        ],
        'paths' => [
          '#theme' => 'item_list',
          '#items' => $path_items,
        ],
      ],
    ];
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
