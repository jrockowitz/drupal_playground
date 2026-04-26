<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov_report\Controller;

use Drupal\clinical_trials_gov\ClinicalTrialsGovBuilderInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovManagerInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders the detail page for a single ClinicalTrials.gov study.
 */
class ClinicalTrialsGovReportStudyController extends ControllerBase {

  public function __construct(
    protected ClinicalTrialsGovManagerInterface $manager,
    protected ClinicalTrialsGovBuilderInterface $builder,
  ) {}

  /**
   * Creates the controller from the container.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('clinical_trials_gov.manager'),
      $container->get('clinical_trials_gov.builder'),
    );
  }

  /**
   * Renders the study detail page.
   *
   * @param string $nctId
   *   The NCT ID from the route.
   */
  public function view(string $nctId): array {
    $study = $this->manager->getStudy($nctId);

    if (empty($study)) {
      return [
        '#type' => 'item',
        '#markup' => $this->t('Study @nct_id not found.', ['@nct_id' => $nctId]),
      ];
    }

    $build = $this->builder->buildStudy($study, $nctId);
    $build['#attached']['library'][] = 'clinical_trials_gov_report/report';

    return $build;
  }

  /**
   * Returns the page title from the study's brief title.
   *
   * @param string $nctId
   *   The NCT ID from the route.
   */
  public function title(string $nctId): string {
    $study = $this->manager->getStudy($nctId);
    return (string) ($study['protocolSection.identificationModule.briefTitle'] ?? $nctId);
  }

}
