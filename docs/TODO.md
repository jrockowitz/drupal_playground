- Change services from Export/Import to Exporter/Importer
  - EntityLabelsExportInterface => EntityLabelsExporterInterface
  - `entity_labels.entity.export` => `entity_labels.entity.exporter`
  - ::getExportService() => getExporter()
  - ::getImportService() => getImporter

- Make sure that the managed file upload is marked temp and deleted after the import
