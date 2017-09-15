<?php

namespace Drupal\hn_test_events\EventSubscriber;

use Drupal\hn\Event\HnResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class DefaultSubscriber.
 */
class DefaultSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {

    return [
      HnResponseEvent::CREATED => 'createdEvent',
      HnResponseEvent::CREATED_CACHE_MISS => 'createdCacheMissEvent',
      HnResponseEvent::POST_ENTITIES_ADDED => 'postEntitiesAddedEvent',
      HnResponseEvent::PRE_SEND => 'preSendEvent',
    ];
  }

  /**
   *
   */
  public function createdEvent(HnResponseEvent $event) {
    $this->addEventIdToResponse($event, HnResponseEvent::CREATED);
  }

  /**
   *
   */
  public function createdCacheMissEvent(HnResponseEvent $event) {
    $this->addEventIdToResponse($event, HnResponseEvent::CREATED_CACHE_MISS);
  }

  /**
   *
   */
  public function postEntitiesAddedEvent(HnResponseEvent $event) {
    $this->addEventIdToResponse($event, HnResponseEvent::POST_ENTITIES_ADDED);
  }

  /**
   *
   */
  public function preSendEvent(HnResponseEvent $event) {
    $this->addEventIdToResponse($event, HnResponseEvent::PRE_SEND);
  }

  /**
   *
   */
  private function addEventIdToResponse(HnResponseEvent $event, $eventId) {
    $responseData = $event->getResponseData();
    $responseData['subscriber'][$eventId] = TRUE;
    $event->setResponseData($responseData);
  }

}
