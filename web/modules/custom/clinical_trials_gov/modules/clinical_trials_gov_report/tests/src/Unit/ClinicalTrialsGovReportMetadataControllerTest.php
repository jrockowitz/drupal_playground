<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov_report\Unit;

use Drupal\clinical_trials_gov\ClinicalTrialsGovFieldManagerInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovManagerInterface;
use Drupal\clinical_trials_gov_report\Controller\ClinicalTrialsGovReportMetadataController;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for ClinicalTrialsGovReportMetadataController.
 *
 * @coversDefaultClass \Drupal\clinical_trials_gov_report\Controller\ClinicalTrialsGovReportMetadataController
 * @group clinical_trials_gov_report
 */
#[Group('clinical_trials_gov_report')]
class ClinicalTrialsGovReportMetadataControllerTest extends UnitTestCase {

  /**
   * The controller under test.
   *
   * @var \Drupal\clinical_trials_gov_report\Controller\ClinicalTrialsGovReportMetadataController
   */
  protected $controller;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $field_manager = $this->createMock(ClinicalTrialsGovFieldManagerInterface::class);
    $manager = $this->createMock(ClinicalTrialsGovManagerInterface::class);
    $date_formatter = $this->createMock(DateFormatterInterface::class);

    $this->controller = new class($field_manager, $manager, $date_formatter) extends ClinicalTrialsGovReportMetadataController {

      /**
       * Exposes buildMetadataTable() for testing.
       */
      public function exposedBuildMetadataTable(array $metadata, array $used_paths = []): array {
        return $this->buildMetadataTable($metadata, $used_paths);
      }

    };
    $this->controller->setStringTranslation($this->getStringTranslationStub());
  }

  /**
   * Tests that used and unused metadata rows are classified correctly.
   *
   * @covers ::buildMetadataTable
   */
  public function testBuildMetadataTableMarksUnusedRows(): void {
    $metadata = [
      'protocolSection' => [
        'path' => 'protocolSection',
        'name' => 'protocolSection',
        'piece' => 'ProtocolSection',
        'title' => 'Protocol Section',
        'sourceType' => 'STRUCT',
        'type' => 'ProtocolSection',
        'maxChars' => NULL,
        'altPieceNames' => [],
        'synonyms' => FALSE,
        'description' => '',
        'rules' => '',
        'dedLinkLabel' => '',
        'dedLinkUrl' => '',
      ],
      'protocolSection.identificationModule' => [
        'path' => 'protocolSection.identificationModule',
        'name' => 'identificationModule',
        'piece' => 'IdentificationModule',
        'title' => 'Identification Module',
        'sourceType' => 'STRUCT',
        'type' => 'IdentificationModule',
        'maxChars' => NULL,
        'altPieceNames' => [],
        'synonyms' => FALSE,
        'description' => '',
        'rules' => '',
        'dedLinkLabel' => '',
        'dedLinkUrl' => '',
      ],
      'protocolSection.identificationModule.briefTitle' => [
        'path' => 'protocolSection.identificationModule.briefTitle',
        'name' => 'briefTitle',
        'piece' => 'BriefTitle',
        'title' => 'Brief Title',
        'sourceType' => 'TEXT',
        'type' => 'text',
        'maxChars' => 300,
        'altPieceNames' => ['BRIEF-TITLE'],
        'synonyms' => FALSE,
        'description' => '',
        'rules' => '',
        'dedLinkLabel' => '',
        'dedLinkUrl' => '',
      ],
    ];

    $table = $this->controller->exposedBuildMetadataTable($metadata, [
      'protocolSection',
      'protocolSection.identificationModule.briefTitle',
    ]);

    // Check that used rows are not dimmed.
    $this->assertSame([], $table['#rows'][0]['class']);
    $this->assertSame([], $table['#rows'][2]['class']);

    // Check that an unused row gets the muted row class.
    $this->assertSame(['clinical-trials-gov-report-metadata__row--unused'], $table['#rows'][1]['class']);
  }

  /**
   * Tests that no rows are dimmed when no paths are configured.
   *
   * @covers ::buildMetadataTable
   */
  public function testBuildMetadataTableWithoutUsedPathsLeavesRowsNormal(): void {
    $metadata = [
      'protocolSection' => [
        'path' => 'protocolSection',
        'name' => 'protocolSection',
        'piece' => 'ProtocolSection',
        'title' => 'Protocol Section',
        'sourceType' => 'STRUCT',
        'type' => 'ProtocolSection',
        'maxChars' => NULL,
        'altPieceNames' => [],
        'synonyms' => FALSE,
        'description' => '',
        'rules' => '',
        'dedLinkLabel' => '',
        'dedLinkUrl' => '',
      ],
    ];

    $table = $this->controller->exposedBuildMetadataTable($metadata, []);

    // Check that no rows are marked unused without an allow-list.
    $this->assertSame([], $table['#rows'][0]['class']);
  }

}
