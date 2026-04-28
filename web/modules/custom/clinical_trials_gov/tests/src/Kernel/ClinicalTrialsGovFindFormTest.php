<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\clinical_trials_gov\Form\ClinicalTrialsGovFindForm;
use Drupal\clinical_trials_gov_test\ClinicalTrialsGovManagerStub;
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
   * @var array
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
    $manager = $this->container->get('clinical_trials_gov.manager');
    $this->assertInstanceOf(ClinicalTrialsGovManagerStub::class, $manager);

    // Check that an empty saved query shows the instructional preview message.
    $empty_form = $form_object->buildForm([], new FormState());
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
  }

}
