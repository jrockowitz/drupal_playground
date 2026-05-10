<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Unit\Plugin\migrate\process;

use Drupal\clinical_trials_gov\Plugin\migrate\process\ClinicalTrialsGovCustomField;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for the ClinicalTrials.gov custom field process plugin.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovCustomFieldTest extends UnitTestCase {

  /**
   * Tests remapping and YAML fallback transformation for custom fields.
   */
  public function testTransform(): void {
    $plugin = new ClinicalTrialsGovCustomField([], 'clinical_trials_gov_custom_field', []);

    $conditions_value = $plugin->transform(
      [
        [
          'conditions' => [
            'Colorectal Cancer',
          ],
          'keywords' => [
            'recurrent colon cancer',
          ],
        ],
        [],
        [
          'conditions' => 'cond',
          'keywords' => 'keyword',
        ],
      ],
      $this->createMock(MigrateExecutableInterface::class),
      $this->createMock(Row::class),
      'field_trial_cond_mod'
    );

    // Check that associative source keys are remapped to custom field keys.
    $this->assertSame([
      'cond' => [
        'Colorectal Cancer',
      ],
      'keyword' => [
        'recurrent colon cancer',
      ],
    ], $conditions_value);

    $locations_value = [
      [
        'facility' => 'Universitatsklinikum Carl Gustav Carus',
        'contacts' => [
          [
            'name' => 'Alice Example',
            'phone' => '111-111',
          ],
        ],
        'geoPoint' => [
          'lat' => 51.05089,
          'lon' => 13.73832,
        ],
      ],
    ];

    $locations_prepared_value = $plugin->transform(
      [
        $locations_value,
        ['contact', 'geo_point'],
        [
          'contacts' => 'contact',
          'geoPoint' => 'geo_point',
        ],
      ],
      $this->createMock(MigrateExecutableInterface::class),
      $this->createMock(Row::class),
      'field_trial_location'
    );

    // Check that remapped nested values are serialized to YAML.
    $this->assertIsArray($locations_prepared_value);
    $this->assertSame('Universitatsklinikum Carl Gustav Carus', $locations_prepared_value[0]['facility']);
    $this->assertIsString($locations_prepared_value[0]['contact']);
    $this->assertStringContainsString('Alice Example', $locations_prepared_value[0]['contact']);
    $this->assertStringContainsString("- name: 'Alice Example'", $locations_prepared_value[0]['contact']);
    $this->assertStringContainsString('phone: 111-111', $locations_prepared_value[0]['contact']);
    $this->assertIsString($locations_prepared_value[0]['geo_point']);
    $this->assertStringContainsString('lat: 51.05089', $locations_prepared_value[0]['geo_point']);
    $this->assertStringContainsString('lon: 13.73832', $locations_prepared_value[0]['geo_point']);

    $description_value = $plugin->transform(
      [
        [
          'briefSummary' => 'Summary text.',
          'eligibilityCriteria' => 'Criteria text.',
        ],
        [],
        [
          'briefSummary' => 'brief_summary',
          'eligibilityCriteria' => 'elig_criteria',
        ],
      ],
      $this->createMock(MigrateExecutableInterface::class),
      $this->createMock(Row::class),
      'field_trial_desc_mod'
    );

    // Check that non-list source structs are remapped to destination keys.
    $this->assertSame([
      'brief_summary' => 'Summary text.',
      'elig_criteria' => 'Criteria text.',
    ], $description_value);
  }

}
