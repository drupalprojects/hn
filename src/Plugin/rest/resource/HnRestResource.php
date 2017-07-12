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
   * @var \Symfony\Component\Serializer\Normalizer\NormalizerInterface $normalizer
   */
  protected $normalizer;


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
   * @param \Symfony\Component\Serializer\Normalizer\NormalizerInterface $normalizer
   *   A renderer instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user,
    NormalizerInterface $normalizer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    $this->currentUser = $current_user;
    $this->normalizer = $normalizer;
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
      $container->get('serializer')
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

    // If the ?path= is empty, get the frontpage.
    if ($path == '/' || empty($path)) {
      $path = \Drupal::config('system.site')->get('page.front');
    }

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

    // If it isn't an fieldable entity, don't add.
    if(!$entity instanceof FieldableEntityInterface) return;

    // If the current user doesn't have permission to view, don't add.
    if(!$entity->access('view', $this->currentUser)) return;

    // Find all fields that are hidden in this view.
    $display = entity_get_display($entity->getEntityTypeId(), $entity->bundle(), $view_mode);
    $hidden_fields = array_keys($display->toArray()['hidden']);

    // Nullify all hidden fields, so they aren't normalized.
    foreach ($entity->getFields() as $field_name => $field) {

      if (in_array($field_name, $hidden_fields) || !$field->access('view', $this->currentUser)) {
        $entity->set($field_name, NULL);
      }

    }

    $normalized_entity = ['__hn' => [
      'view_modes' => [$view_mode],
      'hidden_fields' => [],
    ]] + $this->normalizer->normalize($entity);

    // Now completely remove the hidden fields.
    foreach ($entity->getFields() as $field_name => $field) {
      if (in_array($field_name, $hidden_fields) || !$field->access('view', $this->currentUser)) {
        unset($normalized_entity[$field_name]);
        $normalized_entity['__hn']['hidden_fields'][] = $field_name;
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

    // Add the entity and the path to the response_data object.
    $this->response_data['data'][$entity->uuid()] = $normalized_entity;
    $this->response_data['paths'][$entity->toUrl('canonical')->toString()] = $entity->uuid();
  }

}
