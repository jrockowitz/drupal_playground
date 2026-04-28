<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Redirects the wizard manage step to the filtered content overview.
 */
class ClinicalTrialsGovManageController extends ControllerBase {

  /**
   * Redirects to the configured content overview or back to Configure.
   */
  public function index(): RedirectResponse {
    $type = (string) ($this->config('clinical_trials_gov.settings')->get('type') ?? '');

    if ($type === '') {
      $this->messenger()->addStatus($this->t('Create the destination content type before managing imported studies.'));
      return $this->redirect('clinical_trials_gov.configure');
    }

    return $this->redirect('system.admin_content', [], [
      'query' => [
        'title' => '',
        'type' => $type,
      ],
    ]);
  }

}
