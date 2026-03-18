<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_labels\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Functional tests for the entity-labels entity report page.
 *
 * Verifies the Entities tab report renders correctly, that entity-type cells
 * contain links to the drill-down report, and that the CSV download button
 * is present.
 *
 * @group entity_labels
 */
#[Group('entity_labels')]
#[RunTestsInSeparateProcesses]
class EntityLabelsEntityReportTest extends EntityLabelsTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'entity_labels'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin(
      $this->drupalCreateUser(['access site reports'])
    );
  }

  /**
   * Tests that the entity report page is accessible and returns 200.
   */
  public function testEntityReportPageLoads(): void {
    $this->drupalGet('admin/reports/entity-labels/entity');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests that entity type rows contain a link to the drill-down report.
   */
  public function testEntityTypeColumnContainsLink(): void {
    $this->drupalGet('admin/reports/entity-labels/entity');
    $this->assertSession()->linkByHrefExists(
      '/admin/reports/entity-labels/entity/node',
    );
  }

  /**
   * Tests that drilling down to an entity type page shows bundles.
   */
  public function testEntityTypeDrillDownShowsBundle(): void {
    $this->drupalGet('admin/reports/entity-labels/entity/node');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Article');
  }

  /**
   * Tests that the Download CSV button is present on the report page.
   */
  public function testDownloadCsvButtonIsPresent(): void {
    $this->drupalGet('admin/reports/entity-labels/entity');
    $this->assertSession()->linkExists('⇩ Download CSV');
  }

  /**
   * Tests that the export route streams a CSV file.
   */
  public function testEntityExportRouteStreamsResponse(): void {
    $this->drupalGet('admin/reports/entity-labels/entity/export');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderContains(
      'Content-Type',
      'text/csv',
    );
  }

  /**
   * Tests that both primary report routes are accessible (tabs present).
   */
  public function testPrimaryTabsArePresent(): void {
    $this->drupalGet('admin/reports/entity-labels/entity');
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet('admin/reports/entity-labels/field');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests that the breadcrumb contains 'Reports'.
   */
  public function testBreadcrumbContainsReports(): void {
    $this->drupalGet('admin/reports/entity-labels/entity');
    $this->assertSession()->linkByHrefExists('/admin/reports');
  }

}
