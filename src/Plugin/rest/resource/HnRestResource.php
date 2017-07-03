<?php

namespace Drupal\hn\Plugin\rest\resource;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
      $container->get('logger.factory')->get('hn'),
      $container->get('current_user')
    );
  }

  private $response_data;

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
      throw new AccessDeniedHttpException();
    }

    $this->response_data = [
      'data' => [],
      'paths' => []
    ];

    $path = \Drupal::request()->get('path', '');

    // TODO: Use LanguageNegotiationUrl:getLangcode to get the language from the path url
    $url = Url::fromUri('internal:/' . trim($path, '/'));
    if(!$url->isRouted()) {
      throw new NotFoundHttpException('Entity not found for path '.$path);
    }

    $params = $url->getRouteParameters();
    $entity_type = key($params);
    if(!$entity_type) {
      throw new NotFoundHttpException('Path '.$path.' isn\'t an entity and is therefore not supported.');
    }

    $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($params[$entity_type]);
    $this->addEntity($entity);

    $this->response_data['paths'][$path] = $entity->uuid();

    return new ModifiedResourceResponse($this->response_data);
  }

  /**
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param string $view_mode
   */
  private function addEntity($entity, $view_mode = 'default' ) {

    // If this entity is already being added, don't add again.

    if(isset($this->response_data['data'][$entity->uuid()])) return;

    if(!$entity instanceof FieldableEntityInterface) return;


    $this->response_data['data'][$entity->uuid()] = $entity;
    $this->response_data['paths'][$entity->toUrl('canonical')->toString()] = $entity->uuid();

    /**
     * Find all fields that are hidden in this view
     */
    $display = entity_get_display($entity->getEntityTypeId(), $entity->bundle(), $view_mode);
    $hidden_fields = array_keys($display->toArray()['hidden']);

    /** @var $field \Drupal\Core\Field\FieldItemList */
    foreach ($entity as $field_name => $field) {

      /**
       * Make sure we don't include hidden fields
       */
      if (in_array($field_name, $hidden_fields)) {
        $entity->set($field_name, NULL);
      }

      /**
       * If this field is an entity reference, add the referenced entities too.
       */
      else if ($field instanceof EntityReferenceFieldItemListInterface) {

        // Get all referenced entities
        $referenced_entities = $field->referencedEntities();

        // Get the referenced view mode (e.g. teaser) that is set in the current display (e.g. full)
        $referenced_entities_display = $display->getComponent($field_name);
        $referenced_entities_view_mode = $referenced_entities_display && $referenced_entities_display['type'] === 'entity_reference_entity_view' ? $referenced_entities_display['settings']['view_mode'] : 'default';

        foreach ($referenced_entities as $referenced_entity) {
          $this->addEntity($referenced_entity, $referenced_entities_view_mode);
        }

      }
    }
  }

}
