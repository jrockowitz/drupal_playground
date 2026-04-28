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
   * @var array
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
    'json_field',
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
      ->set('type', 'trial')
      ->set('fields', ['protocolSection.identificationModule.nctId'])
      ->save();

    $form = $this->container->get('form_builder')->buildForm('Drupal\clinical_trials_gov\Form\ClinicalTrialsGovImportForm', new FormState());

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

    // Check that the migration status fieldset contains the overview link.
    $this->assertArrayHasKey('overview', $form['migration_status']['links']);
  }

}
