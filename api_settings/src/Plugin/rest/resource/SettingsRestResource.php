<?php

namespace Drupal\api_settings\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\Plugin\ResourceBase;
use Symfony\Component\HttpFoundation\Response;
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
  use \Drupal\api_nodes\FileUrlsTrait;

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
        'general' => $this->getGeneralSettings(),
        'logos' => $this->getLogos(),
        'languages' => Language::getLanguages(),
        'menu' => Menu::get(),
        'qa' => $this->getQaSettings(),
      ),
    );

    $data = json_encode($responseArray);
    $response = new Response($data);

    return $response;
  }

  /**
   * Get general settings.
   */
  private function getGeneralSettings() {
    $output = [];

    $config = \Drupal::config('system.site');
    $output['siteName'] = $config->get('name');

    $config = \Drupal::config('api_settings.config');

    $output['showShareButtons'] = (bool) $config->get('show_share_buttons');
    $output['countriesLink'] = $config->get('countries_link');

    return $output;
  }

  /**
   * Get site logo's.
   */
  protected function getLogos() {
    $config = \Drupal::config('api_settings.logo');
    $output = [];

    foreach (\Drupal::languageManager()->getLanguages() as $language) {
      $languageId = $language->getId();

      $fid = $config->get("logo.$languageId");

      if (empty($fid)) {
        $output[$language->getId()] = NULL;
      }
      if (!empty($fid)) {
        $file = ['fid' => $fid];
        $this->addFileUri($file);
        $output[$languageId] = $file;
      }
    }
    return $output;
  }

  /**
   * Get Q&A settings.
   */
  protected function getQaSettings() {
    $config = \Drupal::config('api_settings.qa');
    $output = [];
    foreach (\Drupal::languageManager()->getLanguages() as $language) {
      $languageId = $language->getId();
      $output[$languageId] = [
        'q' => $config->get("q.$languageId"),
        'a' => $config->get("a.$languageId"),
      ];
    }
    return $output;
  }

}
