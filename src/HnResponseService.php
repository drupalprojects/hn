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
  protected $entitiesWithViews;

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
    ] + $this->serializer->normalize($entity);

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

        if ($field_name === 'field_view') {
          print_r($referenced_entities[0]->toArray());
          print get_class($referenced_entities[0]);
          die();
        }

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
