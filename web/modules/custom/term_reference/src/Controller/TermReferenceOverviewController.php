<?php

namespace Drupal\term_reference\Controller;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\taxonomy\TermInterface;
use Drupal\term_reference\TermReferenceAccessInterface;
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
   * @param \Drupal\term_reference\TermReferenceAccessInterface $termReferenceAccess
   *   The term reference access service.
   */
  public function __construct(
    protected AccountInterface $account,
    protected TermReferenceDiscoveryInterface $termReferenceDiscovery,
    protected TermReferenceAccessInterface $termReferenceAccess,
  ) {}

  /**
   * Checks access to the primary References task.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current account.
   * @param \Drupal\taxonomy\TermInterface $taxonomy_term
   *   The taxonomy term.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, TermInterface $taxonomy_term): AccessResultInterface {
    return $this->termReferenceAccess->overviewAccess($account, $taxonomy_term);
  }

  /**
   * Redirects the primary task to the first accessible field.
   *
   * @param \Drupal\taxonomy\TermInterface $taxonomy_term
   *   The taxonomy term.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|array
   *   A redirect response or an empty-state render array.
   */
  public function overview(TermInterface $taxonomy_term): RedirectResponse|array {
    foreach ($this->termReferenceDiscovery->getFieldsForVocabulary($taxonomy_term->bundle()) as $field) {
      $access = $this->termReferenceAccess->fieldAccess($this->account, $taxonomy_term, $field);
      if (!$access->isAllowed()) {
        continue;
      }
      $url = Url::fromRoute('term_reference.reference', [
        'taxonomy_term' => $taxonomy_term->id(),
        'field' => $field['id'],
      ]);
      return new RedirectResponse($url->toString());
    }

    return [
      '#markup' => $this->t('No fields are available for this term.'),
    ];
  }

  /**
   * Gets the term reference page title.
   *
   * @param \Drupal\taxonomy\TermInterface $taxonomy_term
   *   The taxonomy term.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The term reference page title.
   */
  public function title(TermInterface $taxonomy_term): TranslatableMarkup {
    return $this->t('Add references to %term', [
      '%term' => $taxonomy_term->label(),
    ]);
  }

}
