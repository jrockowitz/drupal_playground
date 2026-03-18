<?php

declare(strict_types=1);

namespace Drupal\entity_labels\Form;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
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
    protected string $type,
    private readonly FileSystemInterface $fileSystem,
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
   *
   * @return array
   *   The form render array.
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
  public function validateFileUpload(
    array &$element,
    FormStateInterface $form_state,
    array &$complete_form,
  ): void {
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

    if ($csv === FALSE || $csv === '') {
      $form_state->setErrorByName(
        $element['#name'],
        $this->t('The uploaded file could not be read.'),
      );
      return;
    }

    $form_state->setValue('csvupload', $csv);
    $form_state->setValue('csv_filename', $file->getFilename());
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $csv = $form_state->getValue('csvupload');
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

    $form_state->setRedirectUrl($this->buildRedirectUrl(
      $form_state->getValue('csv_filename', ''),
    ));
  }

  /**
   * Builds the redirect URL based on the uploaded CSV filename.
   *
   * Parses the filename to extract entity_type and bundle (when present)
   * and routes to the corresponding report page.
   *
   * @param string $filename
   *   The uploaded CSV filename.
   *
   * @return \Drupal\Core\Url
   *   The target report URL.
   */
  private function buildRedirectUrl(string $filename): Url {
    $name = pathinfo($filename, PATHINFO_FILENAME);
    $prefix = 'entity-labels-' . $this->getPluralName();

    if (!str_starts_with($name, $prefix)) {
      return Url::fromRoute($this->getReportRoute());
    }

    // Remaining after prefix: '' | '-node' | '-node-article'.
    $remaining = ltrim(substr($name, strlen($prefix)), '-');

    if ($remaining === '') {
      return Url::fromRoute($this->getReportRoute());
    }

    // Machine names are [a-z0-9_]; dashes are always separators.
    $parts = explode('-', $remaining, 2);
    $entity_type = $parts[0] !== '' ? $parts[0] : NULL;
    $bundle = ($this->type === 'field') ? ($parts[1] ?? NULL) : NULL;

    return Url::fromRoute(
      $this->getReportRoute(),
      array_filter(['entity_type' => $entity_type, 'bundle' => $bundle]),
    );
  }

}
