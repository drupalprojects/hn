<?php

namespace Drupal\hn\Plugin\HnEntityManagerPlugin;

/**
 * Provides a HN Entity Handler for the FieldableEntity entity.
 *
 * @HnEntityManagerPlugin(
 *   id = "hn_eck_entity"
 * )
 */
class EckEntityHandler extends FieldableEntityHandler {

  protected $supports = 'Drupal\eck\Entity\EckEntityBundle';

}
