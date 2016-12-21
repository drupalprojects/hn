<?php

namespace Drupal\api_nodes\Plugin\rest\resource;

use \Drupal\node\Entity\Node;
use Drupal\Core\Entity\Plugin\DataType\EntityReference;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;
use Drupal\rest\Plugin\ResourceBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Response;

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
  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  private $language;

  private $allowedEntityReferences = [
    'paragraph', 'file',
  ];

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

    $this->fileStorage = \Drupal::entityTypeManager()->getStorage('file');
    $this->imageStyleStorage = \Drupal::entityTypeManager()->getStorage('image_style');
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
   *
   * @return ResourceResponse
   *   Throws exception expected.
   */
  public function get() {
    // Get the ?url= query.
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
    if ($url == '/') {
      $url = \Drupal::config('system.site')->get('page.front');
    }

    // Get the internal path (entity/entity_id) by the alias provided.
    $path = \Drupal::service('path.alias_manager')->getPathByAlias($url, $this->language);

    // Check if the entity is a node.
    if (preg_match('/node\/(\d+)/', $path, $matches)) {

      // Get the node.
      $node = Node::load($matches[1]);
      if (!$node) {
        return $this->getErrorResponse(404, $url);
      }

      $node = $node->getTranslation($this->language);

      // Check if the user has permissions to view this node.
      if (!$node->access()) {
        return $this->getErrorResponse(403, $url);
      }

      $nodeObject = $this->getFields($node);

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
      switch ($code) {
        case 403:
          throw new AccessDeniedHttpException();

        case 404:
          throw new NotFoundHttpException();

      }
    }
    return $this->getResponseByUrl($url, $code);
  }

  /**
   * Get metatags for node.
   */
  protected function getMetatags($node) {
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

  /**
   * Get fields for node Object.
   */
  private function getFields($node = NULL, array $nodeObject = array()) {
    if ($node) {
      foreach ($node->getFields() as $field_items) {
        $targetType = $field_items->getSetting('target_type');
        $name = $field_items->getName();
        foreach ($field_items as $field_item) {
          // Loop over all properties of a field item.
          foreach ($field_item->getProperties(TRUE) as $property) {
            if (in_array($targetType, $this->allowedEntityReferences)) {
              if ($property instanceof EntityReference && $entity = $property->getValue()) {
                if (empty($nodeObject[$name])) {
                  $nodeObject[$name] = [];
                }
                $fields = $this->getFields($entity);
                if (!empty($fields['fid']) && !empty($fields['uri'])) {
                  $this->addFileUri($fields);
                }
                $nodeObject[$name][] = $fields;
              }
            }
            if (!in_array($targetType, $this->allowedEntityReferences)) {
              $nodeObject[$name] = $field_item->value;
            }
          }
        }
      }
    }
    return $nodeObject;
  }

  /**
   * Add uri to file fields.
   */
  private function addFileUri(&$fields) {
    $file = $this->fileStorage->load($fields['fid']);
    $fields['url'] = $file->url();
    if (reset(explode('/', $fields['filemime'])) == 'image') {
      $fields['styles'] = $this->getImageStyleUris($fields['uri']);
    }
  }

  /**
   * Generate uri for each image style.
   */
  private function getImageStyleUris($uri) {
    $output = [];
    foreach (\Drupal::entityQuery('image_style')->execute() as $name) {
      $style = $this->imageStyleStorage->load($name);
      $output[$name] = $style->buildUrl($uri);
    }
    return $output;
  }

}
