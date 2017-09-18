<?php

namespace Drupal\hn;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\hn\Event\HnEntityEvent;
use Drupal\hn\Event\HnHandledEntityEvent;
use Drupal\hn\Event\HnResponseEvent;
use Drupal\node\Entity\Node;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\Serializer;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;

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
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface*/
  private $eventDispatcher;

  protected $language;

  /**
   * A list of entities and their views.
   *
   * @var \Drupal\hn\EntitiesWithViews
   */
  public $entitiesWithViews;

  /**
   * Constructs a new HnResponseService object.
   */
  public function __construct(Serializer $serializer, AccountProxy $current_user, ConfigFactory $config_factory, CacheBackendInterface $cache, EventDispatcherInterface $eventDispatcher) {
    $this->serializer = $serializer;
    $this->currentUser = $current_user;
    $this->config = $config_factory;
    $this->language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $this->debugging = \Drupal::request()->query->get('debug', FALSE);
    $this->cache = $cache;
    $this->eventDispatcher = $eventDispatcher;
  }

  protected $responseData;

  protected $debugging = FALSE;

  protected $cache;

  /**
   * This invokes a function that can be ca.
   *
   * @param $eventName
   */
  private function alterResponse($eventName) {
    $event = new HnResponseEvent($this->responseData);
    $this->eventDispatcher->dispatch($eventName, $event);
    $this->responseData = $event->getResponseData();
  }

  /**
   * Responds to GET requests.
   *
   * Returns a list of bundles for specified entity.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function getResponseData() {

    $this->responseData = [];

    $this->alterResponse(HnResponseEvent::CREATED);

    $this->log('Creating new Headless Ninja response..');

    $status = 200;

    if (!$this->currentUser->hasPermission('access content')) {
      $cache_string = $path = $this->config->get('system.site')->get('page.403');
      $status = 403;
    }
    else {
      $GET_params = \Drupal::request()->query->all();
      $path = $GET_params['path'];
      unset($GET_params['_format'], $GET_params['debug']);

      // Construct a string to identify the cache object.
      // It will keep all the params in the URL to prevent wrong caching.
      $cache_string = implode('&', array_map(function ($v, $k) {
        // Group together searches like String1+String2.
        // The + characters are replaced for blank spaces.
        return $k . '=' . str_replace(' ', '+', $v);
      }, $GET_params, array_keys($GET_params)));

    }

    if (!$this->debugging && $cache = $this->cache->get('hn.response_cache.' . $cache_string)) {
      return $cache->data;
    }

    $this->alterResponse(HnResponseEvent::CREATED_CACHE_MISS);

    $url = Url::fromUri('internal:/' . trim($path, '/'));

    if (!$url->isRouted()) {
      /** @var \Drupal\redirect\RedirectRepository $redirect_service */
      $redirect_service = \Drupal::service('redirect.repository');
      // Source path has no leading /.
      $source_path = trim($path, '/');
      /** @var \Drupal\redirect\Entity\Redirect $redirect */
      // Get all redirects by original url.
      $redirect = $redirect_service->findMatchingRedirect($source_path, [], $this->language);
      // Check if redirects are found.
      if (!empty($redirect)) {
        // Get 301/302.
        $status = (int) $redirect->getStatusCode();
        // Get URL object from redirect.
        $url = $redirect->getRedirectUrl();
        $this->log('Redirect found from "' . $source_path . '" to "' . $url->toString() . '"');
      }
      // If no redirects are found, throw 404.
      else {
        $this->log('Initial entity url isn\'t routed and no redirects found, getting 404 page..');
        $url = Url::fromUri('internal:/' . trim($this->config->get('system.site')->get('page.404'), '/'));
        $status = 404;
      }
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

    $entity = NULL;

    if (!$entity_type) {
      if (explode('.', $url->getRouteName())[0] === 'view') {
        $entity = \Drupal::entityTypeManager()->getStorage('view')->load(explode('.', $url->getRouteName())[1]);
      }
      else {
        $this->log('Can\'t find entity type of ' . $path . ', returning 404 page.. ' . json_encode($params, TRUE));
        // @todo make more generic.
        $url = Url::fromUri('internal:/' . trim($this->config->get('system.site')->get('page.404'), '/'));
        $status = 404;
        $params = $url->getRouteParameters();
        $entity_type = key($params);
        if (empty($entity_type)) {
          throw new NotFoundHttpException('Can\'t find suitable entity and no 404 is defined. Please enter a 404 url in the site.system settings.');
        }
      }
    }

    if (!$entity) {
      $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($params[$entity_type]);
    }
    if ($entity instanceof Node) {
      $entity = $entity->getTranslation($this->language);
    }

    $this->entitiesWithViews = new EntitiesWithViews();
    $this->addEntity($entity);

    $this->alterResponse(HnResponseEvent::POST_ENTITIES_ADDED);

    $this->responseData['data'][$entity->uuid()]['__hn']['status'] = $status;

    $this->responseData['paths'][$path] = $entity->uuid();

    $this->log('Done building response data.');
    if ($this->debugging) {
      $this->responseData['__hn']['log'] = $this->log;
    }

    $cache_tags = [];

    foreach ($this->entitiesWithViews->getEntities() as $entity) {
      foreach ($entity->getCacheTags() as $cache_tag) {
        $cache_tags[] = $cache_tag;
      }
    }

    \Drupal::cache()->set('hn.response_cache.' . $cache_string, $this->responseData, Cache::PERMANENT, $cache_tags);

    $this->alterResponse(HnResponseEvent::PRE_SEND);

    return $this->responseData;
  }

  private $alreadyAdded = [];

  /**
   * Adds an entity to $this->response_data.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to be added.
   * @param string $view_mode
   *   The view mode to be added.
   */
  public function addEntity(EntityInterface $entity, $view_mode = 'default') {

    $alreadyAddedKey = $entity->uuid() . ':' . $view_mode;
    if (in_array($alreadyAddedKey, $this->alreadyAdded)) {
      return;
    }
    $this->alreadyAdded[] = $alreadyAddedKey;

    $event = new HnEntityEvent($entity, $view_mode);
    $this->eventDispatcher->dispatch(HnEntityEvent::ADDDED, $event);
    $entity = $event->getEntity();
    $view_mode = $event->getViewMode();

    /** @var \Drupal\hn\Plugin\HnEntityManagerPluginManager $hnEntityManagerPluginManager */
    $hnEntityManagerPluginManager = \Drupal::getContainer()->get('plugin.manager.hn_entity_manager_plugin');

    $entityHandler = $hnEntityManagerPluginManager->getEntityHandler($entity);

    if (!$entityHandler) {
      $this->log('Not adding entity of type ' . get_class($entity));
      return;
    }

    $this->log('Handling entity ' . $entity->uuid() . ' with ' . $entityHandler->getPluginId());

    $normalized_entity = $entityHandler->handle($entity, $view_mode);

    if (empty($normalized_entity)) {
      return;
    }

    $normalized_entity['__hn']['entity']['type'] = $entity->getEntityTypeId();
    $normalized_entity['__hn']['entity']['bundle'] = $entity->bundle();

    try {
      $url = $entity->toUrl('canonical')->toString();
      $this->responseData['paths'][$url] = $entity->uuid();
      $normalized_entity['__hn']['url'] = $url;
    }
    catch (\Exception $exception) {
      // Can't add url so do nothing.
    }

    $event = new HnHandledEntityEvent($normalized_entity, $view_mode);
    $this->eventDispatcher->dispatch(HnHandledEntityEvent::POST_HANDLE, $event);
    $normalized_entity = $event->getEntity();

    // Add the entity and the path to the response_data object.
    $this->responseData['data'][$entity->uuid()] = $normalized_entity;
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
      . ($this->lastLogTime ? '+' . round($newTime - $this->lastLogTime, 5) * 1000 . 'ms' : date(DATE_RFC1123))
      . '] ' . $string;

    $this->lastLogTime = $newTime;
  }

}
