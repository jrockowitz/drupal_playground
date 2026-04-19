<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld_log\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for the AI Schema.org JSON-LD log module.
 */
class AiSchemaDotOrgJsonLdLogFormHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_form_ai_schemadotorg_jsonld_settings_alter().
   */
  #[Hook('form_ai_schemadotorg_jsonld_settings_alter')]
  public function formAiSchemaDotOrgJsonLdSettingsAlter(array &$form, FormStateInterface $form_state): void {
    $form['development']['log'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable prompt and response logging'),
      '#description' => $this->t('Store generated prompts and AI responses in the log table for debugging Schema.org JSON-LD generation.'),
      '#config_target' => 'ai_schemadotorg_jsonld_log.settings:enable',
    ];
  }

}
