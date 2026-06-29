<?php

namespace Drupal\term_reference\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\taxonomy\TermInterface;
use Drupal\term_reference\Access\TermReferenceAccessCheck;
use Drupal\term_reference\TermReferenceDiscoveryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Handles the primary References taxonomy term task.
 */
class TermReferenceOverviewController extends ControllerBase {

  /**
   * Constructs a TermReferenceOverviewController object.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   * @param \Drupal\term_reference\TermReferenceDiscoveryInterface $termReferenceDiscovery
   *   The term reference discovery service.
   * @param \Drupal\term_reference\Access\TermReferenceAccessCheck $termReferenceAccessCheck
   *   The term reference access checker.
   */
  public function __construct(
    protected AccountInterface $account,
    protected TermReferenceDiscoveryInterface $termReferenceDiscovery,
    protected TermReferenceAccessCheck $termReferenceAccessCheck,
  ) {}

  /**
   * Redirects the primary task to the first accessible reference field.
   *
   * @param \Drupal\taxonomy\TermInterface $taxonomy_term
   *   The taxonomy term.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|array
   *   A redirect response or an empty-state render array.
   */
  public function overview(TermInterface $taxonomy_term): RedirectResponse|array {
    foreach ($this->termReferenceDiscovery->getReferenceFieldsForVocabulary($taxonomy_term->bundle()) as $field) {
      $access = $this->termReferenceAccessCheck->fieldAccess($this->account, $taxonomy_term, $field);
      if (!$access->isAllowed()) {
        continue;
      }
      $url = Url::fromRoute('term_reference.reference', [
        'taxonomy_term' => $taxonomy_term->id(),
        'reference_field' => $field['id'],
      ]);
      return new RedirectResponse($url->toString());
    }

    return [
      '#markup' => $this->t('No reference fields are available for this term.'),
    ];
  }

  /**
   * Gets the term reference page title.
   *
   * @param \Drupal\taxonomy\TermInterface $taxonomy_term
   *   The taxonomy term.
   *
   * @return string
   *   The term label.
   */
  public function title(TermInterface $taxonomy_term): string {
    return $taxonomy_term->label();
  }

}
