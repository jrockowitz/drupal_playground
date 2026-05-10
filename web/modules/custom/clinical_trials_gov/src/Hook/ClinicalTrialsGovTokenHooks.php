<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Hook;

use Drupal\clinical_trials_gov\ClinicalTrialsGovEntityManagerInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovStudyManagerInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\Core\Utility\Token;

/**
 * Token hook implementations for the ClinicalTrials.gov module.
 */
class ClinicalTrialsGovTokenHooks {

  use StringTranslationTrait;

  /**
   * The metadata path that identifies the node NCT ID field.
   */
  protected const NCT_ID_PATH = 'protocolSection.identificationModule.nctId';

  /**
   * Constructs a new ClinicalTrialsGovTokenHooks instance.
   */
  public function __construct(
    protected Token $token,
    protected ModuleHandlerInterface $moduleHandler,
    protected ClinicalTrialsGovEntityManagerInterface $entityManager,
    protected ClinicalTrialsGovStudyManagerInterface $studyManager,
  ) {}

  /**
   * Implements hook_token_info().
   */
  #[Hook('token_info')]
  public function tokenInfo(): array {
    return [
      'tokens' => [
        'node' => [
          'clinical-trials-gov' => [
            'name' => $this->t('ClinicalTrials.gov'),
            'description' => $this->buildApiTokenDescription(),
            'dynamic' => TRUE,
          ],
        ],
      ],
    ];
  }

  /**
   * Implements hook_tokens().
   *
   * @param string $type
   *   The token type being replaced.
   * @param array $tokens
   *   The requested tokens keyed by token name.
   * @param array $data
   *   Token context data.
   * @param array $options
   *   Token options.
   * @param \Drupal\Core\Render\BubbleableMetadata $bubbleable_metadata
   *   Bubbleable metadata collected during replacement.
   *
   * @return array
   *   Replacement values keyed by original token.
   */
  #[Hook('tokens')]
  public function tokens(string $type, array $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata): array {
    return ($type === 'node') ? $this->replaceNodeTokens($tokens, $data, $bubbleable_metadata) : [];
  }

  /**
   * Replaces ClinicalTrials.gov node tokens.
   *
   * @param array $tokens
   *   The requested tokens keyed by token name.
   * @param array $data
   *   Token context data.
   * @param \Drupal\Core\Render\BubbleableMetadata $bubbleable_metadata
   *   Bubbleable metadata collected during replacement.
   *
   * @return array
   *   Replacement values keyed by original token.
   */
  protected function replaceNodeTokens(array $tokens, array $data, BubbleableMetadata $bubbleable_metadata): array {
    $replacements = [];
    $node = $data['node'] ?? NULL;
    if (!$node instanceof ContentEntityInterface) {
      return $replacements;
    }

    $bubbleable_metadata->addCacheableDependency($node);
    $clinical_trials_gov_tokens = $this->token->findWithPrefix($tokens, 'clinical-trials-gov');
    if (!$clinical_trials_gov_tokens) {
      return $replacements;
    }

    $normalized_tokens = [];
    foreach ($clinical_trials_gov_tokens as $identifier => $original) {
      $replacements[$original] = '';
      $normalized_tokens[$this->normalizeIdentifier((string) $identifier)][] = $original;
    }

    $nct_id = $this->getNodeNctId($node);
    if (!$nct_id) {
      return $replacements;
    }

    $study = $this->studyManager->getStudy($nct_id);
    $metadata = $this->studyManager->getMetadataByPath();

    foreach ($metadata as $path => $item) {
      $normalized_path = $this->normalizeIdentifier($path);
      $normalized_piece = $this->normalizeIdentifier($item['piece'] ?? '');

      if (!isset($normalized_tokens[$normalized_path])
        && !isset($normalized_tokens[$normalized_piece])) {
        continue;
      }

      $value = $study[$path] ?? NULL;
      $replacement = $this->stringifyValue($value);

      if (isset($normalized_tokens[$normalized_path])) {
        foreach ($normalized_tokens[$normalized_path] as $original) {
          $replacements[$original] = $replacement;
        }
      }

      if (isset($normalized_tokens[$normalized_piece])) {
        foreach ($normalized_tokens[$normalized_piece] as $original) {
          $replacements[$original] = $replacement;
        }
      }
    }

    return $replacements;
  }

  /**
   * Returns the configured/generated NCT ID value from a node.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $node
   *   The node being inspected.
   *
   * @return string|null
   *   The NCT ID value when available, or an empty string when the field does
   *   not exist.
   */
  protected function getNodeNctId(ContentEntityInterface $node): ?string {
    $field_name = $this->entityManager->generateFieldName(self::NCT_ID_PATH);
    return $node->hasField($field_name)
      ? $node->get($field_name)->value
      : '';
  }

  /**
   * Normalizes a token identifier for case-insensitive comparisons.
   *
   * @param string $identifier
   *   The identifier to normalize.
   *
   * @return string
   *   The lowercase identifier without hyphen or underscore delimiters.
   */
  protected function normalizeIdentifier(string $identifier): string {
    return strtolower(str_replace(['-', '_'], '', $identifier));
  }

  /**
   * Converts a study value into a token-safe string.
   *
   * @param mixed $value
   *   The value to convert into a token replacement string.
   *
   * @return string
   *   The scalar string value or JSON-encoded data.
   */
  protected function stringifyValue(mixed $value): string {
    if (is_null($value)) {
      return '';
    }

    if (is_bool($value)) {
      return $value ? 'true' : 'false';
    }

    if (is_scalar($value)) {
      return (string) $value;
    }

    $encoded = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    return is_string($encoded) ? $encoded : '';
  }

  /**
   * Builds the token description for dynamic API value lookups.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The token description with example token formats.
   */
  protected function buildApiTokenDescription(): TranslatableMarkup {
    if ($this->moduleHandler->moduleExists('clinical_trials_gov_report')) {
      return $this->t('Return a ClinicalTrials.gov API value by metadata path or piece using tokens like <code>[node:clinical-trials-gov:BriefTitle]</code> or <code>[node:clinical-trials-gov:brief-title]</code> or <code>[node:clinical-trials-gov:brief_title]</code> or <code>[node:clinical-trials-gov:protocolSection.identificationModule.briefTitle]</code>. See the <a href=":url">metadata report</a> for available fields.', [
        ':url' => Url::fromRoute('clinical_trials_gov_report.metadata')->toString(),
      ]);
    }

    return $this->t('Return a ClinicalTrials.gov API value by metadata path or piece using tokens like <code>[node:clinical-trials-gov:BriefTitle]</code> or <code>[node:clinical-trials-gov:brief-title]</code> or <code>[node:clinical-trials-gov:brief_title]</code> or <code>[node:clinical-trials-gov:protocolSection.identificationModule.briefTitle]</code>.');
  }

}
