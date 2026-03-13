<?php

declare(strict_types=1);

namespace Drupal\telephone_filter\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\filter\Attribute\Filter;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\filter\Plugin\FilterInterface;

/**
 * Converts US phone numbers in HTML text to clickable tel: links.
 *
 * Supported formats:
 *   - Numeric:  888-888-8888 | 888.888.8888 | (888) 888-8888
 *   - Vanity:   800-FLOWERS  | 800-ASK-HELP  (uppercase letters only)
 *
 * Numbers already inside <a> tags are never double-wrapped.
 * Only numbers whose area code appears in the configured list are linked.
 * Lowercase or mixed-case vanity segments are not matched.
 */
#[Filter(
  id: 'telephone_filter',
  title: new TranslatableMarkup('Telephone filter'),
  description: new TranslatableMarkup('Converts US phone numbers to clickable tel: links.'),
  type: FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
  settings: [
    "area_codes" => "",
  ],
)]
final class TelephoneFilter extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form['area_codes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Allowed area codes'),
      '#description' => $this->t('Comma-separated 3-digit area codes (e.g. 800, 888). Leave blank to link all phone numbers regardless of area code.'),
      '#default_value' => $this->settings['area_codes'],
      '#maxlength' => 255,
      '#element_validate' => [[static::class, 'validateAreaCodes']],
    ];
    return $form;
  }

  /**
   * Validates the area_codes textfield value.
   *
   * Called via #element_validate so it is invoked by the filter settings form.
   * Each comma-separated token must be exactly 3 digits; blank tokens from
   * leading/trailing/doubled commas are silently skipped.
   *
   * @param array $element
   *   The form element being validated.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  public static function validateAreaCodes(array &$element, FormStateInterface $form_state): void {
    $raw = $element['#value'] ?? '';
    foreach (array_map('trim', explode(',', $raw)) as $code) {
      if ($code === '') {
        continue;
      }
      if (!ctype_digit($code) || strlen($code) !== 3) {
        $form_state->setError(
          $element,
          t('"@code" is not a valid area code. Each value must contain exactly 3 digits (e.g. 800).', ['@code' => $code]),
        );
        return;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode): FilterProcessResult {
    $area_codes = array_values(array_filter(
      array_map('trim', explode(',', $this->settings['area_codes'])),
    ));

    // Pattern: \(?\b(AREA)\)?\s*[-.]?\s*([A-Z0-9]{3})\s*[-.]?\s*([A-Z0-9]{4})\b
    // \(? and \)? make area-code parentheses optional.
    // \b fires between ( and digit, or before a bare digit.
    // No i flag: lowercase vanity letters do not match.
    // Empty area_codes: a bare [0-9]{3} group matches any area code.
    if ($area_codes !== []) {
      $alternation = implode('|', array_map('preg_quote', $area_codes, array_fill(0, count($area_codes), '/')));
      $pattern = '/\(?\b(' . $alternation . ')\)?\s*[-.]?\s*([A-Z0-9]{3})\s*[-.]?\s*([A-Z0-9]{4})\b/';
    }
    else {
      // No restriction — match any 3-digit area code.
      $pattern = '/\(?\b([0-9]{3})\)?\s*[-.]?\s*([A-Z0-9]{3})\s*[-.]?\s*([A-Z0-9]{4})\b/';
    }

    $document = Html::load($text);
    $this->processNode($document, $document, $pattern);

    return new FilterProcessResult(Html::serialize($document));
  }

  /**
   * Recursively processes DOMText nodes, replacing phone matches with anchors.
   */
  private function processNode(\DOMDocument $document, \DOMNode $node, string $pattern): void {
    // Collect children first; modifying the tree while iterating causes skips.
    /** @var list<\DOMNode> $children */
    $children = [];
    foreach ($node->childNodes as $child) {
      $children[] = $child;
    }

    foreach ($children as $child) {
      if ($child instanceof \DOMText) {
        if (!$this->hasAnchorAncestor($child)) {
          $this->replacePhoneNumbers($document, $child, $pattern);
        }
      }
      elseif ($child->hasChildNodes()) {
        $this->processNode($document, $child, $pattern);
      }
    }
  }

  /**
   * Returns TRUE if the given node has an <a> element anywhere in its ancestry.
   */
  private function hasAnchorAncestor(\DOMNode $node): bool {
    $parent = $node->parentNode;
    while ($parent !== NULL) {
      if ($parent instanceof \DOMElement && strtolower($parent->tagName) === 'a') {
        return TRUE;
      }
      $parent = $parent->parentNode;
    }
    return FALSE;
  }

  /**
   * Splits a DOMText node around phone-number matches, inserting <a> elements.
   */
  private function replacePhoneNumbers(\DOMDocument $document, \DOMText $text_node, string $pattern): void {
    $value = $text_node->nodeValue ?? '';

    // Guard: most text nodes contain no phone number. The cheap preg_match()
    // check avoids the overhead of PREG_OFFSET_CAPTURE for the common case.
    // preg_match_all() is only called when at least one match is confirmed.
    if (!preg_match($pattern, $value)) {
      return;
    }

    $parent = $text_node->parentNode;
    if ($parent === NULL) {
      return;
    }

    $offset = 0;
    $fragment = $document->createDocumentFragment();

    preg_match_all($pattern, $value, $matches, PREG_OFFSET_CAPTURE);

    foreach ($matches[0] as $index => $match) {
      [$matched_text, $match_offset] = $match;

      // Text before the match.
      if ($match_offset > $offset) {
        $fragment->appendChild(
          $document->createTextNode(substr($value, $offset, $match_offset - $offset))
        );
      }

      // Build the digit-only phone number for the tel: href.
      $area       = $this->vanityToDigits($matches[1][$index][0]);
      $exchange   = $this->vanityToDigits($matches[2][$index][0]);
      $subscriber = $this->vanityToDigits($matches[3][$index][0]);
      $digits     = $area . $exchange . $subscriber;

      // createElement() does not encode its second argument, so the matched
      // text is appended as a safe DOMText child node instead.
      $anchor = $document->createElement('a');
      $anchor->setAttribute('href', 'tel:+1' . $digits);
      $anchor->appendChild($document->createTextNode($matched_text));
      $fragment->appendChild($anchor);

      $offset = $match_offset + strlen($matched_text);
    }

    // Remaining text after the last match.
    if ($offset < strlen($value)) {
      $fragment->appendChild(
        $document->createTextNode(substr($value, $offset))
      );
    }

    $parent->replaceChild($fragment, $text_node);
  }

  /**
   * Converts vanity letters in a phone number segment to their keypad digits.
   *
   * Digits pass through unchanged. Letters are mapped per the standard
   * telephone keypad layout:
   *   ABC=2, DEF=3, GHI=4, JKL=5, MNO=6, PQRS=7, TUV=8, WXYZ=9
   *
   * @param string $number
   *   A single phone segment (area code, exchange, or subscriber).
   *
   * @return string
   *   The segment with all letters converted to digits.
   */
  private function vanityToDigits(string $number): string {
    return strtr(strtoupper($number), [
      'A' => '2',
      'B' => '2',
      'C' => '2',
      'D' => '3',
      'E' => '3',
      'F' => '3',
      'G' => '4',
      'H' => '4',
      'I' => '4',
      'J' => '5',
      'K' => '5',
      'L' => '5',
      'M' => '6',
      'N' => '6',
      'O' => '6',
      'P' => '7',
      'Q' => '7',
      'R' => '7',
      'S' => '7',
      'T' => '8',
      'U' => '8',
      'V' => '8',
      'W' => '9',
      'X' => '9',
      'Y' => '9',
      'Z' => '9',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE): string {
    return (string) $this->t('US phone numbers with allowed area codes are automatically converted to clickable telephone links.');
  }

}
