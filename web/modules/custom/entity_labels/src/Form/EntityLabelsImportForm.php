<?php

declare(strict_types=1);

namespace Drupal\entity_labels\Form;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_labels\EntityLabelsTypeTrait;
use Drupal\entity_labels\Exception\EntityLabelsCsvParseException;
use Drupal\entity_labels\Exception\EntityLabelsImportException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Import form for entity and field label CSV files.
 */
class EntityLabelsImportForm extends FormBase {

  use EntityLabelsTypeTrait;

  /**
   * Constructs an EntityLabelsImportForm.
   */
  public function __construct(
    private readonly string $type,
    private readonly FileSystemInterface $fileSystem,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityTypeBundleInfoInterface $bundleInfo,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $type = $container->get('request_stack')
      ->getCurrentRequest()->attributes->get('type', 'entity');
    // @phpstan-ignore new.static
    return new static(
      $type,
      $container->get('file_system'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'entity_labels_' . $this->type . '_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#attributes']['enctype'] = 'multipart/form-data';

    $form['csv_upload'] = [
      '#type' => 'file',
      '#title' => $this->t('CSV file'),
      '#description' => $this->t('Upload a CSV file to import.'),
      '#element_validate' => [[$this, 'validateFileUpload']],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import CSV'),
    ];

    return $form;
  }

  /**
   * Element validator: reads the uploaded CSV into form state.
   *
   * Reads the file content during validation so the temp file is consumed
   * within the same request it was uploaded.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The complete form render array.
   */
  public function validateFileUpload(array &$element, FormStateInterface $form_state, array &$complete_form): void {
    $upload_name = implode('_', $element['#parents']);

    $file = file_save_upload(
      $upload_name,
      ['FileExtension' => ['extensions' => 'csv']],
      'temporary://',
      0,
    );

    if ($file === NULL) {
      return;
    }

    if ($file === FALSE) {
      $form_state->setError($element, $this->t('The file upload failed.'));
      return;
    }

    $real_path = $this->fileSystem->realpath($file->getFileUri());
    $csv = $real_path !== FALSE ? file_get_contents($real_path) : FALSE;
    $file->delete();

    if (empty($csv)) {
      $form_state->setErrorByName(
        $element['#name'],
        $this->t('The uploaded file could not be read.'),
      );
      return;
    }

    $form_state->setValue('csv_filename', $file->getFilename());
    $form_state->setValue('csv_content', $csv);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $csv = $form_state->getValue('csv_content');
    if (!$csv) {
      return;
    }

    try {
      $result = $this->getImporter()->import($csv);
    }
    catch (EntityLabelsCsvParseException $e) {
      $this->messenger()->addError($e->getMessage());
      return;
    }
    catch (EntityLabelsImportException $e) {
      $this->messenger()->addError($e->getMessage());
      return;
    }

    $this->messenger()->addStatus($this->t(
      '@updated row(s) updated, @skipped row(s) skipped.',
      [
        '@updated' => $result['updated'],
        '@skipped' => $result['skipped'],
      ],
    ));

    foreach ($result['errors'] as $error) {
      $this->messenger()->addWarning($error);
    }

    if (!empty($result['null_fields'])) {
      $this->messenger()->addWarning($this->t(
        'Could not load: @items',
        ['@items' => implode(', ', $result['null_fields'])],
      ));
    }

    $filename = $form_state->getValue('csv_filename', '');
    $route_parameters = $this->getRouteParametersFromFilename($filename);
    $form_state->setRedirect($this->getReportRoute(), $route_parameters);
  }

  /**
   * Extracts route parameters from the provided filename.
   *
   * @param string $filename
   *   The filename to extract parameters from.
   *
   * @return array
   *   An associative array containing the route parameters:
   *   - entity_type: The type of the entity derived from the filename, or NULL if not found.
   *   - bundle: The bundle name derived from the filename, or NULL if not found.
   */
  protected function getRouteParametersFromFilename(string $filename): array {
    $name = pathinfo($filename, PATHINFO_FILENAME);

    $prefix = 'entity-labels-' . $this->getPluralName() . '-';
    if (!str_starts_with($name, $prefix)) {
      return [];
    }

    // Remove prefix from the file name.
    $name = preg_replace('/^' . $prefix . '/', '', $name);

    // Remove numeric suffix from the file name.
    $name = preg_replace('/\s*\(\d+\)$/', '', $name);

    // Split the name into entity type and bundle.
    $parts = explode('-', $name);
    $entity_type = $parts[0];
    $bundle = $parts[1] ?? NULL;

    // Check that the entity type exists.
    if (!$this->entityTypeManager->hasDefinition($entity_type)) {
      return [];
    }

    // Check that the bundle exists.
    if (!$bundle
      || empty($this->bundleInfo->getBundleInfo($entity_type)[$bundle])) {
      return ['entity_type' => $entity_type];
    }

    return [
      'entity_type' => $entity_type,
      'bundle' => $bundle,
    ];
  }

}
