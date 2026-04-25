<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov_report\Form;

use Drupal\clinical_trials_gov\Element\ClinicalTrialsGovStudiesQuery;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Search form for the ClinicalTrials.gov report.
 *
 * Contains a single ClinicalTrialsGovStudiesQuery element. On submit,
 * redirects to the studies report route with the assembled query string
 * as URL parameters.
 */
class ClinicalTrialsGovReportStudiesSearchForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'clinical_trials_gov_studies_search';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['studies_query'] = [
      '#type' => 'clinical_trials_gov_studies_query',
      '#default_value' => $this->getRequest()->getQueryString() ?? '',
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Search'),
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $query_string = (string) $form_state->getValue('studies_query');
    $parameters = ClinicalTrialsGovStudiesQuery::parseQueryString($query_string);
    $form_state->setRedirect('clinical_trials_gov_report.studies', [], ['query' => $parameters]);
  }

}
