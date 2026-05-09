<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov_search_api_autocomplete\Plugin\search_api_autocomplete\suggester;

use Drupal\Core\Database\Connection;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api_autocomplete\Attribute\SearchApiAutocompleteSuggester;
use Drupal\search_api_autocomplete\Suggester\SuggesterPluginBase;
use Drupal\search_api_autocomplete\Suggestion\SuggestionFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Suggests trial terms directly from Drupal field storage tables.
 */
#[SearchApiAutocompleteSuggester(
  id: 'clinical_trials_gov_search_api_autocomplete',
  label: new TranslatableMarkup('Clinical Trials database terms'),
  description: new TranslatableMarkup('Loads trial condition and keyword suggestions directly from Drupal field storage.'),
)]
class ClinicalTrialsGovSearchApiAutocomplete extends SuggesterPluginBase {

  /**
   * Constructs a new Clinical Trials autocomplete suggester.
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
   * @param string $plugin_id
   *   The plugin identifier.
   * @param mixed $plugin_definition
   *   The plugin definition.
   *
   * @return static
   *   The instantiated suggester plugin.
   */
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
   * Returns distinct trial condition and keyword suggestions for the input.
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

    $result_limit = max(10, $limit * 2);
    $matches = array_merge(
      $this->loadMatches('node__trial_cond', 'trial_cond_value', $user_input, $result_limit),
      $this->loadMatches('node__trial_keyword', 'trial_keyword_value', $user_input, $result_limit),
    );

    usort($matches, 'strnatcasecmp');

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

  /**
   * Loads matching values from a single Clinical Trials field storage table.
   *
   * @param string $table
   *   The table name to query.
   * @param string $column
   *   The column containing the stored text values.
   * @param string $user_input
   *   The current autocomplete input.
   * @param int $limit
   *   The maximum number of rows to load from this table.
   *
   * @return array
   *   A list of matching values from the requested table.
   */
  protected function loadMatches(string $table, string $column, string $user_input, int $limit): array {
    $query = $this->database->select($table, 'trials');
    $query->distinct();
    $query->fields('trials', [$column]);
    $query->isNotNull($column);
    $query->where("TRIM($column) <> ''");
    $query->where("LOWER($column) LIKE LOWER(:match)", [
      ':match' => $this->database->escapeLike($user_input) . '%',
    ]);
    $query->orderBy($column);
    $query->range(0, $limit);

    return array_values($query->execute()->fetchCol());
  }

}
