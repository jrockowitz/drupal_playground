<?php

declare(strict_types=1);

namespace Drupal\ai_devel_cache\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\ConfigTarget;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configures which chat requests bypass the AI Devel Cache.
 */
class AiDevelCacheSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ai_devel_cache_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['ai_devel_cache.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['uncacheable_chat_tags'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Uncacheable chat tags'),
      '#description' => $this->t('Chat requests carrying any of these tags are never cached, so interactive chat UIs keep talking to the live provider. Enter one tag per line. Non-chat operations (embeddings, moderation, image, etc.) are always cached regardless of this list.'),
      '#config_target' => new ConfigTarget(
        'ai_devel_cache.settings',
        'uncacheable_chat_tags',
        fromConfig: static fn (?array $tags): string => implode("\n", $tags ?? []),
        toConfig: static fn (string $value): array => self::parseTagList($value),
      ),
      '#rows' => 6,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Splits a textarea value into a normalized list of tags.
   *
   * @param string $value
   *   The raw textarea value.
   *
   * @return array
   *   A list of unique, non-empty tags preserving submission order.
   */
  protected static function parseTagList(string $value): array {
    $tags = [];
    foreach (preg_split('/\r\n|\r|\n/', $value) ?: [] as $line) {
      $line = trim($line);
      if ($line !== '' && !in_array($line, $tags)) {
        $tags[] = $line;
      }
    }
    return $tags;
  }

}
