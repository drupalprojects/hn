<?php

namespace Drupal\hn;

use Drupal\Core\Entity\FieldableEntityInterface;

class EntitiesWithViews {

  /**
   * @var EntityWithViews[]
   */
  private $entities = [];

  /**
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param $view_mode
   * @return \Drupal\hn\EntityWithViews
   */
  public function addEntity($entity, $view_mode = 'default') {
    if (!$entity instanceof FieldableEntityInterface) {
      return null;
    }
    if(!isset($this->entities[$entity->uuid()])) {
      $this->entities[$entity->uuid()] = new EntityWithViews($entity);
    }
    $entity_with_views = $this->entities[$entity->uuid()];
    $entity_with_views->addViewMode($view_mode);

    return $entity_with_views;
  }

}

class EntityWithViews {

  /**
   * @var \Drupal\Core\Entity\EntityInterface
   */
  private $entity;

  /**
   * @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface[]
   */
  private $view_modes = [];

  /**
   * @param \Drupal\Core\Entity\EntityInterface $entity
   */
  function __construct($entity) {
    $this->entity = $entity;
  }

  public function getEntity() {
    return $this->entity;
  }

  public function addViewMode($view_mode) {

    if(!isset($this->view_modes[$view_mode])) {
      $this->view_modes[$view_mode] = entity_get_display($this->entity->getEntityTypeId(), $this->entity->bundle(), $view_mode);
    }
  }

  public function getViewModes() {
    return array_keys($this->view_modes);
  }

  public function getHiddenFields() {

    $hidden = [];
    foreach($this->view_modes as $display) {
      $hidden[] = array_keys($display->toArray()['hidden']);
    }

    if(count($hidden) === 1) return $hidden[0];

    $hidden_fields = array_intersect(...$hidden);

    return $hidden_fields;
  }

  /**
   * @param $view_mode
   * @return \Drupal\Core\Entity\Display\EntityViewDisplayInterface
   */
  public function getDisplay($view_mode) {
    return $this->view_modes[$view_mode];
  }

}
