<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov_report\Unit;

use Drupal\clinical_trials_gov\ClinicalTrialsGovStudyManagerInterface;
use Drupal\clinical_trials_gov_report\Controller\ClinicalTrialsGovReportEnumsController;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for ClinicalTrialsGovReportEnumsController.
 *
 * @coversDefaultClass \Drupal\clinical_trials_gov_report\Controller\ClinicalTrialsGovReportEnumsController
 * @group clinical_trials_gov_report
 */
#[Group('clinical_trials_gov_report')]
class ClinicalTrialsGovReportEnumsControllerTest extends UnitTestCase {

  /**
   * The controller under test.
   */
  protected TestClinicalTrialsGovReportEnumsController $controller;

  /**
   * The study manager mock.
   */
  protected ClinicalTrialsGovStudyManagerInterface $studyManager;

  /**
   * The date formatter mock.
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->studyManager = $this->createMock(ClinicalTrialsGovStudyManagerInterface::class);
    $this->dateFormatter = $this->createMock(DateFormatterInterface::class);

    $this->controller = new TestClinicalTrialsGovReportEnumsController($this->dateFormatter, $this->studyManager);
    $this->controller->setStringTranslation($this->getStringTranslationStub());
  }

  /**
   * Tests that the enums report page includes the expected render structure.
   *
   * @covers ::index
   */
  public function testIndexBuildsEnumsReportPage(): void {
    $this->studyManager->method('getEnums')
      ->willReturn([
        [
          'type' => 'Status',
          'values' => [
            [
              'value' => 'COMPLETED',
              'legacyValue' => 'Completed',
            ],
            [
              'value' => 'RECRUITING',
              'legacyValue' => 'Recruiting',
            ],
          ],
          'pieces' => ['OverallStatus', 'LastKnownStatus'],
        ],
      ]);
    $this->studyManager->method('getVersion')
      ->willReturn([
        'apiVersion' => '2.0.0',
        'dataTimestamp' => '2024-01-02T03:04:05',
      ]);
    $this->dateFormatter->method('format')
      ->willReturn('January 2 2024 at 3:04 am');

    $build = $this->controller->index();

    // Check that the page attaches the report library and top-level structure.
    $this->assertSame('container', $build['#type']);
    $this->assertContains('clinical_trials_gov_report/report', $build['#attached']['library']);
    $this->assertContains('clinical-trials-gov-report-enums', $build['#attributes']['class']);

    // Check that the intro, summary, table, API URL, and version output are present.
    $this->assertArrayHasKey('intro', $build);
    $this->assertArrayHasKey('summary', $build);
    $this->assertArrayHasKey('results', $build);
    $this->assertArrayHasKey('api_url', $build);
    $this->assertArrayHasKey('version', $build);
    $this->assertStringContainsString('Showing 1 enum types', (string) $build['summary']['#markup']);
    $this->assertStringContainsString('/studies/enums', (string) $build['api_url']['#markup']);
    $this->assertStringContainsString('Version: 2.0.0', (string) $build['version']['#markup']);

    // Check that the results table renders the expected headers and values.
    $this->assertSame('table', $build['results']['#type']);
    $this->assertSame('Enum Type', (string) $build['results']['#header'][0]);
    $this->assertSame('Values', (string) $build['results']['#header'][1]);
    $this->assertSame('Pieces', (string) $build['results']['#header'][2]);
    $this->assertCount(1, $build['results']['#rows']);
    $this->assertSame('Status', (string) $build['results']['#rows'][0][0]['data']['#markup']);
    $this->assertStringContainsString('Completed (COMPLETED)', (string) $build['results']['#rows'][0][1]['data']['#markup']);
    $this->assertStringContainsString('Recruiting (RECRUITING)', (string) $build['results']['#rows'][0][1]['data']['#markup']);
    $this->assertStringContainsString('OverallStatus', (string) $build['results']['#rows'][0][2]['data']['#markup']);
    $this->assertStringContainsString('LastKnownStatus', (string) $build['results']['#rows'][0][2]['data']['#markup']);
  }

  /**
   * Tests that enum values are formatted and filtered for display.
   *
   * @covers ::buildValuesCell
   */
  public function testBuildValuesCellFormatsValues(): void {
    $cell = $this->controller->exposedBuildValuesCell([
      [
        'value' => 'COMPLETED',
        'legacyValue' => 'Completed',
      ],
      [
        'value' => 'UNKNOWN',
      ],
      [
        'legacyValue' => '',
        'value' => '',
      ],
      'ignored',
    ]);

    // Check that valid enum values are rendered and invalid entries are skipped.
    $this->assertIsArray($cell);
    $this->assertStringContainsString('Completed (COMPLETED)', (string) $cell['data']['#markup']);
    $this->assertStringContainsString('UNKNOWN', (string) $cell['data']['#markup']);
    $this->assertStringContainsString('<br', (string) $cell['data']['#markup']);
    $this->assertStringNotContainsString('ignored', (string) $cell['data']['#markup']);

    // Check that empty or invalid values produce no rendered output.
    $this->assertSame('', $this->controller->exposedBuildValuesCell([]));
    $this->assertSame('', $this->controller->exposedBuildValuesCell('not-an-array'));
  }

}

/**
 * Testable enums controller subclass.
 */
class TestClinicalTrialsGovReportEnumsController extends ClinicalTrialsGovReportEnumsController {

  /**
   * Exposes buildValuesCell() for testing.
   */
  public function exposedBuildValuesCell(mixed $values): array|string {
    return $this->buildValuesCell($values);
  }

}
