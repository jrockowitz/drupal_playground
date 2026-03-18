<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_labels\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Functional tests for field_group row support in entity_labels.
 *
 * These tests are skipped automatically when the field_group module is not
 * available. They verify that field_group rows appear in the bundle-level
 * field report and can be round-tripped through CSV import.
 *
 * @group entity_labels
 */
#[Group('entity_labels')]
#[RunTestsInSeparateProcesses]
class EntityLabelsFieldGroupTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'entity_labels'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    if (!\Drupal::moduleHandler()->moduleExists('field_group')) {
      $this->markTestSkipped('field_group module is not available.');
    }

    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
    $this->drupalLogin(
      $this->drupalCreateUser(['access site reports'])
    );
  }

  /**
   * Tests that field_group rows appear in the bundle-level field report.
   *
   * Creates a field group on the node/article default form display and
   * verifies that the bundle-level report includes a row with field_type
   * 'field_group'.
   */
  public function testFieldGroupRowsAppearInBundleReport(): void {
    $this->addFieldGroupToArticle('group_test', 'Test Group');

    $this->drupalGet('admin/reports/entity-labels/field/node/article');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('field_group');
  }

  /**
   * Tests that a field_group CSV row can be imported without errors.
   *
   * Exports the current CSV for node/article, modifies the field_group label,
   * and re-imports it. Verifies a success status message is shown.
   */
  public function testFieldGroupCsvImportSucceeds(): void {
    $this->addFieldGroupToArticle('group_import', 'Import Group');

    $csv_content = "langcode,entity_type,bundle,field_name,field_type,label,description\n"
      . "en,node,article,group_import,field_group,Updated Group Label,Updated desc\n";

    $this->drupalGet('admin/reports/entity-labels/field/import');
    $this->submitForm(
      ['files[csv_upload]' => $this->writeTemporaryCsv($csv_content)],
      'Import CSV',
    );
    $this->assertSession()->statusMessageContains('updated', 'status');
  }

  /**
   * Adds a field group to the node/article default form display.
   *
   * @param string $group_name
   *   The group machine name.
   * @param string $label
   *   The group label.
   */
  private function addFieldGroupToArticle(string $group_name, string $label): void {
    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $display */
    $display = \Drupal::entityTypeManager()
      ->getStorage('entity_form_display')
      ->load('node.article.default');
    if ($display === NULL) {
      return;
    }

    $group = [
      'label'           => $label,
      'children'        => [],
      'parent_name'     => '',
      'weight'          => 0,
      'format_type'     => 'details',
      'format_settings' => [
        'open'        => FALSE,
        'description' => 'Group description',
      ],
    ];
    $display->setThirdPartySetting('field_group', $group_name, $group);
    $display->save();
  }

  /**
   * Writes CSV content to a temporary file and returns its path.
   *
   * @param string $content
   *   The CSV content to write.
   *
   * @return string
   *   Absolute path to the temporary file.
   */
  private function writeTemporaryCsv(string $content): string {
    $path = $this->tempFilesDirectory . '/' . $this->randomMachineName() . '.csv';
    file_put_contents($path, $content);
    return $path;
  }

}
