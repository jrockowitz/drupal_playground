<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov_search_api_autocomplete\Unit;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the shipped Elastic recipe autocomplete configuration.
 *
 * @group clinical_trials_gov_search_api_autocomplete
 */
#[Group('clinical_trials_gov_search_api_autocomplete')]
class ClinicalTrialsGovRecipeElasticAutocompleteConfigTest extends UnitTestCase {

  /**
   * Tests the recipe install list, permission grant, and autocomplete config.
   */
  public function testElasticRecipeAutocompleteConfig(): void {
    $recipe_directory = dirname(__DIR__, 9) . '/recipes/clinical_trials_gov_recipe_elastic_autocomplete';
    $recipe = Yaml::decode((string) file_get_contents($recipe_directory . '/recipe.yml'));
    $search = Yaml::decode((string) file_get_contents($recipe_directory . '/config/search_api_autocomplete.search.trials_elasticsearch.yml'));
    $composer = Json::decode((string) file_get_contents($recipe_directory . '/composer.json'));

    // Check that the dedicated recipe installs the required autocomplete
    // modules.
    $this->assertContains('search_api_autocomplete', $recipe['install']);
    $this->assertContains('clinical_trials_gov_search_api_autocomplete', $recipe['install']);

    // Check that anonymous users can access the public autocomplete search.
    $this->assertSame(
      'use search_api_autocomplete for trials_elasticsearch',
      $recipe['config']['actions']['user.role.anonymous']['grantPermission']
    );
    $this->assertSame(
      'use search_api_autocomplete for trials_elasticsearch',
      $recipe['config']['actions']['user.role.authenticated']['grantPermission']
    );

    // Check that the recipe imports the autocomplete search config entity.
    $this->assertContains(
      'search_api_autocomplete.search.trials_elasticsearch',
      $recipe['config']['import']['search_api_autocomplete']
    );

    // Check that the config is bound to the Elastic index and Views search.
    $this->assertSame('trials_elasticsearch', $search['id']);
    $this->assertSame('trials_elasticsearch', $search['index_id']);
    $this->assertArrayHasKey('clinical_trials_gov_search_api_autocomplete', $search['suggester_settings']);
    $this->assertSame(
      ['page_1'],
      $search['search_settings']['views:trials_elasticsearch']['displays']['selected']
    );
    $this->assertFalse($search['search_settings']['views:trials_elasticsearch']['displays']['default']);

    // Check that the shipped autocomplete behavior uses a small plain-text
    // list.
    $this->assertSame(10, $search['options']['limit']);
    $this->assertFalse($search['options']['show_count']);

    // Check that the recipe package declares the contrib dependency it enables.
    $this->assertSame('^1', $composer['require']['drupal/search_api_autocomplete']);
  }

}
