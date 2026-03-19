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
   * Tests the entity report page, its drill-down, export, and 404 routes.
   */
  public function testEntityReport(): void {
    // Check that the entity report page returns 200.
    $this->drupalGet('admin/reports/entity-labels/entity');
    $this->assertSession()->statusCodeEquals(200);

    // Check that entity-type cells link to the drill-down report.
    $this->assertSession()->linkByHrefExists('/admin/reports/entity-labels/entity/node');

    // Check that the Download CSV button is present.
    $this->assertSession()->linkExists('⇩ Download CSV');

    // Check that the breadcrumb contains a Reports link.
    $this->assertSession()->linkByHrefExists('/admin/reports');

    // Check that the title callback renders the top-level title.
    $this->assertSession()->pageTextContains('Entity labels');

    // Check that the entity-type drill-down page returns 200 and shows bundles.
    $this->drupalGet('admin/reports/entity-labels/entity/node');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Article');

    // Check that the title callback appends the entity type label.
    $this->assertSession()->pageTextContains('Entity labels: Content');

    // Check that the export route streams a CSV file.
    $this->drupalGet('admin/reports/entity-labels/entity/export');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderContains('Content-Type', 'text/csv');

    // Check that an invalid entity type path returns 404.
    $this->drupalGet('admin/reports/entity-labels/entity/nonexistent_type');
    $this->assertSession()->statusCodeEquals(404);

    // Check that an invalid bundle on the field report also returns 404.
    $this->drupalGet('admin/reports/entity-labels/field/node/nonexistent_bundle');
    $this->assertSession()->statusCodeEquals(404);

    // Check that the entity report requires 'access site reports'.
    $this->drupalLogin($this->drupalCreateUser([]));
    $this->drupalGet('admin/reports/entity-labels/entity');
    $this->assertSession()->statusCodeEquals(403);

    // Check that the import form requires 'administer site configuration'.
    $this->drupalLogin($this->drupalCreateUser(['access site reports']));
    $this->drupalGet('admin/reports/entity-labels/entity/import');
    $this->assertSession()->statusCodeEquals(403);
  }

}
