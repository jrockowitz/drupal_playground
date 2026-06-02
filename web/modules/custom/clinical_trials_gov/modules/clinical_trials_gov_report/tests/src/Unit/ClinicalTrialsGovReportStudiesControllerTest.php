<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov_report\Unit;

use Drupal\clinical_trials_gov\ClinicalTrialsGovBuilderInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovStudyManagerInterface;
use Drupal\clinical_trials_gov_report\Controller\ClinicalTrialsGovReportStudiesController;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for ClinicalTrialsGovReportStudiesController.
 *
 * @coversDefaultClass \Drupal\clinical_trials_gov_report\Controller\ClinicalTrialsGovReportStudiesController
 * @group clinical_trials_gov_report
 */
#[Group('clinical_trials_gov_report')]
class ClinicalTrialsGovReportStudiesControllerTest extends UnitTestCase {

  /**
   * The controller under test.
   */
  protected TestClinicalTrialsGovReportStudiesController $controller;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $study_manager = $this->createMock(ClinicalTrialsGovStudyManagerInterface::class);
    $builder = $this->createMock(ClinicalTrialsGovBuilderInterface::class);
    $date_formatter = $this->createMock(DateFormatterInterface::class);
    $date_formatter->method('format')
      ->willReturnCallback(fn($timestamp) => date('F j Y', $timestamp));

    $this->controller = new TestClinicalTrialsGovReportStudiesController($date_formatter, $builder, $study_manager);
    $this->controller->setStringTranslation($this->getStringTranslationStub());
  }

  /**
   * Tests that NULL and empty string default to 'true'.
   *
   * @covers ::normalizeCountTotal
   */
  public function testNormalizeCountTotalDefaultsToTrue(): void {
    // Check that NULL (not provided in URL) defaults to requesting a total count.
    $this->assertSame('true', $this->controller->exposedNormalizeCountTotal(NULL));
    $this->assertSame('true', $this->controller->exposedNormalizeCountTotal(''));
  }

  /**
   * Tests that recognized truthy values map to 'true'.
   *
   * @covers ::normalizeCountTotal
   */
  public function testNormalizeCountTotalTruthyValues(): void {
    // Check that each recognized truthy representation maps to 'true'.
    $this->assertSame('true', $this->controller->exposedNormalizeCountTotal('1'));
    $this->assertSame('true', $this->controller->exposedNormalizeCountTotal('true'));
    $this->assertSame('true', $this->controller->exposedNormalizeCountTotal('yes'));
    $this->assertSame('true', $this->controller->exposedNormalizeCountTotal(TRUE));
  }

  /**
   * Tests that recognized falsy values and unknown values map to 'false'.
   *
   * @covers ::normalizeCountTotal
   */
  public function testNormalizeCountTotalFalsyValues(): void {
    // Check that recognized falsy representations map to 'false'.
    $this->assertSame('false', $this->controller->exposedNormalizeCountTotal('0'));
    $this->assertSame('false', $this->controller->exposedNormalizeCountTotal('false'));
    $this->assertSame('false', $this->controller->exposedNormalizeCountTotal('no'));
    $this->assertSame('false', $this->controller->exposedNormalizeCountTotal(FALSE));
    // Check that an unrecognized value also maps to 'false'.
    $this->assertSame('false', $this->controller->exposedNormalizeCountTotal('maybe'));
  }

  /**
   * Tests that version markup formats required version data.
   *
   * @covers ::buildVersionMarkup
   */
  public function testBuildVersionMarkupFormatsRequiredVersionData(): void {
    $markup = $this->controller->exposedBuildVersionMarkup([
      'apiVersion' => '2.0.5',
      'dataTimestamp' => '2024-01-02T03:04:05',
    ]);

    // Check that valid version contract data is formatted for display.
    $this->assertStringContainsString('Version: 2.0.5', $markup);
    $this->assertStringContainsString('January 2 2024', $markup);
  }

}

/**
 * Testable studies controller subclass.
 */
class TestClinicalTrialsGovReportStudiesController extends ClinicalTrialsGovReportStudiesController {

  /**
   * Exposes normalizeCountTotal() for testing.
   */
  public function exposedNormalizeCountTotal(mixed $value): string {
    return $this->normalizeCountTotal($value);
  }

  /**
   * Exposes buildVersionMarkup() for testing.
   */
  public function exposedBuildVersionMarkup(array $version): string {
    return $this->buildVersionMarkup($version);
  }

}
