<?php

namespace Drupal\api_view_serializer\Plugin\Field\FieldFormatter;

use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatterBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'api_view_serializer' formatter.
 *
 * @FieldFormatter(
 *   id = "api_view_serializer",
 *   label = @Translation("Image Raw"),
 *   field_types = {
 *     "image"
 *   }
 * )
 */
class UrlApiFormatter extends ImageFormatterBase {
  use \Drupal\api_nodes\FileUrlsTrait;

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = array();
    foreach ($items as $delta => $item) {
      if ($item->entity) {
        $fields = array();
        $fields['filemime'] = $item->entity->getMimeType();
        $fields['url'] = $item->entity->getFileUri();

        $mimeParts = explode('/', $fields['filemime']);
        if (reset($mimeParts) == 'image') {
          $fields['styles'] = $this->getImageStyleUris($item->entity->getFileUri());
        }

        $elements[$delta] = array(
          '#markup' => json_encode($fields),
        );
      }
    }
    return $elements;
  }

}
