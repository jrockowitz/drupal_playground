<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld_log\EventSubscriber;

use Drupal\ai\Event\PostGenerateResponseEvent;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai_schemadotorg_jsonld\Traits\AiSchemaDotOrgJsonLdAutomatorTrait;
use Drupal\ai_schemadotorg_jsonld_log\AiSchemaDotOrgJsonLdLogStorageInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Logs prompt and response pairs for AI Schema.org JSON-LD requests.
 */
class AiSchemaDotOrgJsonLdLogEventSubscriber implements EventSubscriberInterface {

  use AiSchemaDotOrgJsonLdAutomatorTrait;

  /**
   * Constructs an AiSchemaDotOrgJsonLdLogEventSubscriber object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\ai_schemadotorg_jsonld_log\AiSchemaDotOrgJsonLdLogStorageInterface $logStorage
   *   The log storage.
   */
  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly AiSchemaDotOrgJsonLdLogStorageInterface $logStorage,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PostGenerateResponseEvent::EVENT_NAME => 'onPostGenerateResponse',
    ];
  }

  /**
   * Logs prompt and response data for JSON-LD automator requests.
   *
   * @param \Drupal\ai\Event\PostGenerateResponseEvent $event
   *   The AI response event.
   */
  public function onPostGenerateResponse(PostGenerateResponseEvent $event): void {
    if (!$this->configFactory->get('ai_schemadotorg_jsonld_log.settings')->get('enable')) {
      return;
    }
    if (!$this->hasTags($event->getTags())) {
      return;
    }

    $entity_type_id = $this->extractTagValue($event->getTags(), 'ai_automator:entity_type:');
    $entity_id = $this->extractTagValue($event->getTags(), 'ai_automator:entity:');
    $entity = $this->loadEntity($entity_type_id, $entity_id);

    $response = $this->extractResponse($event);
    $this->logStorage->insert([
      'entity_type' => $entity_type_id,
      'entity_id' => $entity_id,
      'entity_label' => ($entity && $entity->label()) ? (string) $entity->label() : '',
      'bundle' => ($entity) ? $entity->bundle() : '',
      'url' => $this->buildCanonicalUrl($entity),
      'prompt' => $this->extractPrompt($event),
      'response' => $response,
      'valid' => $this->isValidJson($response) ? 1 : 0,
    ]);
  }

  /**
   * Extracts the prompt string from the event input.
   */
  protected function extractPrompt(PostGenerateResponseEvent $event): string {
    $input = $event->getInput();
    if (is_string($input)) {
      return $input;
    }
    if ($input instanceof ChatInput) {
      $messages = $input->getMessages();
      $message = reset($messages);
      return ($message instanceof ChatMessage) ? $message->getText() : '';
    }
    return '';
  }

  /**
   * Extracts the response string from the event output.
   */
  protected function extractResponse(PostGenerateResponseEvent $event): string {
    $normalized_output = $event->getOutput()->getNormalized();
    return ($normalized_output instanceof ChatMessage)
      ? $normalized_output->getText()
      : print_r($normalized_output, TRUE);
  }

  /**
   * Determines whether the response contains valid JSON.
   *
   * @param string $response
   *   The response text.
   *
   * @return bool
   *   TRUE if the response is valid JSON, FALSE otherwise.
   */
  protected function isValidJson(string $response): bool {
    try {
      json_decode($response, TRUE, 512, JSON_THROW_ON_ERROR);
      return TRUE;
    }
    catch (\JsonException) {
      return FALSE;
    }
  }

  /**
   * Extracts a tag value by prefix.
   *
   * @param array $tags
   *   The request tags.
   * @param string $prefix
   *   The tag prefix.
   */
  protected function extractTagValue(array $tags, string $prefix): string {
    foreach ($tags as $tag) {
      if (str_starts_with($tag, $prefix)) {
        return substr($tag, strlen($prefix));
      }
    }
    return '';
  }

  /**
   * Loads the tagged entity when possible.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $entity_id
   *   The entity ID.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The loaded entity, or NULL if unavailable.
   */
  protected function loadEntity(string $entity_type_id, string $entity_id): ?EntityInterface {
    if (!$entity_type_id || !$entity_id || !$this->entityTypeManager->hasDefinition($entity_type_id)) {
      return NULL;
    }

    try {
      $entity = $this->entityTypeManager
        ->getStorage($entity_type_id)
        ->load($entity_id);
      return ($entity instanceof EntityInterface) ? $entity : NULL;
    }
    catch (\Throwable) {
      return NULL;
    }
  }

  /**
   * Builds an absolute canonical URL when available.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   The loaded entity.
   */
  protected function buildCanonicalUrl(?EntityInterface $entity): string {
    if (!$entity || !$entity->getEntityType()->hasLinkTemplate('canonical')) {
      return '';
    }

    try {
      return $entity->toUrl('canonical')->setAbsolute()->toString();
    }
    catch (\Throwable) {
      return '';
    }
  }

}
