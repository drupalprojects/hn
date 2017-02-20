<?php

namespace Drupal\api_url\Plugin\rest\resource;

use Drupal\node\Entity\Node;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;
use Drupal\rest\Plugin\ResourceBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Drupal\headless_drupal\ResponseHelper;

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
class NodeRestResource extends ResourceBase {
  use \Drupal\api_url\FileUrlsTrait;
  use \Drupal\api_url\FieldTrait;


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
      $container->get('logger.factory')->get('api_url'),
      $container->get('current_user')
    );
  }

  /**
   * Responds to GET requests.
   *
   * Returns a list of bundles for specified entity.
   *
   * @return Response
   *   Throws exception expected.
   */
  public function get() {

    return $this->getResponseByUrl(\Drupal::request()->get('url', ''));
  }

  /**
   * Generate API response for given URL.
   */
  private function getResponseByUrl($url, $statusCode = 200) {
    $url = '/' . trim($url, '/');

    $language_negotiation = \Drupal::config('language.negotiation')->get('url');

    if ($language_negotiation['source'] == LanguageNegotiationUrl::CONFIG_PATH_PREFIX) {

      // The PATH_PREFIX method is used for language detection.
      // This should be stripped of the url.
      foreach ($language_negotiation['prefixes'] as $lang_id => $lang_prefix) {
        if (empty($lang_prefix) && !isset($this->language)) {
          $this->language = $lang_id;
        }

        if (!empty($lang_prefix) && strpos($url, $lang_prefix) === 1) {
          // Change the language.
          $this->language = $lang_id;

          // Remove the prefix from the url.
          $url = substr($url, strlen($lang_prefix) + 1);
        }
      }
    }

    // If the ?url= is empty, get the frontpage.
    if ($url == '/' || empty($url)) {
      $url = \Drupal::config('system.site')->get('page.front');
    }

    // Get the internal path (entity/entity_id) by the alias provided.
    $path = \Drupal::service('path.alias_manager')->getPathByAlias($url, $this->language);

    $response = NULL;

    // Check if the entity is a node.
    if (preg_match('/node\/(\d+)/', $path, $matches)) {

      // Get the node.
      $node = Node::load($matches[1]);
      if (!$node) {
        return $this->getErrorResponse(404, $url);
      }

      $node = $node->getTranslation($this->language);

      // Check if the user has permissions to view this node.
      /* if (!$node->access()) {
      var_dump('no access');
      return $this->getErrorResponse(403, $url);
      } */

      $nodeObject = $this->getFullNode($node);

      $response = new \stdClass();
      $response->content = $nodeObject;
      $response->meta = $this->getMetatags($node);

      $data = json_encode($response);
      $httpResponse = new Response($data);

      // Set status code.
      if ($statusCode != 200) {
        $httpResponse->setStatusCode($statusCode);
      }

      return $httpResponse;
    }
    if (!preg_match('/node\/(\d+)/', $path, $matches)) {
      return $this->getErrorResponse(404, $url);
    }

    return $response;
  }

  /**
   * Generate HTTP response for error page.
   */
  private function getErrorResponse($code, $originalUrl) {
    $url = \Drupal::config('system.site')->get("page.$code");
    if ($originalUrl == $url) {
      // Error page is not found or accessible by itself.
      // Prevent inifinite recursion.
      ResponseHelper::throwResponse($code);
    }
    // TODO: This line can cause a infinite loop. Rework it.
    /* return $this->getResponseByUrl($url, $code); */

  }

  /**
   * Get meta-tags for node.
   *
   * @param Node $node
   *   Node.
   *
   * @return array|mixed
   *   Returns all meta-tags.
   */
  protected function getMetatags(Node $node) {
    $metatag_manager = \Drupal::service('metatag.manager');
    $metatags = metatag_get_default_tags();
    if ($metatags) {
      foreach ($metatag_manager->tagsFromEntity($node) as $key => $value) {
        $metatags[$key] = $value;
      }
      $token = \Drupal::token();
      foreach ($metatags as $key => $value) {
        $value = str_replace('[current-page:title]', '[node:title]', $value);
        $metatags[$key] = $token->replace($value, [
          'node' => $node,
        ], [
          'langcode' => $this->language,
        ]);
      }
    }
    if (!$metatags) {
      $metatags = [];
    }
    $url = $node->toUrl('canonical');
    $url->setOption('absolute', TRUE);
    return $metatags + [
      'description' => '',
      'keywords' => '',
      'canonical_url' => $url->toString(),
    ];
  }

}
