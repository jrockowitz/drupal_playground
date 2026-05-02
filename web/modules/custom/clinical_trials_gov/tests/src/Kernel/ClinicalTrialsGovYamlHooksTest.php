<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\clinical_trials_gov\Hook\ClinicalTrialsGovCustomFieldHooks;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Render\RendererInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for YAML-related ClinicalTrials.gov hooks.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovYamlHooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'clinical_trials_gov',
    'clinical_trials_gov_test',
    'node',
    'field',
    'text',
    'link',
    'options',
    'datetime',
    'filter',
    'user',
    'system',
    'migrate',
    'migrate_plus',
    'migrate_tools',
    'custom_field',
    'field_group',
  ];

  /**
   * The hook implementation under test.
   */
  protected ClinicalTrialsGovCustomFieldHooks $hooks;

  /**
   * The renderer.
   */
  protected RendererInterface $renderer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig(['field', 'filter', 'node', 'system']);
    $this->installSchema('node', ['node_access']);
    $this->container->get('config.factory')->getEditable('clinical_trials_gov.settings')
      ->set('field_prefix', 'trial_version_holder')
      ->save();

    $this->hooks = new ClinicalTrialsGovCustomFieldHooks(
      $this->container->get('config.factory'),
    );
    $this->renderer = $this->container->get('renderer');
  }

  /**
   * Tests YAML values render inside pre tags for matching custom field items.
   */
  public function testPreprocessCustomFieldItem(): void {
    $variables = [
      'elements' => [
        '#field_name' => 'trial_version_holder_location',
      ],
      'label' => 'Facility Contact (YAML)',
      'value' => [
        '#markup' => "name: Alice\nphone: 111-111\n",
      ],
    ];
    $this->hooks->preprocessCustomFieldItem($variables);

    // Check that matching YAML values are wrapped in pre tags.
    $this->assertIsArray($variables['value']);
    $this->assertSame('html_tag', $variables['value']['#type']);
    $this->assertSame('pre', $variables['value']['#tag']);
    $rendered_value = (string) $this->renderer->renderRoot($variables['value']);
    $this->assertStringContainsString('<pre', $rendered_value);
    $this->assertStringContainsString('name: Alice', $rendered_value);

    $non_yaml_variables = [
      'elements' => [
        '#field_name' => 'trial_version_holder_location',
      ],
      'label' => 'Facility Name',
      'value' => [
        '#markup' => 'Karolinska University Hospital',
      ],
    ];
    $this->hooks->preprocessCustomFieldItem($non_yaml_variables);

    // Check that non-YAML labels are left alone.
    $this->assertSame([
      '#markup' => 'Karolinska University Hospital',
    ], $non_yaml_variables['value']);

    $non_clinical_trials_gov_variables = [
      'elements' => [
        '#field_name' => 'field_location',
      ],
      'label' => 'Facility Contact (YAML)',
      'value' => [
        '#markup' => "name: Alice\n",
      ],
    ];
    $this->hooks->preprocessCustomFieldItem($non_clinical_trials_gov_variables);

    // Check that non-ClinicalTrials.gov fields are left alone.
    $this->assertSame([
      '#markup' => "name: Alice\n",
    ], $non_clinical_trials_gov_variables['value']);
  }

  /**
   * Tests YAML validation is attached only to matching widget elements.
   */
  public function testFieldWidgetSingleElementFormAlter(): void {
    $matching_element = [
      'contacts' => [
        '#type' => 'textarea',
        '#title' => 'Facility Contact (YAML)',
        '#parents' => ['trial_version_holder_location', 0, 'contacts'],
        '#value' => 'contacts: [',
      ],
      'geoPoint' => [
        '#type' => 'text_format',
        '#title' => 'Location Geo Point (YAML)',
        'value' => [
          '#type' => 'textarea',
          '#parents' => ['trial_version_holder_location', 0, 'geoPoint', 'value'],
          '#value' => "lat: 59.32938\nlon: 18.06871\n",
        ],
      ],
      'facility' => [
        '#type' => 'textarea',
        '#title' => 'Facility Name',
        '#parents' => ['trial_version_holder_location', 0, 'facility'],
        '#value' => 'Karolinska University Hospital',
      ],
    ];
    $this->hooks->fieldWidgetSingleElementFormAlter($matching_element, new FormState(), [
      'items' => $this->createFieldItemListMock('trial_version_holder_location'),
    ]);

    // Check that YAML-labeled textarea and text_format value elements get validation.
    $this->assertSame([ClinicalTrialsGovCustomFieldHooks::class, 'validateYamlElement'], $matching_element['contacts']['#element_validate'][0]);
    $this->assertSame([ClinicalTrialsGovCustomFieldHooks::class, 'validateYamlElement'], $matching_element['geoPoint']['value']['#element_validate'][0]);
    $this->assertArrayNotHasKey('#element_validate', $matching_element['facility']);

    $invalid_state = new FormState();
    $element = $matching_element['contacts'];
    ClinicalTrialsGovCustomFieldHooks::validateYamlElement($element, $invalid_state);

    // Check that invalid YAML produces an error.
    $this->assertNotEmpty($invalid_state->getErrors());

    $valid_state = new FormState();
    $element = $matching_element['geoPoint']['value'];
    ClinicalTrialsGovCustomFieldHooks::validateYamlElement($element, $valid_state);

    // Check that valid YAML passes.
    $this->assertSame([], $valid_state->getErrors());

    $empty_state = new FormState();
    $empty_element = [
      '#type' => 'textarea',
      '#title' => 'Facility Contact (YAML)',
      '#parents' => ['trial_version_holder_location', 0, 'contacts'],
      '#value' => '',
    ];
    ClinicalTrialsGovCustomFieldHooks::validateYamlElement($empty_element, $empty_state);

    // Check that empty YAML values are allowed.
    $this->assertSame([], $empty_state->getErrors());

    $non_matching_element = [
      'contacts' => [
        '#type' => 'textarea',
        '#title' => 'Facility Contact (YAML)',
        '#parents' => ['field_location', 0, 'contacts'],
        '#value' => 'contacts: [',
      ],
    ];
    $this->hooks->fieldWidgetSingleElementFormAlter($non_matching_element, new FormState(), [
      'items' => $this->createFieldItemListMock('field_location'),
    ]);

    // Check that non-ClinicalTrials.gov fields are not altered.
    $this->assertArrayNotHasKey('#element_validate', $non_matching_element['contacts']);
  }

  /**
   * Creates a field item list mock for one field name.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface>
   *   A mock field item list.
   */
  protected function createFieldItemListMock(string $field_name): FieldItemListInterface {
    /** @var \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface>&\PHPUnit\Framework\MockObject\MockObject $items */
    $items = $this->createMock(FieldItemListInterface::class);
    $items->method('getName')->willReturn($field_name);
    return $items;
  }

}
