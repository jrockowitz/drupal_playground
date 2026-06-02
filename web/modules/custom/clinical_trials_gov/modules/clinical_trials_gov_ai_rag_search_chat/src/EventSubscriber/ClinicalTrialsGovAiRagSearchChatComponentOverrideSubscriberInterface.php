<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov_ai_rag_search_chat\EventSubscriber;

use Symfony\Component\HttpKernel\Event\ViewEvent;

/**
 * Defines the component override subscriber contract.
 */
interface ClinicalTrialsGovAiRagSearchChatComponentOverrideSubscriberInterface {

  /**
   * Alters the AI RAG search chat render array before rendering.
   *
   * @param \Symfony\Component\HttpKernel\Event\ViewEvent $event
   *   The view event.
   */
  public function onKernelView(ViewEvent $event): void;

}
