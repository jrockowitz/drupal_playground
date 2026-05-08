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
   * @param string $nct_id
   *   The NCT ID from the route.
   */
  public function view(string $nct_id): array {
    $study = $this->studyManager->getStudy($nct_id);

    if (empty($study)) {
      return [
        '#type' => 'item',
        '#markup' => $this->t('Study @nct_id not found.', ['@nct_id' => $nct_id]),
      ];
    }

    $build = $this->builder->buildStudy($study, $nct_id);
    $build['#attached']['library'][] = 'clinical_trials_gov_report/report';

    return $build;
  }

  /**
   * Returns the page title from the study's brief title.
   *
   * @param string $nct_id
   *   The NCT ID from the route.
   */
  public function title(string $nct_id): string {
    $study = $this->studyManager->getStudy($nct_id);
    if (!$study) {
      return $nct_id;
    }
    return $study['protocolSection.identificationModule.briefTitle'];
  }

}
