<?php

declare(strict_types=1);

use DrupalRector\Drupal10\Rector\Deprecation\AnnotationToAttributeRector;
use DrupalRector\Drupal10\Rector\ValueObject\AnnotationToAttributeConfiguration;
use DrupalRector\Set\Drupal10SetList;
use DrupalRector\Set\Drupal11SetList;
use DrupalRector\Set\Drupal9SetList;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
  // Keep the standard Drupal upgrade sets used by the DDEV Rector command.
  $rectorConfig->sets([
    Drupal9SetList::DRUPAL_9,
    Drupal10SetList::DRUPAL_10,
    Drupal11SetList::DRUPAL_11,
  ]);

  // Annotation-to-attribute conversion is intentionally opt-in in Drupal
  // Rector. These mappings support Schema.org Blueprints issue #3622073.
  // Both versions are set to Drupal 10.0 so the legacy annotations are
  // removed from the generated code rather than retained as compatibility
  // documentation.
  $rectorConfig->ruleWithConfiguration(AnnotationToAttributeRector::class, [
    new AnnotationToAttributeConfiguration(
      '10.0.0',
      '10.0.0',
      'ConfigEntityType',
      'Drupal\\Core\\Entity\\Attribute\\ConfigEntityType',
    ),
    new AnnotationToAttributeConfiguration(
      '10.0.0',
      '10.0.0',
      'EntityReferenceSelection',
      'Drupal\\Core\\Entity\\Attribute\\EntityReferenceSelection',
    ),
  ]);

  // Run the conversion as an isolated pass to avoid applying unrelated
  // deprecation fixes at the same time, for example:
  // ddev rector process web/modules/sandbox/schemadotorg --only=DrupalRector\\Drupal10\\Rector\\Deprecation\\AnnotationToAttributeRector

  // Let Rector add imports for generated attribute classes while retaining
  // fully-qualified names for classes that already conflict with an import.
  $rectorConfig->importNames(TRUE, FALSE);
  $rectorConfig->importShortClasses(FALSE);
};
