<?php

namespace Drupal\api_form\Plugin\rest\resource;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\FormState;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\Plugin\ResourceBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Psr\Log\LoggerInterface;
use Drupal\webform\Entity\WebformSubmission;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "form_rest_resource",
 *   label = @Translation("Form rest resource"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/form",
 *     "https://www.drupal.org/link-relations/create" = "/api/v1/form"
 *   }
 * )
 */
class FormRestResource extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  private $language;

  protected $moduleHandler;

  /**
   * Constructs a Drupal\rest\Plugin\ResourceBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   A current user instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    $this->currentUser = $current_user;

    $this->moduleHandler = \Drupal::moduleHandler();

    $this->language = \Drupal::languageManager()->getCurrentLanguage()->getId();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('api_settings'),
      $container->get('current_user')
    );
  }

  /**
   * Responds to POST requests.
   *
   * Returns a list of bundles for specified entity.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function post($values) {
    return $this->postSubmission($values);
  }

  /**
   * Validates all values and returns a form response.
   *
   * @param array $values
   *   Array with post data.
   *
   * @return \Symfony\Component\HttpFoundation\Response|\Exception
   *   Response.
   */
  private function postSubmission(array $values) {

    // Check if Form_id isset
    if (empty($values['form_id'])) {
      return new Response('', 400);
    }

    $form_id = $values['form_id'];

    // Create webformsubmission.
    $webform_submission = $this->createSubmission($form_id);

    // Unset Form_id, because later we are going to use values to create a new
    // submission.
    unset($values['form_id']);

    $webform_submission->setData($values);

    // Get the form object.
    $entity_form_object = \Drupal::entityTypeManager()
      ->getFormObject('webform_submission', 'default');
    $entity_form_object->setEntity($webform_submission);

    // Initialize the form state.
    $form_state = (new FormState())->setValues($values);
    \Drupal::formBuilder()->submitForm($entity_form_object, $form_state);

    $errors = $form_state->getErrors();

    if (empty($errors) === FALSE) {
      return new Response(json_encode($errors), 200);
    }
  
    try {
      $webform_submission->save();
      $status = 200;
      $message = 'OK';
      $id = $webform_submission->id();
      $uuid = $webform_submission->uuid();
    
      $responses = $this->moduleHandler->invokeAll('api_form_save', [
        'webform_submission' => $webform_submission,
        'values' => $values,
        'form_id' => $form_id,
      ]);
    
      $responses = is_array($responses[0]) ? $responses : [$responses]; // Turn into array if not already.
    
      foreach($responses as $response) {
        if($response['status'] >= 400) { // If at least one error, return it
          return new Response(json_encode([
            'status' => $response['status'],
            'message' => $response['message'],
          ]), $response['status']);
        }
      }
    
      return new Response(json_encode([
        'status' => $status,
        'submission_id' => $id,
        'uuid' => $uuid,
        'message' => $message,
      ]), 201);
    }
    catch (EntityStorageException $e) {
      return new HttpException(500, 'Internal server error', $e);
    }
  }

  /**
   * Create a submission.
   *
   * @param int $form_id
   *   Form id.
   *
   * @return WebformSubmission
   *   Returns the webformsubmission or false.
   */
  private function createSubmission($form_id) {
    return WebformSubmission::create([
      'webform_id' => $form_id,
      'uri' => '/form/' . $form_id,
    ]);
  }

}
