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

  /**
   * The response service to get and change the responseData of.
   *
   * @var \Drupal\hn\HnResponseService
   */
  private $responseService;

  /**
   * Creates an response event.
   *
   * @param \Drupal\hn\HnResponseService $hn_response_service
   *   The response service to get and change the responseData of.
   */
  public function __construct(HnResponseService $hn_response_service) {
    $this->responseService = $hn_response_service;
  }

  /**
   * Returns the response data.
   *
   * @return array
   *   The current response data.
   */
  public function getResponseData() {
    return $this->responseService->responseData;
  }

  /**
   * Changes the response data.
   *
   * @param array $responseData
   *   The new response data.
   */
  public function setResponseData(array $responseData) {
    $this->responseService->responseData = $responseData;
  }

}
