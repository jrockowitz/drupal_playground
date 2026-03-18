<?php

declare(strict_types=1);

namespace Drupal\entity_labels;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides type-driven helpers for the entity and field report types.
 *
 * The using class must declare protected string $type before invoking
 * these methods.
 */
trait EntityLabelsTypeTrait {

  /**
   * Returns the singular UI label for the current report type.
   */
  protected function getSingularLabel(): TranslatableMarkup {
    return match ($this->type) {
      'field' =>$this->t('Field'),
      default =>$this->t('Entity'),
    };
  }

  /**
   * Returns the plural UI label for the current report type.
   */
  protected function getPluralLabel(): TranslatableMarkup {
    return match ($this->type) {
      'field' =>$this->t('Fields'),
      default =>$this->t('Entities'),
    };
  }

  /**
   * Returns the singular machine name for the current report type.
   */
  protected function getSingularName(): string {
    return $this->type === 'field' ? 'field' : 'entity';
  }

  /**
   * Returns the plural machine name for the current report type.
   */
  protected function getPluralName(): string {
    return $this->type === 'field' ? 'fields' : 'entities';
  }

  /**
   * Returns the report route name for the current type.
   */
  protected function getReportRoute(): string {
    return 'entity_labels.' . $this->type . '.report';
  }

  /**
   * Returns the export route name for the current type.
   */
  protected function getExportRoute(): string {
    return 'entity_labels.' . $this->type . '.export';
  }

  /**
   * Returns the import route name for the current type.
   */
  protected function getImportRoute(): string {
    return 'entity_labels.' . $this->type . '.import';
  }

  /**
   * Resolves and returns the exporter service for the current type.
   */
  protected function getExporter(): EntityLabelsExporterInterface {
    return \Drupal::service('entity_labels.' . $this->type . '.exporter');
  }

  /**
   * Resolves and returns the importer service for the current type.
   */
  protected function getImporter(): EntityLabelsImporterInterface {
    return \Drupal::service('entity_labels.' . $this->type . '.importer');
  }

}
