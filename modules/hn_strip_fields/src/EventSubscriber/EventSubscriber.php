<?php

namespace Drupal\hn_strip_fields\EventSubscriber;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\hn\Event\HnEntityEvent;
use Drupal\hn\Event\HnHandledEntityEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class DefaultSubscriber.
 */
class EventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {

    return [
      HnEntityEvent::ADDDED => 'nullifyEntityProperties',
      HnHandledEntityEvent::POST_HANDLE => 'unsetEntityProperties',
    ];

  }

  /**
   * This nullifies entity properties before they are normalized.
   *
   * This improves performance, because these properties don't need to be
   * handled by the normalizer.
   *
   * @param \Drupal\hn\Event\HnEntityEvent $event
   *   The event.
   */
  public function nullifyEntityProperties(HnEntityEvent $event) {

    $entity = $event->getEntity();

    if (!$entity instanceof FieldableEntityInterface) {
      return;
    }

    $config = \Drupal::config('hn_strip_fields.settings');

    $removed_properties = ($config->get('strip.' . $entity->getEntityTypeId()));

    if (!empty($removed_properties)) {
      $entityDefinition = \Drupal::entityTypeManager()->getDefinition($entity->getEntityTypeId());
      $entityKeys = $entityDefinition->getKeys();
      $removed_properties = array_diff($removed_properties, $entityKeys);
      foreach ($removed_properties as $removed_property) {
        $entity->set($removed_property, NULL);
      }
    }

  }

  /**
   * This removes entity properties after they are normalized.
   *
   * @param \Drupal\hn\Event\HnHandledEntityEvent $event
   *   The event.
   */
  public function unsetEntityProperties(HnHandledEntityEvent $event) {

    $entity = $event->getEntity();
    $handledEntity = $event->getHandledEntity();

    if (!$entity instanceof FieldableEntityInterface) {
      return;
    }

    $config = \Drupal::config('hn_strip_fields.settings');

    $removed_properties = ($config->get('strip.' . $entity->getEntityTypeId()));

    if (!empty($removed_properties)) {
      foreach ($removed_properties as $removed_property) {
        unset($handledEntity[$removed_property]);
      }
    }

    $event->setHandledEntity($handledEntity);
  }

}
