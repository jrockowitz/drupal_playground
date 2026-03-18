<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_labels\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Base class for entity_labels functional tests.
 */
abstract class EntityLabelsTestBase extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
  }

  /**
   * Writes CSV content to a temporary file and returns its path.
   *
   * @param string $content
   *   The CSV content to write.
   *
   * @return string
   *   Absolute path to the temporary file.
   */
  protected function writeTemporaryCsv(string $content): string {
    $path = $this->tempFilesDirectory . '/' . $this->randomMachineName() . '.csv';
    file_put_contents($path, $content);
    return $path;
  }

}
