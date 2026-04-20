<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld\EventSubscriber;

use Drupal\ai\Event\PreGenerateResponseEvent;
use Drupal\ai\OperationType\Chat\ChatInput;
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
      PreGenerateResponseEvent::EVENT_NAME => 'onPreGenerateResponse',
      ValuesChangeEvent::EVENT_NAME => 'onValuesChange',
    ];
  }

  /**
   * Unescapes HTML entities in the JSON-LD automator prompt before send.
   *
   * @param \Drupal\ai\Event\PreGenerateResponseEvent $event
   *   The AI pre-generate response event.
   */
  public function onPreGenerateResponse(PreGenerateResponseEvent $event): void {
    if (!$this->isJsonLdAutomatorRequest($event->getTags())) {
      return;
    }

    $input = $event->getInput();
    if (!$input instanceof ChatInput) {
      return;
    }

    foreach ($input->getMessages() as $message) {
      if ($message instanceof ChatMessage && $message->getRole() === 'user') {
        $message->setText($this->cleanupPromptText($message->getText()));
      }
    }

    $event->setInput($input);
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
    foreach ($values as $index => $value) {
      $values[$index] = $this->extractJson((string) $value);
    }
    $event->setValues($values);
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

    $json = $this->repairInvalidQuoteEscapes($json);

    try {
      json_decode($json, TRUE, 512, JSON_THROW_ON_ERROR);
      return $json;
    }
    catch (\JsonException $e) {
      $this->loggerFactory->get('ai_schemadotorg_jsonld')
        ->warning('Invalid JSON in AI response: @message', ['@message' => $e->getMessage()]);
      $this->messenger->addWarning(
        $this->t('The AI response contained invalid JSON. The original value has been preserved.')
      );
      return $raw;
    }
  }

  /**
   * Repairs bad quote escapes in extracted JSON before validation.
   *
   * @param string $json
   *   The extracted JSON candidate.
   *
   * @return string
   *   The repaired JSON candidate.
   */
  protected function repairInvalidQuoteEscapes(string $json): string {
    $json = str_replace('\&quot;"', '\"', $json);
    $json = str_replace('"\&quot;', '\"', $json);
    return $json;
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

  /**
   * Cleans up prompt text before it is sent to the LLM.
   *
   * @param string $text
   *   The prompt text.
   *
   * @return string
   *   The cleaned prompt text.
   */
  protected function cleanupPromptText(string $text): string {
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
    $text = preg_replace("/\r\n?/", "\n", $text) ?? $text;
    $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
    return $text;
  }

}
