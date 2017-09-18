<?php

namespace Drupal\hn\Event;

use Drupal\hn\HnResponseService;
use Symfony\Component\EventDispatcher\Event;

/**
 * This event is invoked whenever something happens with the response event.
 *
 * It can be used to alter the response.
 */
class HnResponseEvent extends Event {

  /**
   * This event is triggered as soon as the HN response is created.
   * The responseData array will always be empty, except when another
   * eventListener added data.
   */
  const CREATED = 'hn.response.created';

  /**
   * This event is triggered when there was no cache found for the response.
   * The responseData array will always be empty, except when another
   * eventListener added data.
   */
  const CREATED_CACHE_MISS = 'hn.response.created.cache-miss';

  /**
   * This event is triggered when all entities are added to the response.
   */
  const POST_ENTITIES_ADDED = 'hn.response.done.entities';

  /**
   * This event is sent just before the response is sent back to the browser.
   */
  const PRE_SEND = 'hn.response.done';

  /** @var \Drupal\hn\HnResponseService */
  private $responseService;

  /**
   * Creates an response event.
   *
   * @param \Drupal\hn\HnResponseService $hn_response_service
   */
  public function __construct(HnResponseService $hn_response_service) {
    $this->responseService = $hn_response_service;
  }

  /**
   * Returns the response data.
   *
   * @return array
   */
  public function getResponseData() {
    return $this->responseService->responseData;
  }

  public function setResponseData(array $responseData) {
    return $this->responseService->responseData = $responseData;
  }

}
