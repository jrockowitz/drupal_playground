/**
 * @file
 * Enhances dynamic ClinicalTrials.gov AI RAG search chat text.
 */

/**
 * Registers ClinicalTrials.gov AI RAG search chat behaviors.
 *
 * @param {Drupal} Drupal
 *   The Drupal behavior registry.
 * @param {Function} once
 *   The Drupal once helper.
 */
(function clinicalTrialsGovAiRagSearchChatBehavior(Drupal, once) {
  'use strict';

  /**
   * Replaces dynamic text produced by the contrib chat JavaScript.
   *
   * @param {HTMLElement} element
   *   The text element.
   */
  function replaceDynamicText(element) {
    const replacements = {
      'Step 1 of 2: Searching knowledge base...': Drupal.t('Step 1 of 2: Searching indexed clinical trials...'),
      'Step 2 of 2: Generating response...': Drupal.t('Step 2 of 2: Reviewing trial details...'),
      'Processing your message...': Drupal.t('Looking for matching trials...'),
      'Load More': Drupal.t('Load more searches'),
      'Loading...': Drupal.t('Loading searches...'),
    };

    const replacement = replacements[element.textContent.trim()];
    if (replacement) {
      element.textContent = replacement;
    }
  }

  /**
   * Replaces dynamic ARIA labels produced by the contrib chat JavaScript.
   *
   * @param {HTMLElement} element
   *   The element with an ARIA label.
   */
  function replaceDynamicAriaLabel(element) {
    const replacements = {
      'Open chat sessions sidebar': Drupal.t('Open trial search history'),
      'Close chat sessions sidebar': Drupal.t('Close trial search history'),
    };

    const label = element.getAttribute('aria-label');
    const replacement = replacements[label];
    if (replacement) {
      element.setAttribute('aria-label', replacement);
    }
  }

  Drupal.behaviors.clinicalTrialsGovAiRagSearchChat = {
    /**
     * Enhances dynamic chat labels.
     *
     * @param {HTMLElement} context
     *   The behavior attachment context.
     */
    attach(context) {
      once(
        'clinical-trials-gov-ai-rag-search-chat-dynamic-text',
        '#chat-loading-text, .load-more-button',
        context
      ).forEach((element) => {
        replaceDynamicText(element);

        const observer = new MutationObserver(() => {
          replaceDynamicText(element);
        });
        observer.observe(element, {
          childList: true,
          characterData: true,
          subtree: true,
        });
      });

      once(
        'clinical-trials-gov-ai-rag-search-chat-dynamic-aria',
        '.sidebar-toggle',
        context
      ).forEach((element) => {
        replaceDynamicAriaLabel(element);

        const observer = new MutationObserver(() => {
          replaceDynamicAriaLabel(element);
        });
        observer.observe(element, {
          attributes: true,
          attributeFilter: ['aria-label'],
        });
      });
    },
  };
})(Drupal, once);
