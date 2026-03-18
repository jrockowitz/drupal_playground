<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_labels\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Functional tests for the entity-labels field report page.
 *
 * Verifies that the Fields tab renders the field report, that entity-type and
 * bundle cells contain the expected links, and that the CSV download button
 * is present at each scope level.
 *
 * @group entity_labels
 */
#[Group('entity_labels')]
#[RunTestsInSeparateProcesses]
class EntityLabelsFieldReportTest extends EntityLabelsTestBase {

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
   * Tests that the field report page returns 200.
   */
  public function testFieldReportPageLoads(): void {
    $this->drupalGet('admin/reports/entity-labels/field');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests that entity-type cells on the field report link to the drill-down.
   */
  public function testEntityTypeColumnContainsLink(): void {
    $this->drupalGet('admin/reports/entity-labels/field');
    $this->assertSession()->linkByHrefExists(
      '/admin/reports/entity-labels/field/node',
    );
  }

  /**
   * Tests that the entity-type drill-down page shows bundle links.
   *
   * When entity_type is set and bundle is not, bundle cells should be
   * linked to the bundle-level report.
   */
  public function testEntityTypeDrillDownShowsBundleLinks(): void {
    $this->drupalGet('admin/reports/entity-labels/field/node');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkByHrefExists(
      '/admin/reports/entity-labels/field/node/article',
    );
  }

  /**
   * Tests that the bundle-level report page returns 200 and shows fields.
   */
  public function testBundleLevelReportLoads(): void {
    $this->drupalGet('admin/reports/entity-labels/field/node/article');
    $this->assertSession()->statusCodeEquals(200);
    // The title field is always present on nodes.
    $this->assertSession()->pageTextContains('title');
  }

  /**
   * Tests that the Download CSV button is present on the field report.
   */
  public function testDownloadCsvButtonIsPresent(): void {
    $this->drupalGet('admin/reports/entity-labels/field');
    $this->assertSession()->linkExists('⇩ Download CSV');
  }

  /**
   * Tests that the field export route streams a CSV response.
   */
  public function testFieldExportRouteStreamsResponse(): void {
    $this->drupalGet('admin/reports/entity-labels/field/export');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderContains(
      'Content-Type',
      'text/csv',
    );
  }

  /**
   * Tests that the import note is shown only on the bundle-level report.
   *
   * The 'allowed_values and field_type cannot be updated via import' note
   * must appear only when both entity_type and bundle are set.
   */
  public function testImportNoteAppearsOnlyOnBundleView(): void {
    $this->drupalGet('admin/reports/entity-labels/field/node/article');
    $this->assertSession()->pageTextContains('cannot be updated via import');

    $this->drupalGet('admin/reports/entity-labels/field/node');
    $this->assertSession()->pageTextNotContains('cannot be updated via import');
  }

  /**
   * Tests that both primary report routes are accessible (tabs present).
   */
  public function testPrimaryTabsArePresent(): void {
    $this->drupalGet('admin/reports/entity-labels/field');
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet('admin/reports/entity-labels/entity');
    $this->assertSession()->statusCodeEquals(200);
  }

}
