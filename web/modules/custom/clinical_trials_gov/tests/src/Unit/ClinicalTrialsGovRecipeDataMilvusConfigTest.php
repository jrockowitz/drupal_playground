<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Unit;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the shipped Milvus data recipe configuration.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovRecipeDataMilvusConfigTest extends UnitTestCase {

  /**
   * Tests the merged Milvus recipe installs chat and backend configuration.
   */
  public function testMilvusRecipeConfig(): void {
    $recipe_directory = dirname(__DIR__, 7) . '/recipes/clinical_trials_gov_recipe_data_milvus';
    $recipe = Yaml::decode((string) file_get_contents($recipe_directory . '/recipe.yml'));
    $composer = Json::decode((string) file_get_contents($recipe_directory . '/composer.json'));

    // Check that the renamed recipe still builds on the shared setup layer.
    $this->assertContains('clinical_trials_gov_recipe_data_setup', $recipe['recipes']);

    // Check that the merged recipe now owns the public chat user interface.
    $this->assertContains('ai_chatbot', $recipe['install']);
    $this->assertContains(
      'block.block.olivero_trials_milvus_chat',
      $recipe['config']['import']['block']
    );
    $this->assertSame(
      'access deepchat api',
      $recipe['config']['actions']['user.role.anonymous']['grantPermission']
    );

    // Check that the package identity changed to the data-prefixed name.
    $this->assertSame('drupal/clinical_trials_gov_recipe_data_milvus', $composer['name']);
    $this->assertSame('^1.1@beta', $composer['require']['drupal/ai_vdb_provider_milvus']);
  }

}
