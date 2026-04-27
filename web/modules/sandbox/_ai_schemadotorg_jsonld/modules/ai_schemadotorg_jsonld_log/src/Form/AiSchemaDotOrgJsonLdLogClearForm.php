<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld_log\Form;

use Drupal\ai_schemadotorg_jsonld_log\AiSchemaDotOrgJsonLdLogStorageInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirms clearing the AI Schema.org JSON-LD log.
 *
 * @phpstan-consistent-constructor
 */
class AiSchemaDotOrgJsonLdLogClearForm extends ConfirmFormBase {

  /**
   * Constructs an AiSchemaDotOrgJsonLdLogClearForm object.
   *
   * @param \Drupal\ai_schemadotorg_jsonld_log\AiSchemaDotOrgJsonLdLogStorageInterface $logStorage
   *   The log storage.
   */
  public function __construct(
    protected readonly AiSchemaDotOrgJsonLdLogStorageInterface $logStorage,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(AiSchemaDotOrgJsonLdLogStorageInterface::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ai_schemadotorg_jsonld_log_clear_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Are you sure you want to clear the AI Schema.org JSON-LD log?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return Url::fromUserInput('/' . $this->getRedirectDestination()->get());
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Clear log');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->logStorage->truncate();
    $this->messenger()->addStatus($this->t('The AI Schema.org JSON-LD log has been cleared.'));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
