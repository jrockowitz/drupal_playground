<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Hook;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Serialization\Yaml;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Custom field hook implementations for the ClinicalTrials.gov module.
 */
class ClinicalTrialsGovCustomFieldHooks {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Implements hook_preprocess_custom_field_item().
   */
  #[Hook('preprocess_custom_field_item')]
  public function preprocessCustomFieldItem(array &$variables): void {
    $field_name = (string) ($variables['elements']['#field_name'] ?? '');
    $label = (string) ($variables['label'] ?? '');
    if (!$this->matchesClinicalTrialsGovFieldName($field_name) || !$this->isYamlLabel($label)) {
      return;
    }

    $value = $variables['value'];
    if (is_array($value) && isset($value['#markup']) && is_scalar($value['#markup'])) {
      $value = (string) $value['#markup'];
    }

    if (!is_scalar($value)) {
      return;
    }

    $variables['value'] = [
      '#type' => 'html_tag',
      '#tag' => 'pre',
      '#value' => Html::escape((string) $value),
    ];
  }

  /**
   * Implements hook_field_widget_single_element_form_alter().
   */
  #[Hook('field_widget_single_element_form_alter')]
  public function fieldWidgetSingleElementFormAlter(array &$element, FormStateInterface $form_state, array $context): void {
    $items = $context['items'] ?? NULL;
    if (!$items instanceof FieldItemListInterface) {
      return;
    }

    if (!$this->matchesClinicalTrialsGovFieldName($items->getName())) {
      return;
    }

    $this->attachYamlValidationCallbacks($element);
  }

  /**
   * Validates one YAML-backed widget element.
   */
  public static function validateYamlElement(array &$element, FormStateInterface $form_state, array &$complete_form = []): void {
    $value = $element['#value'] ?? '';
    if (!is_scalar($value) || trim((string) $value) === '') {
      return;
    }

    try {
      Yaml::decode((string) $value);
    }
    catch (InvalidDataTypeException) {
      $form_state->setError($element, new TranslatableMarkup('The value must be valid YAML.'));
    }
  }

  /**
   * Attaches YAML validation callbacks to matching widget elements.
   */
  protected function attachYamlValidationCallbacks(array &$element): void {
    $label = (string) ($element['#title'] ?? '');

    if (($element['#type'] ?? NULL) === 'textarea' && $this->isYamlLabel($label)) {
      $element['#element_validate'][] = [static::class, 'validateYamlElement'];
      return;
    }

    if (($element['#type'] ?? NULL) === 'text_format' && $this->isYamlLabel($label) && isset($element['value']) && is_array($element['value'])) {
      $element['value']['#element_validate'][] = [static::class, 'validateYamlElement'];
      return;
    }

    foreach ($element as &$child) {
      if (is_array($child)) {
        $this->attachYamlValidationCallbacks($child);
      }
    }
  }

  /**
   * Determines whether one label identifies a YAML-backed property.
   */
  protected function isYamlLabel(string $label): bool {
    return str_ends_with(trim($label), '(YAML)');
  }

  /**
   * Determines whether one field name belongs to ClinicalTrials.gov.
   */
  protected function matchesClinicalTrialsGovFieldName(string $field_name): bool {
    $prefix = $this->getClinicalTrialsGovFieldPrefix();
    return ($prefix !== '' && str_starts_with($field_name, $prefix));
  }

  /**
   * Returns the configured ClinicalTrials.gov field name prefix.
   */
  protected function getClinicalTrialsGovFieldPrefix(): string {
    $field_prefix = trim((string) ($this->configFactory->get('clinical_trials_gov.settings')->get('field_prefix') ?? ''));
    if ($field_prefix === '') {
      return '';
    }

    return 'field_' . $field_prefix . '_';
  }

}
