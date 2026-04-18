<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\token\TokenEntityMapperInterface;

/**
 * Manages AI Schema.org JSON-LD settings and entity type defaults.
 */
class AiSchemaDotOrgJsonLdManager implements AiSchemaDotOrgJsonLdManagerInterface {

  /**
   * Unsupported entity types.
   */
  protected array $unsupportedEntityTypes = [
    'ai_log',
    'automator_chain',
    'menu_link_content',
    'shortcut',
  ];

  /**
   * Constructs an AiSchemaDotOrgJsonLdManager object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\token\TokenEntityMapperInterface $tokenEntityMapper
   *   The token entity mapper.
   */
  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly TokenEntityMapperInterface $tokenEntityMapper,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getSupportedEntityTypes(): array {
    $supported_entity_types = [];

    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type_definition) {
      if (!$entity_type_definition instanceof ContentEntityTypeInterface) {
        continue;
      }
      if (!$entity_type_definition->hasLinkTemplate('canonical')) {
        continue;
      }
      if (!$entity_type_definition->entityClassImplements(FieldableEntityInterface::class)) {
        continue;
      }
      if (in_array($entity_type_id, $this->unsupportedEntityTypes)) {
        continue;
      }

      $supported_entity_types[$entity_type_id] = $entity_type_definition;
    }

    uasort($supported_entity_types, static fn (ContentEntityTypeInterface $a, ContentEntityTypeInterface $b): int => strnatcasecmp((string) $a->getLabel(), (string) $b->getLabel()));
    return $supported_entity_types;
  }

  /**
   * {@inheritdoc}
   */
  public function syncEntityTypes(array $entity_type_ids): void {
    $config = $this->configFactory->getEditable('ai_schemadotorg_jsonld.settings');
    $entity_type_settings = $config->get('entity_types') ?? [];
    $enabled_entity_type_ids = array_unique($entity_type_ids);

    foreach (array_keys($entity_type_settings) as $entity_type_id) {
      if (in_array($entity_type_id, $enabled_entity_type_ids)) {
        continue;
      }
      if ($this->hasFieldStorage($entity_type_id)) {
        continue;
      }

      unset($entity_type_settings[$entity_type_id]);
    }

    ksort($entity_type_settings);
    $config->set('entity_types', $entity_type_settings)->save();
  }

  /**
   * {@inheritdoc}
   */
  public function addEntityTypes(array $entity_type_ids): void {
    $config = $this->configFactory->getEditable('ai_schemadotorg_jsonld.settings');
    $entity_type_settings = $config->get('entity_types') ?? [];
    $supported_entity_types = $this->getSupportedEntityTypes();

    foreach (array_unique($entity_type_ids) as $entity_type_id) {
      if (isset($entity_type_settings[$entity_type_id])) {
        continue;
      }

      $entity_type = $supported_entity_types[$entity_type_id] ?? NULL;
      if (!$entity_type instanceof ContentEntityTypeInterface) {
        continue;
      }

      $entity_type_settings[$entity_type_id] = [
        'prompt' => $this->buildDefaultPrompt($entity_type_id, $entity_type),
        'default_jsonld' => '',
      ];
    }

    ksort($entity_type_settings);
    $config->set('entity_types', $entity_type_settings)->save();
  }

  /**
   * Builds the default prompt for an entity type.
   *
   * @param string $entity_type_id
   *   The content entity type ID.
   * @param \Drupal\Core\Entity\ContentEntityTypeInterface $entity_type
   *   The content entity type definition.
   */
  protected function buildDefaultPrompt(string $entity_type_id, ContentEntityTypeInterface $entity_type): string {
    $token_name = $this->getTokenName($entity_type_id);
    $bundle_token_name = $this->getBundleTokenName($entity_type_id, $entity_type);
    $label_token_name = $this->getLabelTokenName($entity_type);
    $label = mb_strtolower((string) $entity_type->getLabel());

    $lines = [
      'Generate valid Schema.org JSON-LD for the ' . $label . ' below:',
      '',
      '# Input',
      '',
    ];

    if ($bundle_token_name !== NULL) {
      $lines[] = 'Type: [' . $token_name . ':' . $bundle_token_name . ']';
    }
    $lines[] = 'URL: [' . $token_name . ':url]';
    if ($label_token_name !== NULL) {
      $lines[] = 'Title: [' . $token_name . ':' . $label_token_name . ']';
    }
    $lines[] = '';
    $lines[] = 'Current JSON-LD: (This will be omitted for new content)';
    $lines[] = '[' . $token_name . ':' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME . ']';
    $lines[] = '';
    $lines[] = '# Requirements';
    $lines[] = '';
    $lines[] = '- Return ONLY the JSON-LD object. No explanatory text, no markdown fences, no preamble.';
    $lines[] = '- Output must begin with { and end with }.';
    $lines[] = '- Use only valid Schema.org types and properties (https://schema.org).';
    $lines[] = '- Set @context to "https://schema.org".';
    $lines[] = '- Set url to the canonical URL provided above.';
    $lines[] = '- Choose the most specific applicable @type for the ' . $label . ' given.';
    $lines[] = '- Do not fabricate values or URLs.';

    return implode(PHP_EOL, $lines);
  }

  /**
   * Returns the token name for an entity type.
   *
   * @param string $entity_type_id
   *   The content entity type ID.
   */
  protected function getTokenName(string $entity_type_id): string {
    return (string) $this->tokenEntityMapper->getTokenTypeForEntityType($entity_type_id, TRUE);
  }

  /**
   * Returns the label token name for an entity type.
   *
   * @param \Drupal\Core\Entity\ContentEntityTypeInterface $entity_type
   *   The content entity type definition.
   */
  protected function getLabelTokenName(ContentEntityTypeInterface $entity_type): ?string {
    return $entity_type->getKey('label') ?: NULL;
  }

  /**
   * Returns the bundle token name for an entity type.
   *
   * @param string $entity_type_id
   *   The content entity type ID.
   * @param \Drupal\Core\Entity\ContentEntityTypeInterface $entity_type
   *   The content entity type definition.
   */
  protected function getBundleTokenName(string $entity_type_id, ContentEntityTypeInterface $entity_type): ?string {
    return match ($entity_type_id) {
      'node' => 'content-type',
      'taxonomy_term' => 'vocabulary:name',
      default => $entity_type->getKey('bundle') ?: NULL,
    };
  }

  /**
   * Returns TRUE when the entity type has Schema.org JSON-LD field storage.
   *
   * @param string $entity_type_id
   *   The content entity type ID.
   */
  protected function hasFieldStorage(string $entity_type_id): bool {
    return FieldStorageConfig::loadByName($entity_type_id, AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME) !== NULL;
  }

}
