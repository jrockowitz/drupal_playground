<?php

declare(strict_types=1);

namespace Drupal\entity_labels\Exception;

/**
 * Thrown when the CSV is malformed or required headers are missing.
 */
class EntityLabelsCsvParseException extends \RuntimeException {}
