<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov_report\Controller;

use Drupal\clinical_trials_gov\ClinicalTrialsGovBuilderInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovStudyManagerInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders the detail page for a single ClinicalTrials.gov study.
 */
class ClinicalTrialsGovReportStudyController extends ControllerBase {

  /**
   * Constructs a new ClinicalTrialsGovReportStudyController instance.
   */
  public function __construct(
    protected ClinicalTrialsGovBuilderInterface $builder,
    protected ClinicalTrialsGovStudyManagerInterface $studyManager,
  ) {}

  /**
   * Creates the controller from the container.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('clinical_trials_gov.builder'),
      $container->get('clinical_trials_gov.study_manager'),
    );
  }

  /**
   * Renders the study detail page.
   *
   * @param string $nctId
   *   The NCT ID from the route.
   */
  public function view(string $nctId): array {
    $study = $this->studyManager->getStudy($nctId);

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
    $study = $this->studyManager->getStudy($nctId);
    if ($study === []) {
      return $nctId;
    }
    return $study['protocolSection.identificationModule.briefTitle'];
  }

}
