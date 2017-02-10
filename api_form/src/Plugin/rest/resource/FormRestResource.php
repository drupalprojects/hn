<?php

namespace Drupal\api_form\Plugin\rest\resource;

use Drupal\Core\Form\FormState;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\Plugin\ResourceBase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Psr\Log\LoggerInterface;

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
  public function post($values, $data) {
//    $formValidator = new FormValidator();
//    $form_state = new FormState();
//    $form_id = 'webform_submission_test_form';
//    $webform = Webform::load('test');
//    $webform_submission = \Drupal\webform\Entity\WebformSubmission::create([
//      'webform_id' => 'test',
//      'data' => [
//        'test_field' => 'test',
//      ],
//      'uri'=> '/form/test'
//    ]);
//    $form = $webform->getSubmissionForm([
//      'webform_id' => 'test',
//      'data' => [
//        'test_field' => 'test',
//      ],
//      'uri'=> '/form/test'
//    ]);
//
//    $form_state->setCompleteForm($form);
//    $form_state->setValues(['test_field' => 'test']);
//
//    kint($form);
//    kint($webform);
//    kint($form_state);
//    $webform_submission->setValidationRequired(TRUE);
//    kint($webform_submission);
//    $violations = $webform_submission->validate();
//    $form_data = $webform->getSubmissionForm();
//    $form_state = new FormState();
//    $form_state->setValues(['checkbox' => '1',
//      'name' => 'test',]);

//    kint($form_state);

//    $webform->invokeHandlers('validateForm', $form_data, $form_state, $webform_submission);

//    kint($violations);
//    kint($webform_submission);
//    $webform_submission->save();
//    kint($webform_submission->id());
//    kint($webform_submission);
//    kint($webform_submission);
//    kint($form);
//    kint($formBase->validateForm($form, $form_state));
//    kint($form);
//    kint($form->getSubmissionForm());
//    die();

    $response = NULL;

    $form_id = !empty($values['form_id']) ? $values['form_id'] : NULL;

    if ($form_id) {
      unset($values['form_id']);

      // Create a submission
      $webform_submission = \Drupal\webform\Entity\WebformSubmission::create([
        'webform_id' => $form_id,
        'uri' => '/form/' . $form_id
      ]);

      // Get the form object.
      $entity_form_object = \Drupal::entityTypeManager()
                                   ->getFormObject('webform_submission', 'default');
      $entity_form_object->setEntity($webform_submission);

      // Initialize the form state.
      $form_state = (new FormState())->setValues($values);
      \Drupal::formBuilder()->submitForm($entity_form_object, $form_state);

      $errors = [];
      foreach ($form_state->getErrors() as $error) {
        $errors[] = $error->jsonSerialize();
      }

      $response = new \stdClass();

      if (empty($errors)) {
        if ($webform_submission->save()) {
          $response->status = 200;
          $response->id = $webform_submission->id();
        } else {
          $response->errors[] = 'Saving went wrong';
        }
      } else {
        $response->errors = $errors;
      }
      $response = json_encode($response);
    }

    kint($response);
    die();

    // Throw an exception if it is required.
    return new Response($response);
  }
}
