<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\clinical_trials_gov\Batch\ClinicalTrialsGovPathDiscoveryBatch;
use Drupal\clinical_trials_gov\Form\ClinicalTrialsGovFindForm;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for ClinicalTrialsGovFindForm.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovFindFormTest extends KernelTestBase {

  /**
   * Modules required for these kernel tests.
   *
   * @var array<string>
   */
  protected static $modules = [
    'clinical_trials_gov',
    'clinical_trials_gov_test',
    'migrate',
    'system',
    'user',
  ];

  /**
   * Tests preview paging and reset behavior.
   */
  public function testPreviewPaging(): void {
    $this->container->get('config.factory')->getEditable('clinical_trials_gov.settings')
      ->set('query', 'query.cond=cancer')
      ->save();

    $form_object = ClinicalTrialsGovFindForm::create($this->container);
    /** @var \Drupal\clinical_trials_gov_test\ClinicalTrialsGovManagerStub $manager */
    $manager = $this->container->get('clinical_trials_gov.manager');

    // Check that Find no longer exposes manual path settings.
    $empty_form = $form_object->buildForm([], new FormState());
    $this->assertArrayNotHasKey('advanced', $empty_form);

    // Check that a saved query still auto-loads preview results.
    $this->assertStringContainsString('Showing 1 - 2 of 3 trials.', $empty_form['preview']['results']['summary']['#markup']);
    $this->assertArrayHasKey('next_page', $empty_form['preview']['results']);

    $form_state = new FormState();
    $form_state->setValue('query', 'query.cond=cancer');
    $form = $form_object->buildForm([], $form_state);

    // Check that the first preview page shows the first page and exposes a next page button.
    $this->assertStringContainsString('Showing 1 - 2 of 3 trials.', $form['preview']['results']['summary']['#markup']);
    $this->assertArrayHasKey('next_page', $form['preview']['results']);
    $this->assertSame('Next page', (string) $form['preview']['results']['next_page']['#value']);

    $form_object->nextPreviewPageSubmit($form, $form_state);
    $paged_form = $form_object->buildForm([], $form_state);

    // Check that clicking next page advances the preview range and consumes the page token.
    $this->assertStringContainsString('Showing 3 - 3 of 3 trials.', $paged_form['preview']['results']['summary']['#markup']);
    $this->assertArrayNotHasKey('next_page', $paged_form['preview']['results']);
    $this->assertSame([
      [
        'query.cond' => 'cancer',
        'countTotal' => 'true',
        'pageSize' => 10,
      ],
      [
        'query.cond' => 'cancer',
        'pageToken' => 'page-2',
        'countTotal' => 'true',
        'pageSize' => 10,
      ],
    ], array_slice($manager->getStudiesRequests(), -2));

    $form_state->setValue('query', 'query.cond=cancer&filter.overallStatus=RECRUITING');
    $form_object->updatePreviewSubmit($paged_form, $form_state);
    $reset_form = $form_object->buildForm([], $form_state);

    // Check that updating the preview query resets paging back to the first page.
    $this->assertStringContainsString('Showing 1 - 2 of 3 trials.', $reset_form['preview']['results']['summary']['#markup']);
    $this->assertArrayHasKey('next_page', $reset_form['preview']['results']);
    $this->assertSame([
      'query.cond' => 'cancer',
      'filter.overallStatus' => 'RECRUITING',
      'countTotal' => 'true',
      'pageSize' => 10,
    ], $manager->getStudiesRequests()[count($manager->getStudiesRequests()) - 1]);

    $submit_form_state = new FormState();
    $submit_form_state->setValue('query', 'query.cond=lung');
    $submit_form = $form_object->buildForm([], $submit_form_state);
    $this->container->get('config.factory')->getEditable('clinical_trials_gov.settings')
      ->set('paths', ['protocolSection.statusModule.overallStatus'])
      ->save();
    $form_object->submitForm($submit_form, $submit_form_state);
    $saved_config = $this->container->get('config.factory')->get('clinical_trials_gov.settings');
    $batch = batch_get();

    // Check that submitting Find clears stale paths and registers a discovery batch.
    $this->assertSame('query.cond=lung', $saved_config->get('query'));
    $this->assertSame([], $saved_config->get('paths'));
    $this->assertArrayHasKey('sets', $batch);
    $this->assertSame([ClinicalTrialsGovPathDiscoveryBatch::class, 'discover'], $batch['sets'][0]['operations'][0][0]);

  }

}
