<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld\Unit;

use Drupal\ai_schemadotorg_jsonld\Form\AiSchemaDotOrgJsonLdSettingsForm;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Tests\UnitTestCase;

/**
 * Tests AiSchemaDotOrgJsonLdSettingsForm.
 *
 * @group ai_schemadotorg_jsonld
 */
class AiSchemaDotOrgJsonLdSettingsFormTest extends UnitTestCase {

  /**
   * Tests that validateJson allows NULL values.
   */
  public function testValidateJsonAllowsNullValue(): void {
    $form = new AiSchemaDotOrgJsonLdSettingsForm(
      $this->createMock(ConfigFactoryInterface::class),
      $this->createMock(TypedConfigManagerInterface::class),
    );
    $form->setStringTranslation($this->getStringTranslationStub());
    $form_state = new FormState();
    $element = [
      '#value' => NULL,
    ];

    $form->validateJson($element, $form_state);

    $this->assertFalse($form_state->hasAnyErrors(), 'NULL JSON values do not trigger validation errors.');
  }

}
