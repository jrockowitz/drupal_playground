<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel\Form;

use Drupal\clinical_trials_gov\Form\ClinicalTrialsGovFindForm;
use Drupal\clinical_trials_gov_test\ClinicalTrialsGovStudyManagerStub;
use Drupal\Core\Form\FormState;
use Drupal\Tests\clinical_trials_gov\Kernel\ClinicalTrialsGovTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for ClinicalTrialsGovFindForm.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovFindFormTest extends ClinicalTrialsGovTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
  ];

  /**
   * The form under test.
   */
  protected ClinicalTrialsGovFindForm $formObject;

  /**
   * The stubbed ClinicalTrials.gov study manager.
   */
  protected ClinicalTrialsGovStudyManagerStub $studyManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('clinical_trials_gov');
    $this->formObject = ClinicalTrialsGovFindForm::create($this->container);
    $this->studyManager = $this->container->get('clinical_trials_gov.study_manager');
  }

  /**
   * Tests preview paging, reset behavior, and save validation.
   */
  public function testFindFormFlow(): void {
    $this->config('clinical_trials_gov.settings')
      ->set('query', 'query.cond=cancer')
      ->save();

    // Check that Find no longer exposes manual path settings.
    $empty_form = $this->formObject->buildForm([], new FormState());
    $this->assertArrayNotHasKey('advanced', $empty_form);

    // Check that a saved query still auto-loads preview results.
    $this->assertStringContainsString('Showing 1 - 2 of 3 trials.', $empty_form['preview']['results']['summary']['#markup']);
    $this->assertArrayHasKey('next_page', $empty_form['preview']['results']);

    $form_state = new FormState();
    $form_state->setValue('query', 'query.cond=cancer');
    $form = $this->formObject->buildForm([], $form_state);

    // Check that the first preview page shows the first page and exposes a next page button.
    $this->assertStringContainsString('Showing 1 - 2 of 3 trials.', $form['preview']['results']['summary']['#markup']);
    $this->assertArrayHasKey('next_page', $form['preview']['results']);
    $this->assertSame('Next page', (string) $form['preview']['results']['next_page']['#value']);

    $this->formObject->nextPreviewPageSubmit($form, $form_state);
    $paged_form = $this->formObject->buildForm([], $form_state);

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
    ], array_slice($this->studyManager->getStudiesRequests(), -2));

    $form_state->setValue('query', 'query.cond=cancer&filter.overallStatus=RECRUITING');
    $this->formObject->updatePreviewSubmit($paged_form, $form_state);
    $reset_form = $this->formObject->buildForm([], $form_state);

    // Check that updating the preview query resets paging back to the first page.
    $this->assertStringContainsString('Showing 1 - 2 of 3 trials.', $reset_form['preview']['results']['summary']['#markup']);
    $this->assertArrayHasKey('next_page', $reset_form['preview']['results']);
    $this->assertSame([
      'query.cond' => 'cancer',
      'filter.overallStatus' => 'RECRUITING',
      'countTotal' => 'true',
      'pageSize' => 10,
    ], $this->studyManager->getStudiesRequests()[count($this->studyManager->getStudiesRequests()) - 1]);

    $submit_form_state = new FormState();
    $submit_form_state->setValue('query', 'query.cond=lung');
    $submit_form = $this->formObject->buildForm([], $submit_form_state);
    $this->config('clinical_trials_gov.settings')
      ->set('query_paths', ['protocolSection.statusModule.overallStatus'])
      ->save();
    $this->formObject->submitForm($submit_form, $submit_form_state);
    $saved_config = $this->container->get('config.factory')->get('clinical_trials_gov.settings');

    // Check that submitting Find saves discovered paths immediately.
    $this->assertSame('query.cond=lung', $saved_config->get('query'));
    $this->assertContains('protocolSection.identificationModule.nctId', $saved_config->get('query_paths'));
    $this->assertContains('protocolSection.identificationModule.briefTitle', $saved_config->get('query_paths'));
    $this->assertSame([
      'query.cond' => 'lung',
      'pageSize' => 1000,
      'sort' => 'LastUpdatePostDate:desc',
    ], $this->studyManager->getStudiesRequests()[count($this->studyManager->getStudiesRequests()) - 1]);

    $save_form_state = new FormState();
    $save_form_state->setValue('query', '');
    $save_form = $this->formObject->buildForm([], $save_form_state);
    $save_form_state->setTriggeringElement([
      '#parents' => ['actions', 'submit'],
    ]);
    $this->formObject->validateForm($save_form, $save_form_state);

    // Check that saving without a query is rejected.
    $this->assertTrue($save_form_state->hasAnyErrors());
    $this->assertNotEmpty($save_form_state->getErrors()['query']);

    $preview_form_state = new FormState();
    $preview_form_state->setValue('query', '');
    $preview_form = $this->formObject->buildForm([], $preview_form_state);
    $preview_form_state->setTriggeringElement([
      '#parents' => ['preview', 'update_preview'],
    ]);
    $this->formObject->validateForm($preview_form, $preview_form_state);

    // Check that preview updates can still run without saving a query.
    $this->assertArrayNotHasKey('query', $preview_form_state->getErrors());
  }

}
