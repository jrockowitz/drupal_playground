<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov_report\Unit;

use Drupal\clinical_trials_gov\ClinicalTrialsGovBuilderInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovStudyManagerInterface;
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

    $this->controller = new TestClinicalTrialsGovReportStudiesController($study_manager, $builder, $date_formatter);
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
   * Tests that a malformed timestamp is displayed as-is.
   *
   * @covers ::buildVersionMarkup
   */
  public function testBuildVersionMarkupWithMalformedTimestamp(): void {
    $markup = $this->controller->exposedBuildVersionMarkup([
      'apiVersion' => '2.0.5',
      'dataTimestamp' => 'not-a-valid-date',
    ]);

    // Check that a malformed timestamp is output raw rather than formatted.
    $this->assertStringContainsString('not-a-valid-date', $markup);
    $this->assertStringContainsString('2.0.5', $markup);
  }

  /**
   * Tests that an empty version array produces valid markup without errors.
   *
   * @covers ::buildVersionMarkup
   */
  public function testBuildVersionMarkupWithEmptyVersion(): void {
    $markup = $this->controller->exposedBuildVersionMarkup([]);

    // Check that missing keys produce an empty but structurally valid markup string.
    $this->assertNotEmpty($markup);
  }

}
