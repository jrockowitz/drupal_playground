<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel\Hook;

use Drupal\clinical_trials_gov\ClinicalTrialsGovEntityManagerInterface;
use Drupal\node\Entity\Node;
use Drupal\Tests\clinical_trials_gov\Kernel\ClinicalTrialsGovContentTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for ClinicalTrials.gov field-access hook behavior.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovFieldAccessHooksTest extends ClinicalTrialsGovContentTestBase {

  /**
   * The entity manager under test.
   */
  protected ClinicalTrialsGovEntityManagerInterface $entityManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('clinical_trials_gov');
    $this->config('clinical_trials_gov.settings')
      ->set('type', 'trial')
      ->set('field_prefix', 'trial')
      ->set('view_display_component', 'visible_update')
      ->set('form_display_component', 'visible')
      ->save();
    $this->entityManager = $this->container->get('clinical_trials_gov.entity_manager');
  }

  /**
   * Tests that visible_update field access requires update access.
   */
  public function testVisibleUpdateFieldAccess(): void {
    $this->entityManager->createContentType('trial', 'Trial', 'Clinical trial content type');
    $this->entityManager->createFields('trial', [
      'protocolSection.identificationModule.briefTitle',
      'protocolSection.identificationModule.nctId',
    ]);

    $this->config('clinical_trials_gov.settings')
      ->set('fields', [
        'trial_brief_title' => 'protocolSection.identificationModule.briefTitle',
        'trial_nct_id' => 'protocolSection.identificationModule.nctId',
      ])
      ->save();

    Role::create([
      'id' => 'trial_editor',
      'label' => 'Trial editor',
    ])->grantPermission('access content')
      ->grantPermission('edit any trial content')
      ->save();
    Role::create([
      'id' => 'trial_viewer',
      'label' => 'Trial viewer',
    ])->grantPermission('access content')
      ->save();

    $editable_user = User::create([
      'name' => 'trial-editor',
      'status' => 1,
    ]);
    $editable_user->addRole('trial_editor');
    $editable_user->save();

    $view_only_user = User::create([
      'name' => 'trial-viewer',
      'status' => 1,
    ]);
    $view_only_user->addRole('trial_viewer');
    $view_only_user->save();

    $node = Node::create([
      'type' => 'trial',
      'title' => 'Visible update field access',
      'trial_brief_title' => [
        'value' => 'Visible brief title',
      ],
      'trial_nct_id' => [
        'value' => 'NCT05088187',
      ],
      'trial_nct_url' => [
        'uri' => 'https://clinicaltrials.gov/study/NCT05088187',
      ],
      'trial_nct_api' => [
        'uri' => 'https://clinicaltrials.gov/api/v2/studies/NCT05088187',
      ],
    ]);
    $node->save();
    $node = Node::load($node->id());

    // Check that mapped fields stay viewable for an editor who can update.
    $this->assertTrue($node->get('trial_brief_title')->access('view', $editable_user));
    $this->assertTrue($node->get('trial_nct_id')->access('view', $editable_user));
    $this->assertTrue($node->get('trial_nct_url')->access('view', $editable_user));
    $this->assertTrue($node->get('trial_nct_api')->access('view', $editable_user));

    // Check that mapped fields are hidden from a user who cannot update.
    $this->assertFalse($node->get('trial_brief_title')->access('view', $view_only_user));
    $this->assertFalse($node->get('trial_nct_id')->access('view', $view_only_user));
    $this->assertFalse($node->get('trial_nct_url')->access('view', $view_only_user));
    $this->assertFalse($node->get('trial_nct_api')->access('view', $view_only_user));
  }

}
