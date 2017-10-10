<?php

namespace Drupal\hn\Normalizer;

use Drupal\serialization\Normalizer\FieldItemNormalizer;

/**
 * Transforms internal paths to url strings.
 *
 * An internal link is by default normalized as entity:node/9. This normalizer
 * transforms that to the actual url (/node-9 for example).
 */
class LinkNormalizer extends FieldItemNormalizer {

  protected $format = ['hn'];

  protected $supportedInterfaceOrClass = 'Drupal\link\Plugin\Field\FieldType\LinkItem';

  /**
   * {@inheritdoc}
   */
  public function normalize($field_item, $format = NULL, array $context = []) {
    /** @var \Drupal\link\Plugin\Field\FieldType\LinkItem $field_item */
    $return = parent::normalize($field_item, $format, $context);
    $return['uri'] = $field_item->getUrl()->toString();
    return $return;
  }

}
