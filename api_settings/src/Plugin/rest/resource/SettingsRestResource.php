<?php

namespace Drupal\api_settings\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Psr\Log\LoggerInterface;
use Drupal\api_settings\Helpers\Language;
use Drupal\api_settings\Helpers\Menu;

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
    // You must to implement the logic of your REST Resource here.
    // Use current user after pass authentication to validate access.
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }

    $responseArray = array(
      'response' => array(
        'languages' => Language::getLanguages(),
        'menu' => Menu::get(),
        'imageStyles' => $this->getImageStyles(),
        'qa' => $this->getQaSettings(),
      ),
    );

    $response = new ResourceResponse($responseArray);
    $response->addCacheableDependency(array(
      '#cache' => array(
        'max-age' => 0,
      ),
    ));
    return $response;
  }

  /**
   * Get Q&A settings.
   */
  protected function getQaSettings() {
    $config = \Drupal::config('api_settings.qa');
    $output = [];
    foreach (\Drupal::languageManager()->getLanguages() as $language) {
      $output[$language->getId()] = [
        'q' => $config->get('q.' . $language->getId()),
        'a' => $config->get('a.' . $language->getId()),
      ];
    }
    return $output;
  }

  /**
   * List all image styles.
   */
  protected function getImageStyles() {
    $output = [];
    $storage = \Drupal::entityTypeManager()->getStorage('image_style');
    foreach (\Drupal::entityQuery('image_style')->execute() as $name) {
      $style = $storage->load($name);
      $width = 0;
      $height = 0;
      foreach ($style->getEffects()->getConfiguration() as $effect) {
        if (!empty($effect['data']['width'])) {
          $width = $effect['data']['width'];
        }
        if (!empty($effect['data']['height'])) {
          $height = $effect['data']['height'];
        }
      }
      $output[$name] = ['width' => $width, 'height' => $height];
    }
    return $output;
  }

}
