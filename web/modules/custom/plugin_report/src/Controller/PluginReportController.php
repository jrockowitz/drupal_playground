<?php

declare(strict_types=1);

namespace Drupal\plugin_report\Controller;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\plugin_report\PluginReportManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
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
   * Constructs a PluginReportController.
   *
   * @param \Drupal\plugin_report\PluginReportManager $pluginReportManager
   *   The plugin report manager.
   */
  public function __construct(PluginReportManager $pluginReportManager) {
    $this->pluginReportManager = $pluginReportManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('plugin_report.manager'),
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
      $this->t('Service ID'),
      $this->t('Class'),
      $this->t('Provider'),
      $this->t('Subdirectory'),
      $this->t('Discovery'),
      $this->t('Plugin Interface'),
      $this->t('Alter Hook'),
    ];

    $rows = [];
    foreach ($managers as $info) {
      $rows[] = [
        [
          'data' => [
            '#type' => 'link',
            '#title' => $info['id'],
            '#url' => Url::fromRoute('plugin_report.plugins', [
              'plugin_manager' => $info['id'],
            ]),
          ],
        ],
        $info['class'],
        $info['provider'],
        (string) ($info['subdir'] ?? ''),
        (string) ($info['discovery'] ?? ''),
        (string) ($info['interface'] ?? ''),
        (string) ($info['alter_hook'] ?? ''),
      ];
    }

    return [
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#sticky' => TRUE,
      '#empty' => $this->t('No plugin managers found.'),
    ];
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
      return ['#markup' => '<p>' . $this->t('No plugins found for this manager.') . '</p>'];
    }

    $headers = array_keys(reset($plugins));

    $rows = [];
    foreach ($plugins as $plugin) {
      $row = [];
      foreach ($headers as $key) {
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
  private function formatValue(mixed $value): string {
    if ($value instanceof TranslatableMarkup) {
      return (string) $value;
    }
    if (!is_scalar($value)) {
      return Yaml::encode($this->convertTranslatableMarkup($value));
    }
    return (string) $value;
  }

}
