in \Drupal\entity_labels\EntityLabelsFieldExporter::getData

- Rework logic to
  - return all fields when $entity_type is empty.
  - return entity type fields when $bundle is empty.
  - return bundle fields when $entity_type and $bundle are specified via ::getBundleData.
- It is okay to have multiple loops that make sens
  - Calling $this->entityTypeManager->getDefinitions() is only needed when $entity_type is empty.

Review the entity_labels module.
Determine if any test coverage is missing
Review README.md for clarity and completeness
Review docblocks for clarity and completeness, try to simplify the commments.
