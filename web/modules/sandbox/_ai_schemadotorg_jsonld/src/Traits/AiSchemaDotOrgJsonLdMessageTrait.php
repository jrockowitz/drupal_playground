<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld\Traits;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides theme-native inline status messages.
 */
trait AiSchemaDotOrgJsonLdMessageTrait {

  use StringTranslationTrait;

  /**
   * Builds a theme-native inline message render array.
   *
   * @param string|array|\Drupal\Component\Render\MarkupInterface $messages
   *   The message text, markup, or list of messages.
   * @param string $type
   *   The message type.
   * @param string|\Drupal\Component\Render\MarkupInterface $heading
   * @param string|\Drupal\Component\Render\MarkupInterface|null $heading
   *   The optional custom message heading.
   */
  protected function buildMessages(string|array|MarkupInterface $messages, string $type, string|TranslatableMarkup|null $heading = NULL): array {
    if ($messages instanceof MarkupInterface) {
      $messages = (string) $messages;
    }
    $messages = (array) $messages;

    $type = match ($type) {
      'info',
      'status',
      'warning',
      'error' => $type,
      default => 'status',
    };

    $status_headings = [
      'info' => $this->t('Information message'),
      'status' => $this->t('Status message'),
      'warning' => $this->t('Warning message'),
      'error' => $this->t('Error message'),
    ];

    return [
      '#theme' => 'status_messages',
      '#message_list' => [
        $type => $messages,
      ],
      '#status_headings' => [
        $type => $heading ?? $status_headings[$type],
      ],
    ];
  }

}
