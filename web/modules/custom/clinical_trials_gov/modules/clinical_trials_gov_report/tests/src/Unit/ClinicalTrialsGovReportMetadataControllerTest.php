<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov_report\Unit;

use Drupal\clinical_trials_gov\ClinicalTrialsGovManagerInterface;
use Drupal\clinical_trials_gov_report\Controller\ClinicalTrialsGovReportMetadataController;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Messenger\MessengerInterface;
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
   * @var mixed
   */
  protected $controller;

  /**
   * The metadata manager mock.
   *
   * @var \Drupal\clinical_trials_gov\ClinicalTrialsGovManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $manager;

  /**
   * The date formatter mock.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $dateFormatter;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->manager = $this->createMock(ClinicalTrialsGovManagerInterface::class);
    $this->dateFormatter = $this->createMock(DateFormatterInterface::class);

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $messenger = $this->createMock(MessengerInterface::class);

    $this->controller = new class($this->manager, $config_factory, $messenger, $this->dateFormatter) extends ClinicalTrialsGovReportMetadataController {

      /**
       * Exposes buildMetadataTable() for testing.
       */
      public function exposedBuildMetadataTable(array $metadata): array {
        return $this->buildMetadataTable($metadata);
      }

    };
    $this->controller->setStringTranslation($this->getStringTranslationStub());
  }

  /**
   * Tests that the report metadata table keeps all rows.
   *
   * @covers ::buildMetadataTable
   */
  public function testBuildMetadataTableKeepsAllRows(): void {
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
        'description' => 'Required for all trials.',
        'rules' => 'Has to be unique in PRS.',
        'dedLinkLabel' => 'Brief Title',
        'dedLinkUrl' => 'https://clinicaltrials.gov/policy/protocol-definitions#BriefTitle',
      ],
    ];

    $table = $this->controller->exposedBuildMetadataTable($metadata);

    // Check that the full report renders all metadata rows without filtering.
    $this->assertCount(2, $table['#rows']);
    $this->assertSame([], $table['#rows'][0]['class']);
    $this->assertSame([], $table['#rows'][1]['class']);

    // Check that the field title column combines description and notes.
    $this->assertCount(6, $table['#header']);
    $this->assertStringContainsString('Description/Notes/Definition', (string) $table['#header'][1]['data']);
    $this->assertSame('Path', (string) $table['#header'][2]);
    $this->assertSame('Alt Piece Names', (string) $table['#header'][5]);
    $this->assertStringContainsString('Required for all trials.', (string) $table['#rows'][1]['data'][1]['data']);
    $this->assertStringContainsString('Has to be unique in PRS.', (string) $table['#rows'][1]['data'][1]['data']);
    $this->assertStringContainsString('https://clinicaltrials.gov/policy/protocol-definitions#BriefTitle', (string) $table['#rows'][1]['data'][1]['data']);
    $this->assertStringNotContainsString('<strong>protocolSection.identificationModule.briefTitle</strong>', (string) $table['#rows'][1]['data'][2]['data']);
    $this->assertStringContainsString('<small>protocolSection.identificationModule.briefTitle</small>', (string) $table['#rows'][1]['data'][2]['data']);
    $this->assertStringContainsString('BRIEF-TITLE', (string) $table['#rows'][1]['data'][5]['data']['#markup']);
  }

  /**
   * Tests that the report page includes the report-only footer details.
   */
  public function testIndexIncludesApiAndVersionFooter(): void {
    $this->manager->method('getMetadataByPath')
      ->willReturn([
        'protocolSection.identificationModule.briefTitle' => [
          'path' => 'protocolSection.identificationModule.briefTitle',
          'name' => 'briefTitle',
          'piece' => 'BriefTitle',
          'title' => 'Brief Title',
          'sourceType' => 'TEXT',
          'type' => 'text',
          'maxChars' => 300,
          'altPieceNames' => [],
          'synonyms' => FALSE,
          'description' => '',
          'rules' => '',
          'dedLinkLabel' => '',
          'dedLinkUrl' => '',
        ],
      ]);
    $this->manager->method('getVersion')
      ->willReturn([
        'apiVersion' => '2.0.0',
        'dataTimestamp' => '2024-01-02T03:04:05',
      ]);
    $this->dateFormatter->method('format')
      ->willReturn('January 2 2024 at 3:04 am');

    $build = $this->controller->index();

    // Check that the report footer still includes the API URL and version.
    $this->assertArrayHasKey('footer', $build);
    $this->assertArrayHasKey('api_url', $build['footer']);
    $this->assertArrayHasKey('version', $build['footer']);
    $this->assertStringContainsString('/studies/metadata', (string) $build['footer']['api_url']['#markup']);
    $this->assertStringContainsString('Version: 2.0.0', (string) $build['footer']['version']['#markup']);
  }

}
