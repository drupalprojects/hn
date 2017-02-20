<?php

namespace Drupal\headless_drupal;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * This class helps to get a good response.
 */
class ResponseHelper {

  /**
   * Function wich returns the correct response.
   *
   * @param int $code
   *    Status code.
   * @param array|string|null $data
   *    Data that should be returned with the response.
   */
  public static function throwResponse($code, $data = NULL) {
    switch ($code) {
      case 400:
        throw new BadRequestHttpException($data);

      case 403:
        throw new AccessDeniedHttpException($data);

      case 404:
        throw new NotFoundHttpException($data);

    }
  }

}
