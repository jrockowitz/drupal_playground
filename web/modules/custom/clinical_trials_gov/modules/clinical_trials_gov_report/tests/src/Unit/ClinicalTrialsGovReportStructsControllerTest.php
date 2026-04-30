<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov_report\Unit;

use Drupal\clinical_trials_gov\ClinicalTrialsGovFieldManagerInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovManagerInterface;
use Drupal\clinical_trials_gov_report\Controller\ClinicalTrialsGovReportStructsController;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for ClinicalTrialsGovReportStructsController.
 *
 * @coversDefaultClass \Drupal\clinical_trials_gov_report\Controller\ClinicalTrialsGovReportStructsController
 * @group clinical_trials_gov_report
 */
#[Group('clinical_trials_gov_report')]
class ClinicalTrialsGovReportStructsControllerTest extends UnitTestCase {

  /**
   * The controller under test.
   *
   * @var \Drupal\clinical_trials_gov_report\Controller\ClinicalTrialsGovReportStructsController
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

    $this->controller = new class($field_manager, $manager, $date_formatter) extends ClinicalTrialsGovReportStructsController {

      /**
       * Exposes buildStructRows() for testing.
       */
      public function exposedBuildStructRows(array $metadata, array $used_paths = []): array {
        return $this->buildStructRows($metadata, $used_paths);
      }

      /**
       * Exposes buildStructsTable() for testing.
       */
      public function exposedBuildStructsTable(array $struct_rows): array {
        return $this->buildStructsTable($struct_rows);
      }

      /**
       * Exposes buildSubPropertiesCell() for testing.
       */
      public function exposedBuildSubPropertiesCell(array $values): array|string {
        return $this->buildSubPropertiesCell($values);
      }

    };
    $this->controller->setStringTranslation($this->getStringTranslationStub());
  }

  /**
   * Tests that only struct rows are included and multiplicity is derived.
   *
   * @covers ::buildStructRows
   */
  public function testBuildStructRowsFiltersAndFlagsMultipleStructs(): void {
    $metadata = [
      'protocolSection' => [
        'path' => 'protocolSection',
        'parent' => '',
        'name' => 'protocolSection',
        'piece' => 'ProtocolSection',
        'title' => 'Protocol Section',
        'type' => 'ProtocolSection',
        'sourceType' => 'STRUCT',
        'children' => ['protocolSection.identificationModule'],
      ],
      'protocolSection.identificationModule' => [
        'path' => 'protocolSection.identificationModule',
        'parent' => 'protocolSection',
        'name' => 'identificationModule',
        'piece' => 'IdentificationModule',
        'title' => 'Identification Module',
        'type' => 'IdentificationModule',
        'sourceType' => 'STRUCT',
        'children' => ['protocolSection.identificationModule.briefTitle'],
      ],
      'protocolSection.identificationModule.briefTitle' => [
        'path' => 'protocolSection.identificationModule.briefTitle',
        'parent' => 'protocolSection.identificationModule',
        'name' => 'briefTitle',
        'piece' => 'BriefTitle',
        'title' => 'Brief Title',
        'type' => 'text',
        'sourceType' => 'TEXT',
        'children' => [],
      ],
      'protocolSection.contactsLocationsModule.locations' => [
        'path' => 'protocolSection.contactsLocationsModule.locations',
        'parent' => 'protocolSection.contactsLocationsModule',
        'name' => 'locations',
        'piece' => 'Location',
        'title' => 'Location',
        'type' => 'Location[]',
        'sourceType' => 'STRUCT',
        'children' => [
          'protocolSection.contactsLocationsModule.locations.status',
          'protocolSection.contactsLocationsModule.locations.contacts',
        ],
      ],
      'protocolSection.contactsLocationsModule.locations.status' => [
        'path' => 'protocolSection.contactsLocationsModule.locations.status',
        'parent' => 'protocolSection.contactsLocationsModule.locations',
        'name' => 'status',
        'piece' => 'LocationStatus',
        'title' => 'Location Status',
        'type' => 'text',
        'sourceType' => 'TEXT',
        'children' => [],
      ],
      'protocolSection.contactsLocationsModule.locations.contacts' => [
        'path' => 'protocolSection.contactsLocationsModule.locations.contacts',
        'parent' => 'protocolSection.contactsLocationsModule.locations',
        'name' => 'contacts',
        'piece' => 'LocationContact',
        'title' => 'Facility Contact',
        'type' => 'Contact[]',
        'sourceType' => 'STRUCT',
        'children' => ['protocolSection.contactsLocationsModule.locations.contacts.name'],
      ],
      'protocolSection.contactsLocationsModule.locations.contacts.name' => [
        'path' => 'protocolSection.contactsLocationsModule.locations.contacts.name',
        'parent' => 'protocolSection.contactsLocationsModule.locations.contacts',
        'name' => 'name',
        'piece' => 'LocationContactName',
        'title' => 'Location Contact Name',
        'type' => 'text',
        'sourceType' => 'TEXT',
        'children' => [],
      ],
    ];

    $rows = $this->controller->exposedBuildStructRows($metadata, [
      'protocolSection',
      'protocolSection.contactsLocationsModule.locations',
      'protocolSection.contactsLocationsModule.locations.contacts',
    ]);

    // Check that only struct rows are included.
    $this->assertCount(4, $rows);
    $this->assertArrayNotHasKey('protocolSection.identificationModule.briefTitle', $rows);

    // Check that repeatable structs are detected for nested warnings.
    $this->assertFalse($rows['protocolSection']['is_nested_multiple']);
    $this->assertFalse($rows['protocolSection.contactsLocationsModule.locations']['is_nested_multiple']);
    $this->assertTrue($rows['protocolSection.contactsLocationsModule.locations.contacts']['is_nested_multiple']);

    // Check that the nearest struct ancestor is used as the parent struct.
    $this->assertSame('protocolSection.contactsLocationsModule.locations', $rows['protocolSection.contactsLocationsModule.locations.contacts']['parent_struct']);

    // Check that immediate child properties are listed without flattening descendants.
    $this->assertSame([
      [
        'name' => 'status',
        'is_struct' => FALSE,
        'is_multiple' => FALSE,
      ],
      [
        'name' => 'contacts',
        'is_struct' => TRUE,
        'is_multiple' => TRUE,
      ],
    ], $rows['protocolSection.contactsLocationsModule.locations']['sub_properties']);
    $this->assertSame([
      [
        'name' => 'name',
        'is_struct' => FALSE,
        'is_multiple' => FALSE,
      ],
    ], $rows['protocolSection.contactsLocationsModule.locations.contacts']['sub_properties']);

    // Check that used and unused struct rows are flagged correctly.
    $this->assertFalse($rows['protocolSection']['is_unused']);
    $this->assertTrue($rows['protocolSection.identificationModule']['is_unused']);
    $this->assertFalse($rows['protocolSection.contactsLocationsModule.locations']['is_unused']);

    // Check that the table includes a data type column and warning row class.
    $table = $this->controller->exposedBuildStructsTable($rows);
    $this->assertSame('Data type', (string) $table['#header'][2]);
    $this->assertSame('Sub-properties', (string) $table['#header'][3]);
    $this->assertSame('Location[]', $table['#rows'][2]['data'][2]['data']['#markup']);
    $this->assertSame([], $table['#rows'][2]['class']);
    $this->assertSame(['color-warning'], $table['#rows'][3]['class']);
    $this->assertSame(['clinical-trials-gov-report-structs__row--unused'], $table['#rows'][1]['class']);
    $this->assertSame('html_tag', $table['#rows'][2]['data'][0]['data']['primary']['#type']);
    $this->assertSame('div', $table['#rows'][2]['data'][0]['data']['primary']['#tag']);
    $this->assertSame('strong', $table['#rows'][2]['data'][0]['data']['primary']['content']['#tag']);
    $this->assertSame('locations', $table['#rows'][2]['data'][0]['data']['primary']['content']['#value']);
    $this->assertSame('Location', $table['#rows'][2]['data'][0]['data']['secondary']['content']['#markup']);

    // Check that sub-properties call out structs and multiple values.
    $sub_properties_cell = $this->controller->exposedBuildSubPropertiesCell($rows['protocolSection.contactsLocationsModule.locations']['sub_properties']);
    $this->assertSame('status', (string) $sub_properties_cell['data']['#items'][0]);
    $this->assertSame('html_tag', $sub_properties_cell['data']['#items'][1]['#type']);
    $this->assertSame('strong', $sub_properties_cell['data']['#items'][1]['#tag']);
    $this->assertSame('contacts[]', $sub_properties_cell['data']['#items'][1]['#value']);
  }

}
