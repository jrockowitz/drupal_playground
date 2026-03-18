<?php

declare(strict_types=1);

namespace Drupal\entity_labels\Exception;

/**
 * Thrown when a row cannot be processed during import.
 *
 * Examples: unknown entity type, unknown field name.
 */
class EntityLabelsImportException extends \RuntimeException {}
