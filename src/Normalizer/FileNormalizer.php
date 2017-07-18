<?php

namespace Drupal\hn\Normalizer;

use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\image\Plugin\Field\FieldType\ImageItem;
use Drupal\serialization\Normalizer\EntityReferenceFieldItemNormalizer;

/**
 * {@inheritDoc}
 */
class FileNormalizer extends EntityReferenceFieldItemNormalizer {

  protected $format = ['hn'];

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = ImageItem::class;

  /**
   * {@inheritdoc}
   * @param \Drupal\Core\TypedData\TypedDataInterface $object
   */
  public function normalize($object, $format = NULL, array $context = []) {
    $normalization = parent::normalize($object, $format, $context);
    if (!$object->isEmpty()) {
      $this->addStyles($object, $normalization, $context);
    }
    return $normalization;
  }

  /**
   * Adds image style information to normalized ImageItem field data.
   *
   * @param \Drupal\image\Plugin\Field\FieldType\ImageItem $item
   *   The image field item.
   * @param array $normalization
   *   The image field normalization to add image style information to.
   * @param array $context
   *   Context options for the normalizer.
   */
  protected function addStyles(ImageItem $item, array &$normalization, array $context) {
    /** @var \Drupal\file\FileInterface $image */
    if ($image = File::load($item->target_id)) {
      $uri = $image->getFileUri();
      /** @var \Drupal\image\ImageStyleInterface[] $styles */
      $styles = ImageStyle::loadMultiple();
      $normalization['image_styles'] = [];
      foreach ($styles as $id => $style) {
        $dimensions = ['width' => $item->width, 'height' => $item->height];
        $style->transformDimensions($dimensions, $uri);
        $normalization['image_styles'][$id] = [
          'url' => file_url_transform_relative($style->buildUrl($uri)),
          'height' => empty($dimensions['height']) ? NULL : $dimensions['height'],
          'width' => empty($dimensions['width']) ? NULL : $dimensions['width'],
        ];
        if (!empty($context['cacheability'])) {
          $context['cacheability']->addCacheableDependency($style);
        }
      }
    }
  }
}
