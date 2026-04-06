<?php

declare(strict_types=1);

namespace Drupal\plugin_report\Controller;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\Core\Utility\TableSort;
use Drupal\plugin_report\PluginReportManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides the Plugin Report pages.
 */
final class PluginReportController extends ControllerBase {

  /**
   * The plugin report manager.
   */
  protected PluginReportManager $pluginReportManager;

  /**
   * The request stack.
   */
  protected RequestStack $requestStack;

  /**
   * Constructs a PluginReportController.
   *
   * @param \Drupal\plugin_report\PluginReportManager $pluginReportManager
   *   The plugin report manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   */
  public function __construct(PluginReportManager $pluginReportManager, RequestStack $requestStack) {
    $this->pluginReportManager = $pluginReportManager;
    $this->requestStack = $requestStack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('plugin_report.manager'),
      $container->get('request_stack'),
    );
  }

  /**
   * Renders the plugin manager list page.
   *
   * @return array
   *   A render array containing a table of all DefaultPluginManager services.
   */
  public function managers(): array {
    $managers = $this->pluginReportManager->getPluginManagers();

    $headers = [
      'id' => ['data' => $this->t('Service ID'), 'field' => 'id', 'sort' => 'asc'],
      'alter_hook' => ['data' => $this->t('Alter Hook'), 'field' => 'alter_hook'],
      'subdir' => ['data' => $this->t('Subdirectory'), 'field' => 'subdir'],
      'provider' => ['data' => $this->t('Provider'), 'field' => 'provider'],
      'discovery' => ['data' => $this->t('Discovery'), 'field' => 'discovery'],
      'interface' => ['data' => $this->t('Plugin Interface'), 'field' => 'interface'],
      'class' => ['data' => $this->t('Class'), 'field' => 'class'],
    ];

    $request = $this->requestStack->getCurrentRequest();
    $context = TableSort::getContextFromRequest($headers, $request);
    usort($managers, static function (array $a, array $b) use ($context): int {
      $field = $context['sql'];
      $result = strcmp((string) ($a[$field] ?? ''), (string) ($b[$field] ?? ''));
      return $context['sort'] === 'desc' ? -$result : $result;
    });

    $rows = [];
    foreach ($managers as $info) {
      $rows[] = [
        'id' => [
          'data' => [
            '#type' => 'link',
            '#title' => $info['id'],
            '#url' => Url::fromRoute('plugin_report.plugins', [
              'plugin_manager' => $info['id'],
            ]),
          ],
        ],
        'alter_hook' => (string) ($info['alter_hook'] ?? ''),
        'subdir' => (string) ($info['subdir'] ?? ''),
        'provider' => $info['provider'],
        'discovery' => (string) ($info['discovery'] ?? ''),
        'interface' => (string) ($info['interface'] ?? ''),
        'class' => $info['class'],
      ];
    }

    return [
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#sticky' => TRUE,
      '#empty' => $this->t('No plugin managers found.'),
      '#attributes' => ['class' => ['plugin-report-table']],
      '#attached' => ['library' => ['plugin_report/plugin_report']],
    ];
  }

  /**
   * Returns the page title for the plugin detail page.
   *
   * @param string $plugin_manager
   *   The plugin manager service ID from the route parameter.
   *
   * @return string
   *   The page title.
   */
  public function title(string $plugin_manager): string {
    return 'Plugin Report: ' . $plugin_manager;
  }

  /**
   * Renders the plugin detail page for a single plugin manager.
   *
   * @param string $plugin_manager
   *   The plugin manager service ID from the route parameter.
   *
   * @return array
   *   A render array containing a table of plugins.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   If the service ID is not a known DefaultPluginManager.
   */
  public function plugins(string $plugin_manager): array {
    try {
      $plugins = $this->pluginReportManager->getPlugins($plugin_manager);
    }
    catch (\InvalidArgumentException) {
      throw new NotFoundHttpException();
    }

    if (empty($plugins)) {
      $message = $this->t('No plugins found for this manager.');
      return ['#markup' => '<p>' . $message . '</p>'];
    }

    $keys = array_keys(reset($plugins));
    $preferredOrder = ['id', 'label', 'description', 'provider', 'dependencies', 'status', 'weight'];
    $orderedKeys = array_merge(
      array_intersect($preferredOrder, $keys),
      array_diff(array_diff($keys, $preferredOrder), ['class']),
      in_array('class', $keys, TRUE) ? ['class'] : [],
    );

    $headers = array_map(
      static fn(string $key): array => $key === 'id'
        ? ['data' => $key, 'field' => $key, 'sort' => 'asc']
        : ['data' => $key, 'field' => $key],
      $orderedKeys,
    );

    $request = $this->requestStack->getCurrentRequest();
    $context = TableSort::getContextFromRequest($headers, $request);
    uasort($plugins, static function (array $a, array $b) use ($context): int {
      $field = $context['sql'];
      $valueA = $a[$field] ?? '';
      $valueB = $b[$field] ?? '';
      if ($valueA instanceof TranslatableMarkup) {
        $valueA = (string) $valueA;
      }
      if ($valueB instanceof TranslatableMarkup) {
        $valueB = (string) $valueB;
      }
      $result = is_scalar($valueA) && is_scalar($valueB)
        ? strcmp((string) $valueA, (string) $valueB)
        : 0;
      return $context['sort'] === 'desc' ? -$result : $result;
    });

    $rows = [];
    foreach ($plugins as $plugin) {
      $row = [];
      foreach ($orderedKeys as $key) {
        $row[] = $this->formatValue($plugin[$key] ?? '');
      }
      $rows[] = $row;
    }

    return [
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#sticky' => TRUE,
      '#empty' => $this->t('No plugins found.'),
      '#attributes' => ['class' => ['plugin-report-table']],
      '#attached' => ['library' => ['plugin_report/plugin_report']],
    ];
  }

  /**
   * Recursively converts TranslatableMarkup instances to plain strings.
   *
   * Walks arrays depth-first, casting any TranslatableMarkup leaf to string.
   * Non-TranslatableMarkup objects are returned unchanged.
   *
   * @param mixed $value
   *   The value to convert.
   *
   * @return mixed
   *   The value with all TranslatableMarkup instances replaced by strings.
   */
  private function convertTranslatableMarkup(mixed $value): mixed {
    if ($value instanceof TranslatableMarkup) {
      return (string) $value;
    }
    if (is_array($value)) {
      return array_map($this->convertTranslatableMarkup(...), $value);
    }
    if (is_object($value)) {
      return method_exists($value, '__toString') ? (string) $value : get_class($value);
    }
    return $value;
  }

  /**
   * Formats a single plugin definition value for table display.
   *
   * Top-level TranslatableMarkup is cast to string directly. Non-scalar values
   * are passed through convertTranslatableMarkup() to resolve any nested
   * TranslatableMarkup before being serialized to YAML.
   *
   * @param mixed $value
   *   The raw value from a plugin definition.
   *
   * @return string
   *   A displayable string representation.
   */
  private function formatValue(mixed $value): string|array {
    if ($value instanceof TranslatableMarkup) {
      return (string) $value;
    }
    if (!is_scalar($value)) {
      return [
        'data' => [
          '#type' => 'html_tag',
          '#tag' => 'pre',
          '#value' => Yaml::encode($this->convertTranslatableMarkup($value)),
        ],
      ];
    }
    return (string) $value;
  }

}
