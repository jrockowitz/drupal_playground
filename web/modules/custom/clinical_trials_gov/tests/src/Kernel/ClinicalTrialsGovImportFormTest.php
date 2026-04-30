<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for ClinicalTrialsGovImportForm.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovImportFormTest extends KernelTestBase {

  /**
   * Modules required for these kernel tests.
   *
   * @var array<string>
   */
  protected static $modules = [
    'clinical_trials_gov',
    'clinical_trials_gov_test',
    'migrate',
    'migrate_plus',
    'migrate_tools',
    'node',
    'field',
    'text',
    'options',
    'datetime',
    'filter',
    'user',
    'system',
    'custom_field',
    'field_group',
  ];

  /**
   * Tests the import form section layout and action links.
   */
  public function testBuildFormSections(): void {
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig(['field', 'filter', 'node', 'system']);
    $this->installSchema('node', ['node_access']);

    $this->config('clinical_trials_gov.settings')
      ->set('query', 'query.cond=cancer&filter.overallStatus=RECRUITING')
      ->set('paths', ['protocolSection.identificationModule.nctId'])
      ->set('type', 'trial')
      ->set('fields', ['field_nct_id' => 'protocolSection.identificationModule.nctId'])
      ->save();
    $this->container->get('clinical_trials_gov.migration_manager')->updateMigration();

    $form_state = new FormState();
    $form = $this->container->get('form_builder')->buildForm('Drupal\clinical_trials_gov\Form\ClinicalTrialsGovImportForm', $form_state);

    // Check that the import form is grouped into the expected fieldsets.
    $this->assertArrayHasKey('studies_query', $form);
    $this->assertArrayHasKey('content_type', $form);
    $this->assertArrayHasKey('migration_status', $form);

    // Check that the studies query fieldset uses the summary render element and links.
    $this->assertSame('clinical_trials_gov_studies_query_summary', $form['studies_query']['summary']['#type']);
    $this->assertArrayHasKey('find', $form['studies_query']['links']);
    $this->assertArrayHasKey('review', $form['studies_query']['links']);
    $this->assertSame(['summary', 'links'], array_values(array_filter(array_keys($form['studies_query']), static fn(string $key): bool => !str_starts_with($key, '#'))));

    // Check that the content type fieldset contains the configure link.
    $this->assertArrayHasKey('configure', $form['content_type']['links']);
    $this->assertSame(['summary', 'links'], array_values(array_filter(array_keys($form['content_type']), static fn(string $key): bool => !str_starts_with($key, '#'))));

    // Check that the content type table uses label/value rows without a header.
    $this->assertSame([], $form['content_type']['summary']['#header']);
    $content_type_rows = $form['content_type']['summary']['#rows'];
    $content_type_labels = array_map(static function (array $row): string {
      return (string) strip_tags((string) $row[0]['data']['#markup']);
    }, $content_type_rows);

    // Check that the content type table includes the expected rows.
    $this->assertSame([
      'Content type',
      'Selected fields',
    ], $content_type_labels);

    // Check that the migration status fieldset contains the overview link.
    $this->assertArrayHasKey('overview', $form['migration_status']['links']);

    // Check that the migration status table uses label/value rows without a header.
    $this->assertArrayHasKey('stats', $form['migration_status']);
    $this->assertSame([], $form['migration_status']['stats']['#header']);

    $rows = $form['migration_status']['stats']['#rows'];
    $labels = array_map(static function (array $row): string {
      return (string) strip_tags((string) $row[0]['data']['#markup']);
    }, $rows);

    // Check that the migration status table includes the expected migration rows.
    $this->assertSame([
      'Migration',
      'Machine Name',
      'Status',
      'Total',
      'Imported',
      'Unprocessed',
      'Messages',
      'Last Imported',
    ], $labels);

    $this->container->get('messenger')->deleteAll();
    $this->config('clinical_trials_gov.settings')
      ->set('query', '')
      ->set('paths', [])
      ->set('type', '')
      ->set('fields', [])
      ->save();
    $not_ready_form_state = new FormState();
    $not_ready_form = $this->container->get('form_builder')->buildForm('Drupal\clinical_trials_gov\Form\ClinicalTrialsGovImportForm', $not_ready_form_state);
    $warning_messages = $this->container->get('messenger')->messagesByType('warning');

    // Check that the not-ready warning is displayed through the page messenger.
    $this->assertArrayNotHasKey('message', $not_ready_form['migration_status']);
    $this->assertCount(1, $warning_messages);
    $this->assertStringContainsString('Complete the', (string) $warning_messages[0]);
    $this->assertStringContainsString('Find', (string) $warning_messages[0]);
    $this->assertStringContainsString('Configure', (string) $warning_messages[0]);
  }

}
