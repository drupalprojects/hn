<?php

namespace Drupal\hn\Plugin\rest\resource;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;
use Drupal\node\Entity\Node;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "node_rest_resource",
 *   serialization_class = "Drupal\Core\Entity\Entity",
 *   label = @Translation("Path endpoint"),
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

  /**
   * The link relation type manager used to create HTTP header links.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $linkRelationTypeManager;

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
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A config factory.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $link_relation_type_manager
   *   The link relation type manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user,
    ConfigFactoryInterface $config_factory,
    PluginManagerInterface $link_relation_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    $this->currentUser = $current_user;
    $this->configFactory = $config_factory;
    $this->linkRelationTypeManager = $link_relation_type_manager;

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
      $container->get('logger.factory')->get('hn'),
      $container->get('current_user'),
      $container->get('config.factory'),
      $container->get('plugin.manager.link_relation_type')
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
        return new NotFoundHttpException();
      }

      $entity_access = $node->access('view', NULL, TRUE);
      if (!$entity_access->isAllowed()) {
        throw new AccessDeniedHttpException($entity_access->getReason() ?: $this->generateFallbackAccessDeniedMessage($node, 'view'));
      }
      $node = $node->getTranslation($this->language);

      $response = new ResourceResponse($node, 200);
      $response->addCacheableDependency($node);
      $response->addCacheableDependency($entity_access);
      $response->addCacheableDependency(['#cache' => ['max-age' => 0],]);

      if ($node instanceof FieldableEntityInterface) {
        foreach ($node as $field_name => $field) {
          /** @var \Drupal\Core\Field\FieldItemListInterface $field */
          $field_access = $field->access('view', NULL, TRUE);
          $response->addCacheableDependency($field_access);

          if (!$field_access->isAllowed()) {
            $node->set($field_name, NULL);
          }
        }
      }
    }

    return $response;
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

  /**
   * Generates a fallback access denied message, when no specific reason is set.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   * @param string $operation
   *   The disallowed entity operation.
   *
   * @return string
   *   The proper message to display in the AccessDeniedHttpException.
   */
  protected function generateFallbackAccessDeniedMessage(EntityInterface $entity, $operation) {
    $message = "You are not authorized to {$operation} this {$entity->getEntityTypeId()} entity";

    if ($entity->bundle() !== $entity->getEntityTypeId()) {
      $message .= " of bundle {$entity->bundle()}";
    }
    return "{$message}.";
  }
}
