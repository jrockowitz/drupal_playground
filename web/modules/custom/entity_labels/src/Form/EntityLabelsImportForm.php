<?php

declare(strict_types=1);

namespace Drupal\entity_labels\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_labels\EntityLabelsTypeTrait;
use Drupal\entity_labels\Exception\EntityLabelsCsvParseException;
use Drupal\entity_labels\Exception\EntityLabelsImportException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Import form for entity and field label CSV files.
 */
class EntityLabelsImportForm extends FormBase {

  use EntityLabelsTypeTrait;

  /**
   * Constructs an EntityLabelsImportForm.
   */
  public function __construct(
    private ContainerInterface $container,
    protected string $type,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $type = $container->get('request_stack')
      ->getCurrentRequest()->attributes->get('type', 'entity');
    // @phpstan-ignore new.static
    return new static($container, $type);
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

    // Field-only: note that allowed_values and field_type are ignored.
    if ($this->type === 'field') {
      $form['field_notice'] = [
        '#type'  => 'html_tag',
        '#tag'   => 'p',
        '#value' => $this->t(
          'Note: %allowed_values and %field_type columns are ignored during import.',
          [
            '%allowed_values' => 'allowed_values',
            '%field_type'     => 'field_type',
          ],
        ),
      ];
    }

    $form['csv_upload'] = [
      '#type'             => 'file',
      '#title'            => $this->t('CSV file'),
      '#description'      => $this->t('Upload a CSV file to import.'),
      '#element_validate' => [
        [static::class, 'validateFileUpload'],
      ],
    ];

    $form['submit'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Import CSV'),
    ];

    return $form;
  }

  /**
   * Element validator: reads the uploaded CSV into form state.
   *
   * Reads the file content during validation so the temp file is consumed
   * within the same request it was uploaded. No dependency on the file module.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The complete form render array.
   */
  public static function validateFileUpload(
    array &$element,
    FormStateInterface $form_state,
    array &$complete_form,
  ): void {
    $upload_name = \implode('_', $element['#parents']);
    $uploaded_files = \Drupal::request()->files->get('files', []);
    $uploaded_file = $uploaded_files[$upload_name] ?? NULL;

    if (!$uploaded_file instanceof UploadedFile) {
      return;
    }

    $extension = \strtolower((string) $uploaded_file->getClientOriginalExtension());
    if ($extension !== 'csv') {
      $form_state->setErrorByName(
        $element['#name'],
        \t('Only CSV files are allowed.'),
      );
      return;
    }

    $real_path = $uploaded_file->getRealPath();
    $csv = $real_path !== FALSE ? \file_get_contents($real_path) : FALSE;
    if ($csv === FALSE || $csv === '') {
      $form_state->setErrorByName(
        $element['#name'],
        \t('The uploaded file could not be read.'),
      );
      return;
    }

    $form_state->setValue('csvupload', $csv);
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
  }

}
