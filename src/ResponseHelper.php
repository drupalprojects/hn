<?php

namespace Drupal\headless_drupal;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ResponseHelper {

  /**
   * Function wich returns the correct response.
   *
   * @param $code int
   *    Status code
   * @param $data array|string|null
   *    Data that should be returned with the response
   */
  public static function throwResponse($code, $data = NULL) {
    switch ($code) {
      case 400:
        throw new BadRequestHttpException();
      case 403:
        throw new AccessDeniedHttpException();
      case 404:
        throw new NotFoundHttpException();
    }
  }
}