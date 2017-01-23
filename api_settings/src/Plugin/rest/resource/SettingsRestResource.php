<?php

namespace Drupal\api_settings\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\Plugin\ResourceBase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "settings_rest_resource",
 *   label = @Translation("Settings rest resource"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/settings"
 *   }
 * )
 */
class SettingsRestResource extends ResourceBase {

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
   * Responds to GET requests.
   *
   * Returns a list of bundles for specified entity.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function get() {

    $moduleHandler = \Drupal::moduleHandler();
    $config = \Drupal::configFactory()->get('api_settings.general');

    $responseArray = array();

    $hookTypes = $config->get('hookTypes');
    // Invoke all modules to fill the response array.
    $moduleHandler->invokeAll('api_alter_settings_data',
      array(
        'responseArray' => &$responseArray,
      ));

    // Invoke module for each hookType
    if ($hookTypes && count($hookTypes) > 0) {
      foreach ($hookTypes as $hookType) {
        $moduleHandler->invokeAll('api_alter_settings_data_' . $hookType,
          array(
            'responseArray' => &$responseArray,
          ));
      }
    }

    // Encode the php array to json.
    $data = json_encode($responseArray);
    $response = new Response($data);

    return $response;
  }
}
