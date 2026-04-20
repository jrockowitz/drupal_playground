<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld\Traits;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

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
   */
  protected function buildMessages(string|array|MarkupInterface $messages, string $type): array {
    if ($messages instanceof MarkupInterface) {
      $messages = (string) $messages;
    }
    $messages = (array) $messages;

    $type = in_array($type, ['status', 'warning', 'error']) ? $type : 'status';

    return [
      '#theme' => 'status_messages',
      '#message_list' => [
        $type => $messages,
      ],
      '#status_headings' => [
        'status' => $this->t('Status message'),
        'warning' => $this->t('Warning message'),
        'error' => $this->t('Error message'),
      ],
    ];
  }

}
