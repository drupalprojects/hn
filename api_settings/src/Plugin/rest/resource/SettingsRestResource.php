<?php

namespace Drupal\api_settings\Plugin\rest\resource;

use Drupal\Console\Bootstrap\Drupal;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageDefault;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
class SettingsRestResource extends ResourceBase
{

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

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
    if(!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }

    $responseArray = [];

    //Add languages
    $responseArray['Languages'] = $this->getLanguages();

    $response = new ResourceResponse($responseArray);
    $response->addCacheableDependency($responseArray);
    return $response;
  }

  private function getLanguages() {
    // Get all the languages
    $languages = \Drupal::languageManager()->getLanguages();
    $languagesArray = [];

    // Instantiate request
    $request = \Drupal::request();
    if(count($languages) > 0) {
      foreach ($languages as $language) {
        $id = $language->getId();
        $name = $language->getName();
        $default = $language->isDefault();
        $direction = $language->getDirection();
        $url = $this->getLanguageDomain($request, $id);

        $languagesArray[][$id] = [
          'id' => $id,
          'name' => $name,
          'default' => $default,
          'direction' => $direction,
          'url' => $url,
        ];
      }
    }
    return $languagesArray;
  }

  private function getLanguageDomain(Request $request = null, $languageId) {
    if($request) {
      // Instantiate configuration drupal
      $config = \Drupal::configFactory();
      // Get language negotiation config. This will give a array with prefixes or domains.
      $languageNegotiation = $config->get('language.negotiation')->get('url');

      // Check if the website is configurated with prefixes or domains
      switch ($languageNegotiation['source']) {
        // If the configuration is path_prefix go further
        case LanguageNegotiationUrl::CONFIG_PATH_PREFIX:

          // Get prefix for given language
          $prefix = $languageNegotiation['prefixes'][$languageId];

          // Check if the prefix returns null if so the languageId is probally wrong.
          if(empty($prefix)) {
            throw new NotFoundHttpException('Language id is probally wrong.');
          }

          $url = $request->getHost() . '/' . $prefix;

          // Return the url
          return $url;
          break;

        // If the configuration is path_domain go further
        case LanguageNegotiationUrl::CONFIG_DOMAIN:

          // Get domain for given language
          $domain = $languageNegotiation['domain'][$languageId];

          // Check if the domain returns null if so the languageId is probally wrong.
          if(empty($domain)) {
            throw new NotFoundHttpException('Language id is probally wrong.');
          }

          // return the url
          return $domain;
          break;
      }
    }
    return NULL;
  }

}
