<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for ClinicalTrialsGovSettingsForm helper methods.
 *
 * @coversDefaultClass \Drupal\clinical_trials_gov\Form\ClinicalTrialsGovSettingsForm
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovSettingsFormTest extends UnitTestCase {

  /**
   * The form under test.
   */
  protected TestClinicalTrialsGovSettingsForm $form;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $typed_config_manager = $this->createMock(TypedConfigManagerInterface::class);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $module_handler = $this->createMock(ModuleHandlerInterface::class);

    $this->form = new TestClinicalTrialsGovSettingsForm(
      $config_factory,
      $typed_config_manager,
      $entity_type_manager,
      $module_handler,
    );
  }

  /**
   * Tests newline formatting for required paths.
   *
   * @covers ::formatRequiredPaths
   */
  public function testFormatRequiredPaths(): void {
    // Check that saved arrays are shown one path per line.
    $this->assertSame(
      "first.path\nsecond.path",
      $this->form->exposedFormatRequiredPaths(['first.path', 'second.path'])
    );
  }

  /**
   * Tests textarea parsing for required paths.
   *
   * @covers ::parseRequiredPaths
   */
  public function testParseRequiredPaths(): void {
    // Check that blank lines are removed and surrounding whitespace is trimmed.
    $this->assertSame([
      'first.path',
      'second.path',
      'third.path',
    ], $this->form->exposedParseRequiredPaths(" first.path \n\nsecond.path\r\n third.path "));
  }

}
