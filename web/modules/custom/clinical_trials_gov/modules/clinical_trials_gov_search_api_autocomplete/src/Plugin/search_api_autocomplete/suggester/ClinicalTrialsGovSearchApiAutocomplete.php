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
    $matches = [];

    foreach ($this->getSuggestionSources() as $source) {
      $matches = array_merge(
        $matches,
        $this->loadMatches(
          $source['table'],
          $source['column'],
          $user_input,
          $result_limit,
          $source['serialized']
        ),
      );
    }

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

  /**
   * Returns the available field storage sources for autocomplete suggestions.
   *
   * @return array
   *   A list of source table definitions.
   */
  protected function getSuggestionSources(): array {
    $candidate_sources = [
      [
        'table' => 'node__trial_cond_mod',
        'column' => 'trial_cond_mod_cond',
        'serialized' => TRUE,
      ],
      [
        'table' => 'node__trial_cond_mod',
        'column' => 'trial_cond_mod_keyword',
        'serialized' => TRUE,
      ],
      [
        'table' => 'node__trial_cond',
        'column' => 'trial_cond_value',
        'serialized' => FALSE,
      ],
      [
        'table' => 'node__trial_keyword',
        'column' => 'trial_keyword_value',
        'serialized' => FALSE,
      ],
    ];
    $schema = $this->database->schema();
    $sources = [];

    foreach ($candidate_sources as $candidate_source) {
      if (
        $schema->tableExists($candidate_source['table'])
        && $schema->fieldExists($candidate_source['table'], $candidate_source['column'])
      ) {
        $sources[] = $candidate_source;
      }
    }

    return $sources;
  }

  /**
   * Loads matching values from a single Clinical Trials field storage source.
   *
   * @param string $table
   *   The table name to query.
   * @param string $column
   *   The column containing the stored text values.
   * @param string $user_input
   *   The current autocomplete input.
   * @param int $limit
   *   The maximum number of rows to load from this table.
   * @param bool $serialized
   *   Whether the field stores serialized arrays of strings.
   *
   * @return array
   *   A list of matching values from the requested table.
   */
  protected function loadMatches(string $table, string $column, string $user_input, int $limit, bool $serialized = FALSE): array {
    $query = $this->database->select($table, 'trials');
    $query->distinct();
    $query->fields('trials', [$column]);
    $query->isNotNull($column);
    if ($serialized) {
      $query->where("LOWER(CAST($column AS CHAR)) LIKE LOWER(:match)", [
        ':match' => '%' . $this->database->escapeLike($user_input) . '%',
      ]);
    }
    else {
      $query->where("LOWER($column) LIKE LOWER(:match)", [
        ':match' => '%' . $this->database->escapeLike($user_input) . '%',
      ]);
    }
    $query->orderBy($column);
    $query->range(0, $limit);

    $matches = array_values($query->execute()->fetchCol());
    if (!$serialized) {
      return array_values(array_filter(array_map('trim', $matches), static fn(string $match): bool => $match !== ''));
    }

    return $this->extractSerializedMatches($matches, $user_input);
  }

  /**
   * Extracts matching plain-text values from serialized custom-field columns.
   *
   * @param array $serialized_matches
   *   The serialized values returned from field storage.
   * @param string $user_input
   *   The current autocomplete input.
   *
   * @return array
   *   A list of matching values extracted from the serialized data.
   */
  protected function extractSerializedMatches(array $serialized_matches, string $user_input): array {
    $matches = [];

    foreach ($serialized_matches as $serialized_match) {
      if (is_resource($serialized_match)) {
        $serialized_match = stream_get_contents($serialized_match) ?: '';
      }

      if (!is_string($serialized_match) || $serialized_match === '') {
        continue;
      }

      $values = @unserialize($serialized_match, ['allowed_classes' => FALSE]);
      if (!is_array($values)) {
        continue;
      }

      foreach ($values as $value) {
        if (!is_string($value)) {
          continue;
        }

        $value = trim($value);
        if ($value === '' || stripos($value, $user_input) === FALSE) {
          continue;
        }

        $matches[] = $value;
      }
    }

    return $matches;
  }

}
