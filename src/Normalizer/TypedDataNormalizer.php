<?php
namespace Drupal\hn\Normalizer;

use Drupal\serialization\Normalizer\NormalizerBase;

/** * Converts typed data objects to arrays. */
class TypedDataNormalizer extends NormalizerBase {

  /** * The interface or class that this Normalizer supports. * * @var string */
  protected $supportedInterfaceOrClass = 'Drupal\Core\TypedData\TypedDataInterface';

  /** * {@inheritdoc} */
  public function normalize($object, $format = NULL, array $context = []) {
    // This variable shows how many values are allowed in a field.
    // -1 means unlimited.
    $cardinality = -1;
    if ($fieldDefinition = $object->getFieldDefinition()) {
      $cardinality = $fieldDefinition->getFieldStorageDefinition()->getCardinality();
    }

    $value = $object->getValue();
    if ($cardinality !== -1 && $cardinality === 1) {
      if (isset($value[0]) && isset($value[0]['value'])) {
        $value = $value[0]['value'];
      }
    }
    return $value;
  }
}
