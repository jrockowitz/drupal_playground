<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld\EventSubscriber;

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
      ValuesChangeEvent::EVENT_NAME => 'onValuesChange',
    ];
  }

  /**
   * Cleans the AI response value for the JSON-LD field.
   */
  public function onValuesChange(ValuesChangeEvent $event): void {
    if ($event->getFieldDefinition()->getName() !== AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME) {
      return;
    }

    $values = $event->getValues();
    $processed = [];
    foreach ($values as $value) {
      $raw = trim($value['value'] ?? '');
      $processed[] = ['value' => $this->extractJson($raw)];
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

}
