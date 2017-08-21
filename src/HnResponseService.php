<?php

namespace Drupal\hn;

use Drupal\Core\Config\ConfigFactory;
use Symfony\Component\Serializer\Serializer;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Url;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;
use Drupal\rest\ModifiedResourceResponse;

/**
 * Class HnResponseService.
 */
class HnResponseService {

  /**
   * Symfony\Component\Serializer\Serializer definition.
   *
   * @var \Symfony\Component\Serializer\Serializer
   */
  protected $serializer;
  /**
   * Drupal\Core\Session\AccountProxy definition.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;
  /**
   * Drupal\webprofiler\Config\ConfigFactoryWrapper definition.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $config;

  /**
   * A list of entities and their views.
   *
   * @var \Drupal\hn\EntitiesWithViews
   */
  public $entitiesWithViews;

  /**
   * Constructs a new HnResponseService object.
   */
  public function __construct(Serializer $serializer, AccountProxy $current_user, ConfigFactory $config_factory) {
    $this->serializer = $serializer;
    $this->currentUser = $current_user;
    $this->config = $config_factory;
  }

  protected $responseData;

  /**
   * Responds to GET requests.
   *
   * Returns a list of bundles for specified entity.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function newResponse() {
    $this->log('Creating new Headless Ninja response..');

    if (!$this->currentUser->hasPermission('access content')) {
      $url = $this->config->get('system.site')->get('page.404');
      $response = new ModifiedResourceResponse(['url' => $url, 'message' => 'Access Denied']);
      $response->setStatusCode(403);
      return $response;
    }

    $path = \Drupal::request()->query->get('path', '');

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

    $language_negotiation = \Drupal::config('language.negotiation')->get('url');

    // TODO: get language by domain.
    if ($language_negotiation['source'] == LanguageNegotiationUrl::CONFIG_PATH_PREFIX) {

      // The PATH_PREFIX method is used for language detection.
      // This should be stripped of the url.
      foreach ($language_negotiation['prefixes'] as $lang_id => $lang_prefix) {
        if (empty($lang_prefix) && !isset($this->language)) {
          $this->language = $lang_id;
        }

        if (!empty($lang_prefix) && strpos($path, $lang_prefix) === 1) {
          // Change the language.
          $this->language = $lang_id;
        }
      }
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
    $entity = $entity->getTranslation($this->language);

    $this->entitiesWithViews = new EntitiesWithViews();
    $this->addEntity($entity);

    $this->responseData['paths'][$path] = $entity->uuid();

    $this->log('Done building response data.');

    $this->responseData['__hn']['log'] = $this->log;
    $this->log = [];

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
  public function addEntity(EntityInterface $entity, $view_mode = 'default') {

    /** @var \Drupal\hn\Plugin\HnEntityManagerPluginManager $hnEntityManagerPluginManager */
    $hnEntityManagerPluginManager = \Drupal::getContainer()->get('plugin.manager.hn_entity_manager_plugin');

    $entityHandler = $hnEntityManagerPluginManager->getEntityHandler($entity);

    if (!$entityHandler) {
      $this->log('Not adding entity of type ' . get_class($entity));
      return;
    }

    $this->log('Handling entity ' . $entity->uuid() . ' with ' . $entityHandler->getPluginId());

    $normalized_entity = $entityHandler->handle($entity, $view_mode);

    if ($normalized_entity === NULL){
      return;
    }

    // Add the entity and the path to the response_data object.
    $this->responseData['data'][$entity->uuid()] = $normalized_entity;

    try {
      $this->responseData['paths'][$entity->toUrl('canonical')->toString()] = $entity->uuid();
    }
    catch (\Exception $exception) {
      // Can't add url so do nothing.
    }
  }

  /**
   * All logged texts.
   *
   * @var string[]
   */
  private $log = [];

  private $lastLogTime;

  /**
   * Add a text to the debug log.
   *
   * @param string $string
   *   The string that get's added to the response log.
   */
  public function log($string) {

    $newTime = microtime(TRUE);
    $this->log[] = '['
      . ($this->lastLogTime ? '+' . round($newTime - $this->lastLogTime, 3) * 1000 . 'ms' : date(DATE_RFC1123))
      . '] ' . $string;

    $this->lastLogTime = $newTime;
  }

}
