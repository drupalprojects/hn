<?php

namespace Drupal\api_url;

/**
 * Functions for adding file uri's to file fields.
 */
trait FileUrlsTrait {

  /**
   * Add uri to file fields.
   */
  private function addFileUri(&$fields) {
    $fileStorage = \Drupal::entityTypeManager()->getStorage('file');
    $file = $fileStorage->load($fields['fid']);
    $fields['filemime'] = $file->getMimeType();
    $fields['url'] = $file->url();
    $mimeParts = explode('/', $fields['filemime']);
    if (reset($mimeParts) == 'image') {
      $fields['styles'] = $this->getImageStyleUris($file->getFileUri());
    }
  }

  /**
   * Generate uri for each image style.
   */
  private function getImageStyleUris($uri) {
    $output = [];
    $imageStyleStorage = \Drupal::entityTypeManager()->getStorage('image_style');
    foreach (\Drupal::entityQuery('image_style')->execute() as $name) {
      $style = $imageStyleStorage->load($name);
      $output[$name] = $style->buildUrl($uri);
    }
    return $output;
  }

  /**
   * Add uri to file fields.
   */
  static public function getFileUri($file) {
    $fields['filemime'] = $file->getMimeType();
    $fields['url'] = $file->url();
    $mimeParts = explode('/', $fields['filemime']);
    if (reset($mimeParts) == 'image') {
      $fields['styles'] = FileUrlsTrait::getImageStyles($file->getFileUri());
    }
    return $fields;
  }

  /**
   * Generate uri for each image style.
   */
  static public function getImageStyles($uri) {
    $output = [];
    $imageStyleStorage = \Drupal::entityTypeManager()->getStorage('image_style');
    foreach (\Drupal::entityQuery('image_style')->execute() as $name) {
      $style = $imageStyleStorage->load($name);
      $output[$name] = $style->buildUrl($uri);
    }
    return $output;
  }

}
