<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel\Hook;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\Entity\Node;
use Drupal\Tests\clinical_trials_gov\Kernel\ClinicalTrialsGovContentTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for ClinicalTrials.gov token hook behavior.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovTokenHooksTest extends ClinicalTrialsGovContentTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'token',
    'clinical_trials_gov_report',
  ];

  /**
   * The generated NCT ID field machine name.
   */
  protected string $nctIdFieldName;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('clinical_trials_gov');
    $this->config('clinical_trials_gov.settings')
      ->set('type', 'trial')
      ->set('field_prefix', 'trial')
      ->save();

    $entity_manager = $this->container->get('clinical_trials_gov.entity_manager');
    $entity_manager->createContentType('trial', 'Trial', 'Clinical trial content type');
    $entity_manager->createFields('trial', [
      'protocolSection.identificationModule.nctId',
      'protocolSection.identificationModule.briefTitle',
    ]);

    $this->nctIdFieldName = $entity_manager->generateFieldName('protocolSection.identificationModule.nctId');
  }

  /**
   * Tests node token resolution for ClinicalTrials.gov study paths and pieces.
   */
  public function testNodeTokensResolveClinicalTrialsGovApiValues(): void {
    // cspell:ignore protocolsection identificationmodule brieftitle
    $node = Node::create([
      'type' => 'trial',
      'title' => 'ClinicalTrials.gov token test',
      $this->nctIdFieldName => [
        'value' => 'NCT05088187',
      ],
    ]);
    $node->save();

    $tokens = $this->buildTokenInput([
      'clinical-trials-gov:protocolSection.identificationModule.briefTitle',
      'clinical-trials-gov:protocolsection.identificationmodule.brieftitle',
      'clinical-trials-gov:protocolSection.identification_module.brief-title',
      'clinical-trials-gov:BriefTitle',
      'clinical-trials-gov:brieftitle',
      'clinical-trials-gov:brief_title',
      'clinical-trials-gov:brief-title',
      'clinical-trials-gov:protocolSection.conditionsModule.conditions',
      'clinical-trials-gov:unknown-piece',
    ]);

    $bubbleable_metadata = new BubbleableMetadata();
    $replacements = $this->container->get('token')->generate('node', $tokens, ['node' => $node], [], $bubbleable_metadata);

    // Check that path and piece tokens resolve the expected scalar value.
    $this->assertSame('Cognition and QoL After Thyroid Surgery', $replacements['[node:clinical-trials-gov:protocolSection.identificationModule.briefTitle]']);
    $this->assertSame('Cognition and QoL After Thyroid Surgery', $replacements['[node:clinical-trials-gov:protocolsection.identificationmodule.brieftitle]']);
    $this->assertSame('Cognition and QoL After Thyroid Surgery', $replacements['[node:clinical-trials-gov:protocolSection.identification_module.brief-title]']);
    $this->assertSame('Cognition and QoL After Thyroid Surgery', $replacements['[node:clinical-trials-gov:BriefTitle]']);
    $this->assertSame('Cognition and QoL After Thyroid Surgery', $replacements['[node:clinical-trials-gov:brieftitle]']);
    $this->assertSame('Cognition and QoL After Thyroid Surgery', $replacements['[node:clinical-trials-gov:brief_title]']);
    $this->assertSame('Cognition and QoL After Thyroid Surgery', $replacements['[node:clinical-trials-gov:brief-title]']);

    // Check that non-scalar values are returned as pretty-printed JSON.
    $this->assertSame(implode("\n", [
      '[',
      '    "Thyroid Nodule",',
      '    "Thyroid Cancer",',
      '    "Cognitive Decline",',
      '    "Survivorship",',
      '    "Symptoms, Cognitive"',
      ']',
    ]), $replacements['[node:clinical-trials-gov:protocolSection.conditionsModule.conditions]']);

    // Check that unknown values resolve to an empty string.
    $this->assertSame('', $replacements['[node:clinical-trials-gov:unknown-piece]']);

    // Check that token info includes a metadata-report link when the report module is installed.
    $token_info = $this->container->get('token')->getInfo()['tokens']['node']['clinical-trials-gov'] ?? [];
    $this->assertInstanceOf(TranslatableMarkup::class, $token_info['description'] ?? NULL);
    $this->assertStringContainsString('/admin/reports/status/clinical-trials-gov/metadata', (string) ($token_info['description'] ?? ''));

    $node_without_nct_id = Node::create([
      'type' => 'trial',
      'title' => 'ClinicalTrials.gov token missing NCT ID',
    ]);
    $node_without_nct_id->save();

    $missing_nct_replacements = $this->container->get('token')->generate('node', $this->buildTokenInput([
      'clinical-trials-gov:BriefTitle',
    ]), ['node' => $node_without_nct_id], [], new BubbleableMetadata());

    // Check that nodes without an NCT ID resolve to an empty string.
    $this->assertSame('', $missing_nct_replacements['[node:clinical-trials-gov:BriefTitle]']);
  }

  /**
   * Builds a raw token input map keyed by token name.
   */
  protected function buildTokenInput(array $token_names): array {
    $tokens = [];

    foreach ($token_names as $token_name) {
      $tokens[$token_name] = '[node:' . $token_name . ']';
    }

    return $tokens;
  }

}
