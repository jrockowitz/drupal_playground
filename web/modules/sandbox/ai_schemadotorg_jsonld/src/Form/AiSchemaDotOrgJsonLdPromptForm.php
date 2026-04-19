<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld\Form;

use Drupal\ai_automators\Entity\AiAutomator;
use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Edits the automator token prompt for a Schema.org JSON-LD bundle.
 *
 * @phpstan-consistent-constructor
 */
class AiSchemaDotOrgJsonLdPromptForm extends FormBase {

  /**
   * The AI automator storage.
   */
  protected EntityStorageInterface $aiAutomatorStorage;

  /**
   * The current entity type id.
   */
  protected string $entityTypeId;

  /**
   * The current bundle.
   */
  protected string $bundle;

  /**
   * Constructs an AiSchemaDotOrgJsonLdPromptForm object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   */
  public function __construct(
    protected readonly ModuleHandlerInterface $moduleHandler,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    RequestStack $requestStack,
  ) {
    $this->requestStack = $requestStack;
    $this->aiAutomatorStorage = $this->entityTypeManager->getStorage('ai_automator');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('module_handler'),
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ai_schemadotorg_jsonld_prompt_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $entity_type = '', string $bundle = ''): array {
    $this->entityTypeId = $entity_type;
    $this->bundle = $bundle;

    $automator = $this->loadAutomator($entity_type, $bundle);

    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Automator Prompt (Token)'),
      '#default_value' => (string) $automator->get('token'),
      '#required' => TRUE,
      '#rows' => 12,
      '#description' => $this->t('Edit the token-based automator prompt used to generate Schema.org JSON-LD for this bundle.'),
    ];

    if ($this->moduleHandler->moduleExists('token')) {
      $form['token_browser'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => [$this->entityTypeId],
        '#show_restricted' => TRUE,
        '#show_nested' => FALSE,
      ];
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    ];

    if ($this->isModalRequest()) {
      $form['actions']['submit']['#submit'] = ['::submitForm'];
      $form['actions']['submit']['#ajax']['callback'] = '::submitAjaxForm';
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $automator = $this->loadAutomator($this->entityTypeId, $this->bundle);
    $token = $form_state->getValue('prompt');
    $plugin_config = $automator->get('plugin_config');
    $plugin_config['automator_token'] = $token;

    $automator
      ->set('token', $token)
      ->set('plugin_config', $plugin_config)
      ->save();

    if (!$this->isModalRequest()) {
      $message = $this->t('The automator prompt has been updated.');
      $this->messenger()->addStatus($message);
    }
  }

  /**
   * Submit form #ajax callback.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An Ajax response that closes the modal dialog and displays a status message
   */
  public function submitAjaxForm(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $response->addCommand(new MessageCommand(
      message: $this->t('The automator prompt has been updated.'),
      clear_previous: TRUE,
    ));
    $response->addCommand(new CloseModalDialogCommand());
    return $response;
  }

  /**
   * Loads the automator for an entity type and bundle.
   *
   * @param string $entity_type
   *   The entity type id.
   * @param string $bundle
   *   The bundle id.
   *
   * @return \Drupal\ai_automators\Entity\AiAutomator
   *   The matching automator.
   */
  protected function loadAutomator(string $entity_type, string $bundle): AiAutomator {
    $automator_id = $entity_type . '.' . $bundle . '.' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME . '.default';
    $automator = $this->aiAutomatorStorage->load($automator_id);
    if (!$automator instanceof AiAutomator) {
      throw new NotFoundHttpException();
    }
    return $automator;
  }

  /**
   * Determines if the form is currently rendered in a Drupal modal dialog.
   */
  protected function isModalRequest(): bool {
    $wrapper_format = $this->getRequest()
      ->query->get(MainContentViewSubscriber::WRAPPER_FORMAT);
    return (in_array($wrapper_format, ['drupal_ajax', 'drupal_modal']));
  }

}
