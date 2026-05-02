<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Controller;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Controller\ControllerBase;
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
    $query = $config->get('query');
    $paths = $config->get('query_paths');
    $type = $config->get('type');
    $field_mappings = $config->get('fields');
    $import_ready = ($query && $paths && $type && $field_mappings);

    if (!$query) {
      $message = $this->buildMessage($this->t('Please go to <a href=":url">Find</a> and build your query.', [
        ':url' => Url::fromRoute('clinical_trials_gov.find')->toString(),
      ]));
    }
    elseif (!$paths) {
      $message = $this->buildMessage($this->t('Your query is saved, but no fields were discovered. Go back to <a href=":url">Find</a> and save a query that returns studies.', [
        ':url' => Url::fromRoute('clinical_trials_gov.find')->toString(),
      ]));
    }
    elseif (!$type || !$field_mappings) {
      $message = $this->buildMessage($this->t('Your query is saved. Go to <a href=":url">Configure</a> and select the destination content type and fields.', [
        ':url' => Url::fromRoute('clinical_trials_gov.configure')->toString(),
      ]));
    }
    else {
      $message = $this->buildMessage($this->t('Your query and field mapping are ready. Continue to <a href=":url">Import</a> when you are ready to sync studies.', [
        ':url' => Url::fromRoute('clinical_trials_gov.import')->toString(),
      ]));
    }

    return [
      'message' => $message,
      'introduction' => [
        '#markup' => '<p>' . $this->t('Use the tasks below to find ClinicalTrials.gov studies, review studies and metadata, configure a destination content type, run a full-sync import, manage imported content, and adjust advanced settings when needed.') . '</p>',
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
            'description' => $this->t('Review the studies and metadata returned by the saved query.'),
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
              : $this->t('Review the import summary and run the migration. Complete the Find and Configure steps first.'),
            'url' => Url::fromRoute('clinical_trials_gov.import'),
          ],
          'manage' => [
            'title' => $this->t('5. Manage'),
            'description' => $this->t('Manage imported content for the configured content type.'),
            'url' => Url::fromRoute('clinical_trials_gov.manage'),
          ],
          'settings' => [
            'title' => $this->t('Settings'),
            'description' => $this->t('Advanced settings for the content type machine name and generated field prefix.'),
            'url' => Url::fromRoute('clinical_trials_gov.settings'),
          ],
        ],
      ],
    ];
  }

  /**
   * Builds a status message render array with inline markup.
   */
  protected function buildMessage(MarkupInterface|string $message): array {
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
