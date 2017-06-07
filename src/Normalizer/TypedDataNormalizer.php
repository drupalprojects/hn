<?php
namespace Drupal\hn\Normalizer;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\serialization\Normalizer\TypedDataNormalizer as SerializationTypedDataNormalizer;

/**
 * {@inheritDoc}
 */
class TypedDataNormalizer extends SerializationTypedDataNormalizer {

  protected $format = ['hn'];

  /**
   * {@inheritdoc}
   * @param \Drupal\Core\TypedData\TypedDataInterface $object
   */
  public function normalize($object, $format = NULL, array $context = []) {

    $value = parent::normalize($object, $format, $context);

    // If this is a field with never more then 1 value, show the first value.
    if($object instanceof FieldItemListInterface) {
      $cardinality = $object->getFieldDefinition()->getFieldStorageDefinition()->getCardinality();
      if($cardinality === 1) {
        if(isset($value[0])) $value = $value[0];
        else $value = NULL;
      }
    }
    else {
      \Drupal::logger('hn')->notice('Interesting! ' . get_class($object) . ' is not a FieldItemList!');
    }

    // If the value is an associative array with 'value' as only key, return the value of 'value'.
    if(is_array($value) && isset($value['value']) && count($value) === 1) {
      $value = $value['value'];
    }

    return $value;
  }
}
