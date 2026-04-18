<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Theme\ThemeInitializationInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Renders a content entity as the anonymous user for use in AI prompts.
 */
class AiSchemaDotOrgJsonLdTokenResolver implements AiSchemaDotOrgJsonLdTokenResolverInterface {

  /**
   * Constructs an AiSchemaDotOrgJsonLdTokenResolver object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\Core\Session\AccountSwitcherInterface $accountSwitcher
   *   The account switcher.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $themeManager
   *   The theme manager.
   * @param \Drupal\Core\Theme\ThemeInitializationInterface $themeInitialization
   *   The theme initialization service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly RendererInterface $renderer,
    protected readonly RequestStack $requestStack,
    protected readonly AccountSwitcherInterface $accountSwitcher,
    protected readonly ThemeManagerInterface $themeManager,
    protected readonly ThemeInitializationInterface $themeInitialization,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function resolve(ContentEntityInterface $entity): string {
    // Switch to anonymous user.
    $this->accountSwitcher->switchTo(new AnonymousUserSession());

    // Switch to site default theme.
    $default_theme = $this->configFactory->get('system.theme')->get('default');
    $active_theme = $this->themeInitialization->initTheme($default_theme);
    $original_theme = $this->themeManager->getActiveTheme();
    $this->themeManager->setActiveTheme($active_theme);

    try {
      $view_builder = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId());
      $build = $view_builder->view($entity, 'default');
      $html = (string) $this->renderer->renderInIsolation($build);
    }
    finally {
      // Always restore account and theme.
      $this->accountSwitcher->switchBack();
      $this->themeManager->setActiveTheme($original_theme);
    }

    return $this->postProcess($html);
  }

  /**
   * Post-processes the rendered HTML for LLM consumption.
   *
   * - Strips outer wrapping <div><div>...</div></div> pairs (single child only).
   * - Converts root-relative href and src attributes to absolute URLs.
   * - Preserves semantic markup.
   *
   * @param string $html
   *   The raw rendered HTML.
   *
   * @return string
   *   The post-processed HTML.
   */
  protected function postProcess(string $html): string {
    $html = $this->stripOuterWrappingDivs($html);
    $html = $this->absolutizeUrls($html);
    return $html;
  }

  /**
   * Recursively removes outer <div><div>...</div></div> wrapper pairs.
   *
   * Each pass removes one level of outer <div> whose only direct child is
   * another <div>. Repeats until no more single-child wrappers remain at
   * the outermost level. Deeper nesting (multiple children) is preserved.
   *
   * @param string $html
   *   The rendered HTML markup.
   */
  protected function stripOuterWrappingDivs(string $html): string {
    $trimmed = trim($html);
    while (preg_match('/^<div[^>]*>\s*(<div[\s\S]*<\/div>)\s*<\/div>$/s', $trimmed, $matches)) {
      $inner = trim($matches[1]);
      if ($inner === $trimmed) {
        break;
      }
      $trimmed = $inner;
    }
    return $trimmed;
  }

  /**
   * Converts root-relative href and src attributes to absolute URLs.
   *
   * @param string $html
   *   The rendered HTML markup.
   */
  protected function absolutizeUrls(string $html): string {
    $request = $this->requestStack->getCurrentRequest();
    if (!$request) {
      return $html;
    }
    $base = $request->getSchemeAndHttpHost();

    // Replace root-relative href and src attributes (starting with /).
    $html = preg_replace('/\bhref="(\/[^"]*)"/', 'href="' . $base . '$1"', $html);
    $html = preg_replace('/\bsrc="(\/[^"]*)"/', 'src="' . $base . '$1"', $html);

    return $html;
  }

}
