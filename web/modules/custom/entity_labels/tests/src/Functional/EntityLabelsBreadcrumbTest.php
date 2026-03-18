<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_labels\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Functional tests for the EntityLabelsBreadcrumbBuilder.
 *
 * Verifies that breadcrumb trails are built correctly for the global report,
 * the entity-type drill-down, and the bundle drill-down.
 *
 * @group entity_labels
 */
#[Group('entity_labels')]
#[RunTestsInSeparateProcesses]
class EntityLabelsBreadcrumbTest extends EntityLabelsTestBase {

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
   * Tests breadcrumb on the global entity report: 'Entities' is active.
   */
  public function testGlobalReportBreadcrumb(): void {
    $this->drupalGet('admin/reports/entity-labels/entity');
    $session = $this->assertSession();

    // Ancestor links are present.
    $session->linkByHrefExists('/admin/reports');
    $session->linkExists('Entity labels');

    // 'Entities' is the unlinked active crumb — it appears as text, not a link.
    $session->elementTextContains('css', '.breadcrumb', 'Entities');
    $session->linkNotExists('Entities');
  }

  /**
   * Tests breadcrumb on entity-type drill-down: entity type label is active.
   */
  public function testEntityTypeDrillDownBreadcrumb(): void {
    $this->drupalGet('admin/reports/entity-labels/entity/node');
    $session = $this->assertSession();

    // 'Entities' is now linked.
    $session->linkExists('Entities');

    // The entity type label ('Content') appears as unlinked active crumb.
    $session->elementTextContains('css', '.breadcrumb', 'Content');
    $session->linkNotExists('Content');
  }

  /**
   * Tests breadcrumb on the bundle drill-down: bundle label is active crumb.
   */
  public function testBundleDrillDownBreadcrumb(): void {
    $this->drupalGet('admin/reports/entity-labels/field/node/article');
    $session = $this->assertSession();

    // 'Fields' is linked.
    $session->linkExists('Fields');

    // Entity type label ('Content') is linked, not the active crumb.
    $session->linkExists('Content');

    // Bundle label ('Article') is the unlinked active crumb.
    $session->elementTextContains('css', '.breadcrumb', 'Article');
    $session->linkNotExists('Article');
  }

}
