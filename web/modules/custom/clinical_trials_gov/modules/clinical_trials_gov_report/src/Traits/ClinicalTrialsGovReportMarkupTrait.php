<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov_report\Traits;

use Drupal\Component\Utility\Html;

/**
 * Builds shared markup for ClinicalTrials.gov report controllers.
 */
trait ClinicalTrialsGovReportMarkupTrait {

  /**
   * Builds the version line markup.
   */
  protected function buildVersionMarkup(array $version): string {
    $updated = $this->dateFormatter->format(
      strtotime($version['dataTimestamp'] . ' UTC'),
      'custom',
      'F j Y \a\t g:i a'
    );

    return '<small>' . $this->t('Version: @version and Last Updated: @updated', [
      '@version' => $version['apiVersion'],
      '@updated' => $updated,
    ]) . '</small>';
  }

  /**
   * Builds a multi-line list cell.
   */
  protected function buildListCell(mixed $values): array|string {
    if (!is_array($values) || $values === []) {
      return '';
    }

    $items = array_values(array_filter(array_map(
      fn(mixed $item): string => is_scalar($item) ? (string) $item : '',
      $values
    )));

    return $this->buildTextCell(implode("\n", $items));
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

}
