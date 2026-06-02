<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov_data\Plugin\search_api_autocomplete\suggester;

use Drupal\Core\Database\Connection;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api_autocomplete\Attribute\SearchApiAutocompleteSuggester;
use Drupal\search_api_autocomplete\Suggester\SuggesterPluginBase;
use Drupal\search_api_autocomplete\Suggestion\SuggestionFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Suggests normalized trial condition terms from Drupal field storage.
 */
#[SearchApiAutocompleteSuggester(
  id: 'clinical_trials_gov_fields_search_api_autocomplete',
  label: new TranslatableMarkup('Clinical Trials field terms'),
  description: new TranslatableMarkup('Loads normalized trial condition suggestions directly from Drupal field storage.'),
)]
class ClinicalTrialsGovFieldsSearchApiAutocomplete extends SuggesterPluginBase {

  /**
   * Constructs a new ClinicalTrials.gov field autocomplete suggester.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    protected Connection $database,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * Creates the suggester plugin from the service container.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param array $configuration
   *   The plugin configuration.
   * @param mixed $plugin_id
   *   The plugin identifier.
   * @param mixed $plugin_definition
   *   The plugin definition.
   *
   * @return static
   *   The instantiated suggester plugin.
   */
  // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getAutocompleteSuggestions(QueryInterface $query, $incomplete_key, $user_input): array {
    $incomplete_key = trim((string) $incomplete_key);
    if ($incomplete_key === '') {
      return [];
    }

    $factory = new SuggestionFactory($user_input);
    $suggestions = [];
    $limit = (int) $query->getOption('limit', 10);

    foreach ($this->getSuggestions($incomplete_key, $limit) as $suggestion_value) {
      $suggestions[] = $factory->createFromSuggestedKeys($suggestion_value);
    }

    return $suggestions;
  }

  /**
   * Returns distinct trial condition suggestions for the input.
   *
   * @param string $user_input
   *   The current autocomplete text entered by the user.
   * @param int $limit
   *   The maximum number of suggestions to return.
   *
   * @return array
   *   A sorted list of suggestion strings.
   */
  protected function getSuggestions(string $user_input, int $limit): array {
    $user_input = trim($user_input);
    if (($user_input === '') || ($limit < 1)) {
      return [];
    }

    $query = $this->database->select('node__field_trial_condition', 'trial_condition');
    $query->distinct();
    $query->fields('trial_condition', ['field_trial_condition_value']);
    $query->isNotNull('field_trial_condition_value');
    $query->where('LOWER(field_trial_condition_value) LIKE LOWER(:match)', [
      ':match' => '%' . $this->database->escapeLike($user_input) . '%',
    ]);
    $query->orderBy('field_trial_condition_value');
    $query->range(0, max(10, $limit * 2));

    $matches = array_map('trim', array_values($query->execute()->fetchCol()));
    $matches = array_filter($matches, static fn(string $match): bool => $match !== '');

    usort($matches, static function (string $first_match, string $second_match): int {
      $comparison = strnatcasecmp($first_match, $second_match);
      return ($comparison !== 0) ? $comparison : strcmp($first_match, $second_match);
    });

    $unique_matches = [];
    foreach ($matches as $match) {
      $normalized_match = mb_strtolower($match);
      if (isset($unique_matches[$normalized_match])) {
        continue;
      }
      $unique_matches[$normalized_match] = $match;
      if (count($unique_matches) === $limit) {
        break;
      }
    }

    return array_values($unique_matches);
  }

}
