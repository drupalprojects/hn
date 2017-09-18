<?php

namespace Drupal\hn\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * This event is invoked whenever something happens with an entity.
 *
 * It can be used to alter the entity and view mode.
 */
class HnHandledEntityEvent extends Event {

  /**
   * This event is emitted as soon as the entity is handled (normalized) by
   * an entity handler. You can alter the handled entity here before adding it
   * to the response.
   */
  const POST_HANDLE = 'hn.handledentity.posthandle';

  /**
   * Creates a new HN Entity Event.
   *
   * @param array $entity
   *   The entity that was handled.
   * @param string $viewMode
   *   The view mode the event is about.
   */
  public function __construct(array $entity, $viewMode = 'default') {
    $this->entity = $entity;
    $this->viewMode = $viewMode;
  }

  private $entity;

  private $viewMode;

  /**
   * Entity getter.
   *
   * @return array
   *   The entity.
   */
  public function getEntity() {
    return $this->entity;
  }

  /**
   * Entity setter.
   *
   * @param array $entity
   *   The entity to set.
   */
  public function setEntity(array $entity) {
    $this->entity = $entity;
  }

  /**
   * View mode getter.
   *
   * @return string
   *   The view mode.
   */
  public function getViewMode() {
    return $this->viewMode;
  }

}
