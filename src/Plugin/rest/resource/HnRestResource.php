<?php

namespace Drupal\hn\Plugin\rest\resource;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\hn\EntitiesWithViews;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "hn_rest_resource",
 *   label = @Translation("Headless Ninja REST Resource"),
 *   uri_paths = {
 *     "canonical" = "/hn"
 *   }
 * )
 */
class HnRestResource extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * A renderer interface.
   *
   * @var \Symfony\Component\Serializer\Normalizer\NormalizerInterface
   */
  protected $normalizer;

  /**
   * A list of entities and their views.
   *
   * @var \Drupal\hn\EntitiesWithViews
   */
  protected $entitiesWithViews;

  /**
   * Constructs a new HnRestResource object.
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
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   A current user instance.
   * @param \Symfony\Component\Serializer\Normalizer\NormalizerInterface $normalizer
   *   A renderer instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    ConfigFactoryInterface $config_factory,
    AccountProxyInterface $current_user,
    NormalizerInterface $normalizer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    $this->currentUser = $current_user;
    $this->normalizer = $normalizer;
    $this->config = $config_factory;
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
      $container->get('config.factory'),
      $container->get('current_user'),
      $container->get('serializer')
    );
  }

  private $responseData;

  /**
   * Responds to GET requests.
   *
   * Returns a list of bundles for specified entity.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function get() {

    if (!$this->currentUser->hasPermission('access content')) {
      $url = $this->config->get('system.site')->get('page.404');
      $response = new ModifiedResourceResponse(['url' => $url, 'message' => 'Access Denied']);
      $response->setStatusCode(403);
      return $response;
    }

    $this->responseData = [
      'data' => [],
      'paths' => [],
    ];

    $path = \Drupal::request()->query->get('path', '');

    // TODO: Use LanguageNegotiationUrl:getLangcode to get the language from the path url.
    $url = Url::fromUri('internal:/' . trim($path, '/'));

    if (!$url->isRouted()) {
      $url = $this->config->get('system.site')->get('page.404');
      $response = new ModifiedResourceResponse(['url' => $url, 'message' => 'Entity not found for path ' . $path]);
      $response->setStatusCode(404);
      return $response;
    }

    if ($url->getRouteName() === '<front>') {
      $url = Url::fromUri('internal:/' . trim(\Drupal::config('system.site')->get('page.front'), '/'));
    }

    $params = $url->getRouteParameters();
    $entity_type = key($params);
    if (!$entity_type) {
      $url = $this->config->get('system.site')->get('page.404');
      $response = new ModifiedResourceResponse(['url' => $url, 'message' => 'Path ' . $path . ' isn\'t an entity and is therefore not supported.']);
      $response->setStatusCode(404);
      return $response;
    }

    $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($params[$entity_type]);

    $this->entitiesWithViews = new EntitiesWithViews();
    $this->addEntity($entity);

    $this->responseData['paths'][$path] = $entity->uuid();

    $response = new ModifiedResourceResponse($this->responseData);
    $response->headers->set('Cache-Control', 'public, max-age=3600');

    return $response;
  }

  /**
   * Adds an entity to $this->response_data.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to be added.
   * @param string $view_mode
   *   The view mode to be added.
   */
  private function addEntity(EntityInterface $entity, $view_mode = 'default') {

    // If it isn't a fieldable entity, don't add.
    if (!$entity instanceof FieldableEntityInterface) {
      return;
    }

    // If the current user doesn't have permission to view, don't add.
    if (!$entity->access('view', $this->currentUser)) {
      return;
    }

    $entity_with_views = $this->entitiesWithViews->addEntity($entity, $view_mode);

    $hidden_fields = $entity_with_views->getHiddenFields();

    // Nullify all hidden fields, so they aren't normalized.
    foreach ($entity->getFields() as $field_name => $field) {

      if (in_array($field_name, $hidden_fields) || !$field->access('view', $this->currentUser)) {
        $entity->set($field_name, NULL);
      }

    }

    $normalized_entity = [
        '__hn' => [
          'view_modes' => $entity_with_views->getViewModes(),
          'hidden_fields' => [],
        ],
      ] + $this->normalizer->normalize($entity);

    // Now completely remove the hidden fields.
    foreach ($entity->getFields() as $field_name => $field) {
      if (in_array($field_name, $hidden_fields) || !$field->access('view', $this->currentUser)) {
        unset($normalized_entity[$field_name]);
        $normalized_entity['__hn']['hidden_fields'][] = $field_name;
      }

      // If this field is an entity reference, add the referenced entities too.
      elseif ($field instanceof EntityReferenceFieldItemListInterface) {

        // Get all referenced entities.
        $referenced_entities = $field->referencedEntities();

        // Get the referenced view mode (e.g. teaser) that is set in the current
        // display (e.g. full).
        $referenced_entities_display = $entity_with_views->getDisplay($view_mode)->getComponent($field_name);
        $referenced_entities_view_mode = $referenced_entities_display && $referenced_entities_display['type'] === 'entity_reference_entity_view' ? $referenced_entities_display['settings']['view_mode'] : 'default';

        foreach ($referenced_entities as $referenced_entity) {
          $this->addEntity($referenced_entity, $referenced_entities_view_mode);
        }

      }
    }

    // Add the entity and the path to the response_data object.
    $this->responseData['data'][$entity->uuid()] = $normalized_entity;

    // If entity is instance of paragraph don't add it to path.
    // Paragraphs don't have a URL to add to the paths array.
    try {
      $this->responseData['paths'][$entity->toUrl('canonical')->toString()] = $entity->uuid();
    }
    catch (\Exception $exception) {
      // Can't add url so do nothing.
    }
  }

}
