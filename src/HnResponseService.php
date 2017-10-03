<?php

namespace Drupal\hn;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\hn\Event\HnEntityEvent;
use Drupal\hn\Event\HnHandledEntityEvent;
use Drupal\hn\Event\HnResponseEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\Serializer;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

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
   * Drupal\Core\Config\ConfigFactory definition.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $config;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  private $eventDispatcher;

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
    $this->cache = $cache;
    $this->eventDispatcher = $eventDispatcher;
  }

  public $responseData;

  protected $debugging = FALSE;

  protected $cache;

  /**
   * This invokes a function that can be ca.
   *
   * @param $eventName
   *   The event name to emit.
   */
  private function alterResponse($eventName) {
    $event = new HnResponseEvent($this);
    $this->eventDispatcher->dispatch($eventName, $event);
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

    $config = $this->config->get('hn.settings');

    // First, get the current request.
    $r = \Drupal::request();

    // Then, get the path that was requested.
    $path = $r->query->get('path', '');

    // Create a new request with all the options of the old request, but with
    // the new url. This also merges the old query with the query in the path.
    $r2 = Request::create($r->getBaseUrl() . $path, 'GET', $r->query->all(), [], [], $r->server->all());

    // The current request is re-initialized with the original request, but
    // with the new query and server variables. Those are the only things
    // that change when the url changes.
    $r->initialize(
      $r2->query->all(),
      $r->request->all(),
      $r->attributes->all(),
      $r->cookies->all(),
      $r->files->all(),
      $r2->server->all()
    );

    // Also the languageManager needs to be reset, in order for the active
    // language to be re-calculated.
    \Drupal::languageManager()->reset();

    $this->debugging = \Drupal::request()->query->get('debug', FALSE);

    $this->responseData = [];

    $this->alterResponse(HnResponseEvent::CREATED);

    $this->log('Creating new Headless Ninja response..');

    $status = 200;

    if (!$this->currentUser->hasPermission('access content')) {
      $path = $this->config->get('system.site')->get('page.403');
      $status = 403;
    }

    // Check if this page is cached.
    if ($config->get('cache') && !$this->debugging && $cache = $this->cache->get('hn.response_cache.' . $path)) {
      $this->responseData = $cache->data;
    }

    else {
      $this->getResponseDataWithoutCache($path, $status);

      $cache_tags = [];

      foreach ($this->entitiesWithViews->getEntities() as $entity) {
        foreach ($entity->getCacheTags() as $cache_tag) {
          $cache_tags[] = $cache_tag;
        }
      }

      if ($config->get('cache')) {
        \Drupal::cache()->set('hn.response_cache.' . $path, $this->responseData, Cache::PERMANENT, $cache_tags);
      }
    }

    $this->alterResponse(HnResponseEvent::PRE_SEND);

    return $this->responseData;
  }

  /**
   * Creates response data.
   *
   * @param string $path
   *   The path to start with.
   * @param int $status
   *   The status of the path to start with.
   */
  private function getResponseDataWithoutCache(&$path, $status) {

    $this->alterResponse(HnResponseEvent::CREATED_CACHE_MISS);

    $url = Url::fromUri('internal:/' . trim($path, '/'));

    // @todo Move the redirect resolver to another module.
    if (!$url->isRouted()) {
      /** @var \Drupal\redirect\RedirectRepository $redirect_service */
      $redirect_service = \Drupal::service('redirect.repository');
      // Source path has no leading /.
      $source_path = trim($path, '/');
      /** @var \Drupal\redirect\Entity\Redirect $redirect */
      // Get all redirects by original url.
      $redirect = $redirect_service->findMatchingRedirect($source_path, []);
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

    $this->entitiesWithViews = new EntitiesWithViews();
    $this->addEntity($entity);

    $this->alterResponse(HnResponseEvent::POST_ENTITIES_ADDED);

    $this->responseData['data'][$entity->uuid()]['__hn']['status'] = $status;

    $this->responseData['paths'][$path] = $entity->uuid();

    $this->log('Done building response data.');
    if ($this->debugging) {
      $this->responseData['__hn']['log'] = $this->log;
    }
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
    $this->eventDispatcher->dispatch(HnEntityEvent::ADDED, $event);
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

    $event = new HnHandledEntityEvent($entity, $normalized_entity, $view_mode);
    $this->eventDispatcher->dispatch(HnHandledEntityEvent::POST_HANDLE, $event);
    $normalized_entity = $event->getHandledEntity();

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
