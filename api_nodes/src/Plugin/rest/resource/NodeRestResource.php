<?php

namespace Drupal\api_nodes\Plugin\rest\resource;

use Drupal\Core\Path\AliasManager;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "node_rest_resource",
 *   label = @Translation("Node rest resource"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/url"
 *   }
 * )
 */
class NodeRestResource extends ResourceBase
{

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
      $container->get('logger.factory')->get('api_nodes'),
      $container->get('current_user')
    );
  }

  /**
   * Responds to GET requests.
   *
   * Returns a list of bundles for specified entity.
   * @return ResourceResponse Throws exception expected.
   * Throws exception expected.
   */
  public function get()
  {
    /**
     * Get the ?url= query
     */
    return $this->getResponseByUrl(\Drupal::request()->get('url', ''));
  }

  private function getResponseByUrl($url, $statusCode = 200){

    $url = '/' . trim($url, '/');

    $language_negotiation = \Drupal::config('language.negotiation')->get('url');

    if($language_negotiation['source'] == LanguageNegotiationUrl::CONFIG_PATH_PREFIX){

      /**
       * The PATH_PREFIX method is used for language detection. This should be stripped of the url.
       */

      foreach($language_negotiation['prefixes'] as $lang_id => $lang_prefix){

        if(strpos($url, $lang_prefix) === 1){

          /**
           * Change the language
           */
          $this->language = $lang_id;

          /**
           * Remove the prefix from the url
           */
          $url = substr($url, strlen($lang_prefix) + 1);

        }

      }

    }

    /**
     * If the ?url= is empty, get the frontpage
     */
    if($url == '/') {
      $url = \Drupal::config('system.site')->get('page.front');
    }

    /**
     * Get the internal path (entity/entity_id) by the alias provided
     */
    $path = \Drupal::service('path.alias_manager')->getPathByAlias($url, $this->language);

    /**
     * Check if the entity is a node
     */
    if(preg_match('/node\/(\d+)/', $path, $matches)) {

      /**
       * Get the node
       */
      $node = \Drupal\node\Entity\Node::load($matches[1]);
      $node = $node->getTranslation($this->language);

      /**
       * TODO: check if the user has permissions to view this node
       */

      $response = new ResourceResponse(array($node));

      /**
       * Set status code
       */
      if($statusCode != 200){
        $response->setStatusCode($statusCode);
      }


      /**
       * Don't cache (yet)
       */
      $response->addCacheableDependency(array(
        '#cache' => array(
          'max-age' => 0,
        ),
      ));

    } else {

      /**
       * When it's not a supported entity, return 404
       */
      $page_404 = \Drupal::config('system.site')->get('page.404');

      if($page_404){
        return $this->getResponseByUrl($page_404, 404);
      }
      else{
        throw new NotFoundHttpException('The path provided couldn\'t be found or isn\'t a node, and there is no 404 page available.');
      }

    }

    return $response;
  }

}
