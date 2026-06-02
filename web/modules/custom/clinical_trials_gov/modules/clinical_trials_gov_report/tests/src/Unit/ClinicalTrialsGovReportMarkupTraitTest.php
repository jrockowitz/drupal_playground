<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov_report\Unit;

use Drupal\clinical_trials_gov_report\Traits\ClinicalTrialsGovReportMarkupTrait;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for ClinicalTrialsGovReportMarkupTrait.
 *
 * @coversDefaultClass \Drupal\clinical_trials_gov_report\Traits\ClinicalTrialsGovReportMarkupTrait
 * @group clinical_trials_gov_report
 */
#[Group('clinical_trials_gov_report')]
class ClinicalTrialsGovReportMarkupTraitTest extends UnitTestCase {

  /**
   * Tests that version markup formats required API version data.
   *
   * @covers ::buildVersionMarkup
   */
  public function testBuildVersionMarkupFormatsRequiredVersionData(): void {
    $date_formatter = $this->createMock(DateFormatterInterface::class);
    $date_formatter->expects($this->once())
      ->method('format')
      ->with(strtotime('2024-01-02T03:04:05 UTC'), 'custom', 'F j Y \a\t g:i a')
      ->willReturn('January 2 2024 at 3:04 am');
    $subject = $this->createTraitTestSubject($date_formatter);

    $markup = $subject->exposedBuildVersionMarkup([
      'apiVersion' => '2.0.5',
      'dataTimestamp' => '2024-01-02T03:04:05',
    ]);

    // Check that required API version data is rendered with formatted output.
    $this->assertStringContainsString('Version: 2.0.5', $markup);
    $this->assertStringContainsString('January 2 2024 at 3:04 am', $markup);
  }

  /**
   * Tests that text cells escape markup and preserve line breaks.
   *
   * @covers ::buildTextCell
   */
  public function testBuildTextCellEscapesMarkupAndPreservesLineBreaks(): void {
    $subject = $this->createTraitTestSubject();

    $cell = $subject->exposedBuildTextCell("<script>alert('x')</script>\nNext line");

    // Check that text cell markup is escaped and line breaks are preserved.
    $this->assertIsArray($cell);
    $this->assertStringContainsString('&lt;script&gt;alert', (string) $cell['data']['#markup']);
    $this->assertStringContainsString('<br', (string) $cell['data']['#markup']);
  }

  /**
   * Tests that empty text cells return an empty string.
   *
   * @covers ::buildTextCell
   */
  public function testBuildTextCellReturnsEmptyStringForEmptyValue(): void {
    $subject = $this->createTraitTestSubject();

    // Check that empty text is not rendered into a table cell structure.
    $this->assertSame('', $subject->exposedBuildTextCell(''));
  }

  /**
   * Tests that list cells filter values and render newline-separated text.
   *
   * @covers ::buildListCell
   */
  public function testBuildListCellFiltersValuesAndRendersNewlines(): void {
    $subject = $this->createTraitTestSubject();

    $cell = $subject->exposedBuildListCell(['Alpha', 2, ['ignored'], 'Gamma']);

    // Check that non-scalar values are ignored and scalar values are rendered.
    $this->assertIsArray($cell);
    $this->assertStringContainsString('Alpha', (string) $cell['data']['#markup']);
    $this->assertStringContainsString('2', (string) $cell['data']['#markup']);
    $this->assertStringContainsString('Gamma', (string) $cell['data']['#markup']);
    $this->assertStringContainsString('<br', (string) $cell['data']['#markup']);
    $this->assertStringNotContainsString('ignored', (string) $cell['data']['#markup']);
  }

  /**
   * Tests that empty list cells return an empty string.
   *
   * @covers ::buildListCell
   */
  public function testBuildListCellReturnsEmptyStringForEmptyList(): void {
    $subject = $this->createTraitTestSubject();

    // Check that an empty list does not render a cell structure.
    $this->assertSame('', $subject->exposedBuildListCell([]));
  }

  /**
   * Creates a trait test subject with the shared controller dependencies.
   */
  protected function createTraitTestSubject(?DateFormatterInterface $date_formatter = NULL): object {
    $date_formatter ??= $this->createMock(DateFormatterInterface::class);
    $subject = new class($date_formatter) {
      use ClinicalTrialsGovReportMarkupTrait;
      use StringTranslationTrait;

      /**
       * Constructs a test subject for the report markup trait.
       */
      public function __construct(
        protected DateFormatterInterface $dateFormatter,
      ) {}

      /**
       * Exposes buildVersionMarkup() for testing.
       */
      public function exposedBuildVersionMarkup(array $version): string {
        return $this->buildVersionMarkup($version);
      }

      /**
       * Exposes buildTextCell() for testing.
       */
      public function exposedBuildTextCell(string $value): array|string {
        return $this->buildTextCell($value);
      }

      /**
       * Exposes buildListCell() for testing.
       */
      public function exposedBuildListCell(mixed $values): array|string {
        return $this->buildListCell($values);
      }

    };
    $subject->setStringTranslation($this->getStringTranslationStub());

    return $subject;
  }

}
