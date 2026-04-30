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
   * Tests YAML fallback transformation for unsupported nested values.
   */
  public function testTransform(): void {
    $plugin = new ClinicalTrialsGovCustomField([], 'clinical_trials_gov_custom_field', []);

    $value = [
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

    $prepared_value = $plugin->transform(
      [
        $value,
        ['contacts', 'geoPoint'],
      ],
      $this->createMock(MigrateExecutableInterface::class),
      $this->createMock(Row::class),
      'field_trial_location'
    );

    // Check that configured nested values are serialized to YAML.
    $this->assertIsArray($prepared_value);
    $this->assertSame('Universitatsklinikum Carl Gustav Carus', $prepared_value[0]['facility']);
    $this->assertIsString($prepared_value[0]['contacts']);
    $this->assertStringContainsString('Alice Example', $prepared_value[0]['contacts']);
    $this->assertStringContainsString("- name: 'Alice Example'", $prepared_value[0]['contacts']);
    $this->assertStringContainsString('phone: 111-111', $prepared_value[0]['contacts']);
    $this->assertIsString($prepared_value[0]['geoPoint']);
    $this->assertStringContainsString('lat: 51.05089', $prepared_value[0]['geoPoint']);
    $this->assertStringContainsString('lon: 13.73832', $prepared_value[0]['geoPoint']);
  }

}
