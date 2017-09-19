<?php

namespace Drupal\Tests\hn\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Provides helper methods for the HN module's functional tests.
 */
abstract class HnFunctionalTestBase extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'hn_test',
  ];

  /**
   * Gets an Hn Response from a path.
   */
  protected function getHnResponse($path) {
    return $this->drupalGet($this->getAbsoluteUrl('hn?_format=hn&path=' . urlencode($path)));
  }

}
