<?php

namespace Drupal\hn\Plugin\rest\resource;

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
    $path = '/' . trim($path, '/');

    // TODO: Use LanguageNegotiationUrl:getLangcode to get the language from the path url

    // TODO: Use Url::fromUserInput instead of Url:fromURI.
    $url = Url::fromUri("internal:" . $path);
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

    $response = new ModifiedResourceResponse($this->response_data);

    return $response;
  }

  /**
   * Responds to POST requests.
   *
   * Returns a list of bundles for specified entity.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function post() {

    // You must to implement the logic of your REST Resource here.
    // Use current user after pass authentication to validate access.
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }

    return new ModifiedResourceResponse("Implement REST State POST!");
  }

  /**
   * @param \Drupal\Core\Entity\EntityInterface $entity
   */
  private function addEntity($entity) {
    $this->response_data['data'][$entity->uuid()] = $entity;
    $this->response_data['paths'][$entity->toUrl('canonical')->toString()] = $entity->uuid();
  }

}
