<?php

namespace Drupal\hn\Plugin\rest\resource;

use Drupal\hn\HnResponseService;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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

  protected $config;

  protected $language;

  protected $hnResponseService;

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
   * @param \Drupal\hn\HnResponseService $hnResponseService
   *   The Headless Ninja response server.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    HnResponseService $hnResponseService) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->hnResponseService = $hnResponseService;
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
      $container->get('hn.response')
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
    $response = new ModifiedResourceResponse($this->hnResponseService->getResponseData());
    $response->headers->set('Cache-Control', 'public, max-age=3600');
    return $response;
  }

}
