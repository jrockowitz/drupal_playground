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
   * Tests the field report page, its drill-down/export routes, and access control.
   */
  public function testFieldReport(): void {
    // Check that the field report page returns 200.
    $this->drupalGet('admin/reports/entity-labels/field');
    $this->assertSession()->statusCodeEquals(200);

    // Check that the field report tab is accessible from the entity report.
    $this->drupalGet('admin/reports/entity-labels/entity');
    $this->assertSession()->statusCodeEquals(200);

    // Check that entity-type cells link to the entity-type drill-down.
    $this->drupalGet('admin/reports/entity-labels/field');
    $this->assertSession()->linkByHrefExists('/admin/reports/entity-labels/field/node');

    // Check that the Download CSV button is present.
    $this->assertSession()->linkExists('⇩ Download CSV');

    // Check that the entity-type drill-down returns 200 and shows bundle links.
    $this->drupalGet('admin/reports/entity-labels/field/node');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkByHrefExists('/admin/reports/entity-labels/field/node/article');

    // Check that the bundle-level report page returns 200 and shows field data.
    $this->drupalGet('admin/reports/entity-labels/field/node/article');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('name');

    // Check that the field export route streams a CSV file.
    $this->drupalGet('admin/reports/entity-labels/field/export');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderContains('Content-Type', 'text/csv');

    // Check that the field report requires 'access site reports'.
    $this->drupalLogin($this->drupalCreateUser([]));
    $this->drupalGet('admin/reports/entity-labels/field');
    $this->assertSession()->statusCodeEquals(403);

    // Check that the field import form requires 'administer site configuration'.
    $this->drupalLogin($this->drupalCreateUser(['access site reports']));
    $this->drupalGet('admin/reports/entity-labels/field/import');
    $this->assertSession()->statusCodeEquals(403);
  }

}
