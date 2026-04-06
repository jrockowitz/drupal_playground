<?php

declare(strict_types=1);

namespace Drupal\Tests\plugin_report\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the Plugin Report controller pages.
 *
 * @group plugin_report
 */
class PluginReportControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['plugin_report', 'block'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests all Plugin Report pages in a single browser session.
   *
   * Combines authenticated happy-path, access-denied, and 404 assertions to
   * avoid the overhead of repeated Drupal installs that separate test methods
   * would incur in BrowserTestBase.
   */
  public function testPluginReportPages(): void {
    // Check that both pages are inaccessible without the required permission.
    $unprivileged = $this->drupalCreateUser([]);
    $this->drupalLogin($unprivileged);
    $this->drupalGet('/admin/reports/plugins');
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet('/admin/reports/plugins/plugin.manager.block');
    $this->assertSession()->statusCodeEquals(403);

    // Check that the manager list page renders a table with expected headers.
    $admin = $this->drupalCreateUser(['access site reports']);
    $this->drupalLogin($admin);
    $this->drupalGet('/admin/reports/plugins');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', 'table');
    $this->assertSession()->pageTextContains('Service ID');
    $this->assertSession()->pageTextContains('Class');
    $this->assertSession()->pageTextContains('Provider');
    $this->assertSession()->pageTextContains('Discovery');
    // Check that the filter input is rendered on the managers page.
    $this->assertSession()->elementExists('css', 'input.plugin-report-filter-text');

    // Check that plugin.manager.block appears and links to its detail page.
    $this->assertSession()->pageTextContains('plugin.manager.block');
    $this->assertSession()->linkByHrefExists('/admin/reports/plugins/plugin.manager.block');

    // Check that the plugin detail page resolves correctly, including dots in
    // the route parameter not being misinterpreted as a format suffix.
    $this->drupalGet('/admin/reports/plugins/plugin.manager.block');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', 'table');
    // Check that block definitions expose an 'id' column.
    $this->assertSession()->pageTextContains('id');
    // Check that the filter input is rendered on the plugins page.
    $this->assertSession()->elementExists('css', 'input.plugin-report-filter-text');
    // Check that the title callback renders the manager ID in the page heading.
    $this->assertSession()->pageTextContains('Plugin Report: plugin.manager.block');

    // Check that an unknown manager returns 404 rather than a PHP exception.
    $this->drupalGet('/admin/reports/plugins/plugin.manager.does_not_exist_xyz');
    $this->assertSession()->statusCodeEquals(404);
  }

}
