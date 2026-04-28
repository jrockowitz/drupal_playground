<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;

/**
 * Displays the ClinicalTrials.gov import wizard landing page.
 */
class ClinicalTrialsGovController extends ControllerBase {

  /**
   * Returns the wizard index page.
   */
  public function index(): array {
    $config = $this->config('clinical_trials_gov.settings');
    $query = (string) ($config->get('query') ?? '');
    $type = (string) ($config->get('type') ?? '');
    $fields = array_values(array_filter($config->get('fields') ?? [], 'is_string'));
    $import_ready = ($query !== '' && $type !== '' && !empty($fields));

    if ($query === '') {
      $message = $this->buildMessage(Markup::create((string) $this->t('Please go to <a href=":url">Find</a> and build your query.', [
        ':url' => Url::fromRoute('clinical_trials_gov.find')->toString(),
      ])));
    }
    elseif ($type === '' || $fields === []) {
      $message = $this->buildMessage(Markup::create((string) $this->t('Your query is saved. Go to <a href=":url">Configure</a> and select the destination content type and fields.', [
        ':url' => Url::fromRoute('clinical_trials_gov.configure')->toString(),
      ])));
    }
    else {
      $message = $this->buildMessage(Markup::create((string) $this->t('Your query and field mapping are ready. Continue to <a href=":url">Import</a> when you are ready to sync studies.', [
        ':url' => Url::fromRoute('clinical_trials_gov.import')->toString(),
      ])));
    }

    return [
      'message' => $message,
      'introduction' => [
        '#markup' => '<p>' . $this->t('Use the tasks below to find ClinicalTrials.gov studies, review results, configure a destination content type, and run a full-sync import.') . '</p>',
      ],
      'content' => [
        '#theme' => 'admin_block_content',
        '#content' => [
          'find' => [
            'title' => $this->t('1. Find'),
            'description' => $this->t('Save the search query into configuration.'),
            'url' => Url::fromRoute('clinical_trials_gov.find'),
          ],
          'review' => [
            'title' => $this->t('2. Review'),
            'description' => $this->t('Review the trials returned by the saved query.'),
            'url' => Url::fromRoute('clinical_trials_gov.review'),
          ],
          'configure' => [
            'title' => $this->t('3. Configure'),
            'description' => $this->t('Create the trial content type and configure field mappings.'),
            'url' => Url::fromRoute('clinical_trials_gov.configure'),
          ],
          'import' => [
            'title' => $this->t('4. Import'),
            'description' => $import_ready
              ? $this->t('Review the import summary and run the migration.')
              : Markup::create((string) $this->t('Review the import summary and run the migration. Complete the Find and Configure steps first.')),
            'url' => Url::fromRoute('clinical_trials_gov.import'),
          ],
        ],
      ],
    ];
  }

  /**
   * Builds a status message render array with inline markup.
   */
  protected function buildMessage(Markup $message): array {
    return [
      '#theme' => 'status_messages',
      '#message_list' => [
        'status' => [$message],
      ],
      '#status_headings' => [
        'status' => $this->t('Status message'),
      ],
    ];
  }

}
