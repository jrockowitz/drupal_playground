<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov_ai_rag_search_chat\EventSubscriber;

use Drupal\Core\Render\Component\Exception\ComponentNotFoundException;
use Drupal\Core\Theme\ComponentPluginManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Swaps the contrib chat SDC for the ClinicalTrials.gov chat SDC.
 */
class ClinicalTrialsGovAiRagSearchChatComponentOverrideSubscriber implements EventSubscriberInterface, ClinicalTrialsGovAiRagSearchChatComponentOverrideSubscriberInterface {

  /**
   * Constructs a ClinicalTrialsGovAiRagSearchChatComponentOverrideSubscriber.
   *
   * @param \Drupal\Core\Theme\ComponentPluginManager $componentPluginManager
   *   The component plugin manager.
   */
  public function __construct(
    protected ComponentPluginManager $componentPluginManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::VIEW => ['onKernelView', 50],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function onKernelView(ViewEvent $event): void {
    if ($event->getRequestType() !== HttpKernelInterface::MAIN_REQUEST) {
      return;
    }

    if ($event->getRequest()->attributes->get('_route') !== 'ai_rag_search_chat.ai_chat') {
      return;
    }

    $controllerResult = $event->getControllerResult();
    if (!is_array($controllerResult)) {
      return;
    }

    $this->replaceChatLayoutComponent($controllerResult);
    $controllerResult['#attached']['library'][] = 'clinical_trials_gov_ai_rag_search_chat/chat';
    $event->setControllerResult($controllerResult);
  }

  /**
   * Gets the ClinicalTrials.gov component ID for a contrib component ID.
   *
   * @param string $componentId
   *   The contrib component ID.
   *
   * @return string
   *   The ClinicalTrials.gov component ID.
   */
  protected function getClinicalTrialsGovComponentId(string $componentId): string {
    return str_replace(
      'ai_rag_search_chat:',
      'clinical_trials_gov_ai_rag_search_chat:',
      $componentId
    );
  }

  /**
   * Replaces the contrib chat layout component in a render array.
   *
   * @param array $build
   *   The render array to inspect.
   */
  protected function replaceChatLayoutComponent(array &$build): void {
    if (($build['#type'] ?? NULL) === 'component'
      && ($build['#component'] ?? NULL) === 'ai_rag_search_chat:chat-layout') {
      $componentId = $this->getClinicalTrialsGovComponentId($build['#component']);
      if (!$this->hasComponent($componentId)) {
        return;
      }
      $build['#component'] = $componentId;
      return;
    }

    foreach ($build as &$child) {
      if (is_array($child)) {
        $this->replaceChatLayoutComponent($child);
      }
    }
  }

  /**
   * Checks whether an SDC component can be discovered.
   *
   * @param string $componentId
   *   The component ID.
   *
   * @return bool
   *   TRUE when the component can be discovered.
   */
  protected function hasComponent(string $componentId): bool {
    try {
      $this->componentPluginManager->find($componentId);
      return TRUE;
    }
    catch (ComponentNotFoundException) {
      return FALSE;
    }
  }

}
