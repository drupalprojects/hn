<?php

namespace Drupal\hn;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactory;
use Symfony\Component\Serializer\Serializer;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Entity\EntityInterface;
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
  public function __construct(Serializer $serializer, AccountProxy $current_user, ConfigFactory $config_factory) {
    $this->serializer = $serializer;
    $this->currentUser = $current_user;
    $this->config = $config_factory;
    $this->language = \Drupal::languageManager()->getCurrentLanguage()->getId();
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
  public function getResponseData() {
    $this->log('Creating new Headless Ninja response..');
    $DEBUG = \Drupal::request()->query->get('debug', FALSE);

    $status = 200;

    if (!$this->currentUser->hasPermission('access content')) {
      $path = $this->config->get('system.site')->get('page.403');
      $status = 403;
    }
    else {
      $path = \Drupal::request()->query->get('path', '');
    }

    if (!$DEBUG && $cache = \Drupal::cache()->get('hn.response_cache.' . $path)) {
      return $cache->data;
    }

    $url = Url::fromUri('internal:/' . trim($path, '/'));

    if (!$url->isRouted()) {
      $this->log('Initial entity url isn\'t routed, getting 404 page..');
      $url = Url::fromUri('internal:/' . trim($this->config->get('system.site')->get('page.404'), '/'));
      $status = 404;
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
      if(explode('.', $url->getRouteName())[0] === 'view') {
        $entity = \Drupal::entityTypeManager()->getStorage('view')->load(explode('.', $url->getRouteName())[1]);
      }
      else {
        $this->log('Can\'t find entity type of ' . $path . ', returning 404 page.. ' . json_encode($params, TRUE));
        // TODO: make more generic
        $url = Url::fromUri('internal:/' . trim($this->config->get('system.site')->get('page.404'), '/'));
        $status = 404;
        $params = $url->getRouteParameters();
        $entity_type = key($params);
      }
    }

    if (!$entity) {
      $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($params[$entity_type]);
    }
    if($entity instanceof Node) {
      $entity = $entity->getTranslation($this->language);
    }

    $this->entitiesWithViews = new EntitiesWithViews();
    $this->addEntity($entity);

    $this->responseData['data'][$entity->uuid()]['__hn']['status'] = $status;

    $this->responseData['paths'][$path] = $entity->uuid();

    $this->log('Done building response data.');
    if ($DEBUG) {
      $this->responseData['__hn']['log'] = $this->log;
    }

    $cache_tags = [];

    foreach ($this->entitiesWithViews->getEntities() as $entity) {
      foreach($entity->getCacheTags() as $cache_tag) {
        $cache_tags[] = $cache_tag;
      }
    }

    \Drupal::cache()->set('hn.response_cache.' . $path, $this->responseData, Cache::PERMANENT, $cache_tags);

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
    if(in_array($alreadyAddedKey, $this->alreadyAdded)) return;
    $this->alreadyAdded[] = $alreadyAddedKey;

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
      . ($this->lastLogTime ? '+' . round($newTime - $this->lastLogTime, 5) * 1000 . 'ms' : date(DATE_RFC1123))
      . '] ' . $string;

    $this->lastLogTime = $newTime;
  }

}
