<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld\Hook;

use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdTokenResolverInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\token\TokenEntityMapperInterface;

/**
 * Token hook implementations for the AI Schema.org JSON-LD module.
 */
class AiSchemaDotOrgJsonLdTokenHooks {

  use StringTranslationTrait;

  /**
   * Constructs an AiSchemaDotOrgJsonLdTokenHooks object.
   *
   * @param \Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdTokenResolverInterface $tokenResolver
   *   The token resolver service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\token\TokenEntityMapperInterface $tokenEntityMapper
   *   The token entity mapper.
   */
  public function __construct(
    protected readonly AiSchemaDotOrgJsonLdTokenResolverInterface $tokenResolver,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly TokenEntityMapperInterface $tokenEntityMapper,
  ) {}

  /**
   * Implements hook_token_info().
   */
  #[Hook('token_info')]
  public function tokenInfo(): array {
    $tokens = [];
    $entity_type_settings = $this->configFactory->get('ai_schemadotorg_jsonld.settings')->get('entity_types') ?? [];

    foreach (array_keys($entity_type_settings) as $entity_type_id) {
      $token_type = $this->tokenEntityMapper->getTokenTypeForEntityType($entity_type_id, TRUE);
      $tokens[$token_type] = [
        'ai_schemadotorg_jsonld:content' => [
          'name' => $this->t('AI Schema.org JSON-LD: Full content'),
          'description' => $this->t('Renders the entity as the anonymous user in the site default theme for use in AI prompts.'),
        ],
      ];
    }

    return ['tokens' => $tokens];
  }

  /**
   * Implements hook_tokens().
   */
  #[Hook('tokens')]
  public function tokens(string $type, array $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata): array {
    $replacements = [];
    $entity_type_id = $this->tokenEntityMapper->getEntityTypeForTokenType($type);
    $entity_type_settings = $this->configFactory->get('ai_schemadotorg_jsonld.settings')->get('entity_types') ?? [];

    if (!isset($entity_type_settings[$entity_type_id]) || empty($data[$type])) {
      return $replacements;
    }

    $entity = $data[$type];
    if (!$entity instanceof ContentEntityInterface) {
      return $replacements;
    }

    foreach ($tokens as $name => $original) {
      if ($name === 'ai_schemadotorg_jsonld:content') {
        $replacements[$original] = $this->tokenResolver->resolve($entity);
      }
    }

    return $replacements;
  }

}
