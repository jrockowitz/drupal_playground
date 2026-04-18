<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld\EventSubscriber;

use Drupal\ai\Event\PostGenerateResponseEvent;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai_automators\Event\ValuesChangeEvent;
use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Extracts and validates JSON from AI automator responses for the JSON-LD field.
 */
class AiSchemaDotOrgJsonLdEventSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Constructs an AiSchemaDotOrgJsonLdEventSubscriber object.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger channel factory.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $stringTranslation
   *   The string translation service.
   */
  public function __construct(
    protected readonly MessengerInterface $messenger,
    protected readonly LoggerChannelFactoryInterface $loggerFactory,
    TranslationInterface $stringTranslation,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PostGenerateResponseEvent::EVENT_NAME => 'onPostGenerateResponse',
      ValuesChangeEvent::EVENT_NAME => 'onValuesChange',
    ];
  }

  /**
   * Logs the raw AI response for the Schema.org JSON-LD automator.
   *
   * @param \Drupal\ai\Event\PostGenerateResponseEvent $event
   *   The AI post-generate response event.
   */
  public function onPostGenerateResponse(PostGenerateResponseEvent $event): void {
    if (!$this->isJsonLdAutomatorRequest($event->getTags())) {
      return;
    }

    $output = $event->getOutput();
    $normalized_output = $output->getNormalized();
    $response_text = ($normalized_output instanceof ChatMessage)
      ? $normalized_output->getText()
      : print_r($normalized_output, TRUE);

    $this->loggerFactory->get('ai_schemadotorg_jsonld')->notice(
      'Raw AI response before JSON-LD validation. Request ID: @request_id. Response: @response. Raw output: @raw_output',
      [
        '@request_id' => $event->getRequestThreadId(),
        '@response' => $response_text,
        '@raw_output' => print_r($output->getRawOutput(), TRUE),
      ],
    );
  }

  /**
   * Cleans the AI response value for the JSON-LD field.
   *
   * @param \Drupal\ai_automators\Event\ValuesChangeEvent $event
   *   The values change event.
   */
  public function onValuesChange(ValuesChangeEvent $event): void {
    if ($event->getFieldDefinition()->getName() !== AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME) {
      return;
    }

    $values = $event->getValues();
    $processed = [];
    foreach ($values as $value) {
      $processed[] = $this->extractJson((string) $value);
    }

    $event->setValues($processed);
  }

  /**
   * Extracts and validates a JSON object from a raw string.
   *
   * @param string $raw
   *   The raw string from the AI response.
   *
   * @return string
   *   The extracted JSON string, or empty string on failure.
   */
  protected function extractJson(string $raw): string {
    $raw = trim($raw);

    if ($raw === '') {
      return '';
    }

    if (str_starts_with($raw, '{') && str_ends_with($raw, '}')) {
      $json = $raw;
    }
    else {
      $start = strpos($raw, '{');
      $end = strrpos($raw, '}');

      if ($start === FALSE || $end === FALSE) {
        $this->loggerFactory->get('ai_schemadotorg_jsonld')
          ->warning('Could not find JSON object boundaries in AI response.');
        $this->messenger->addWarning(
          $this->t('The AI response did not contain a valid JSON object. The field has been left empty.')
        );
        return '';
      }

      $json = substr($raw, $start, $end - $start + 1);
    }

    try {
      json_decode($json, TRUE, 512, JSON_THROW_ON_ERROR);
      return $json;
    }
    catch (\JsonException $e) {
      $this->loggerFactory->get('ai_schemadotorg_jsonld')
        ->warning('Invalid JSON in AI response: @message', ['@message' => $e->getMessage()]);
      $this->messenger->addWarning(
        $this->t('The AI response contained invalid JSON. The field has been left empty.')
      );
      return '';
    }
  }

  /**
   * Returns TRUE when the tags belong to this module's JSON-LD automator.
   *
   * @param array $tags
   *   The request tags.
   */
  protected function isJsonLdAutomatorRequest(array $tags): bool {
    return in_array('ai_automator', $tags)
      && in_array('ai_automator:field_name:' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME, $tags);
  }

}
