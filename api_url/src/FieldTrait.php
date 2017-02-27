<?php

namespace Drupal\api_url;

use Drupal\Core\Entity\Plugin\DataType\EntityReference;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\TypedData\TypedData;

/**
 * Function for getting the fields from a node.
 */
trait FieldTrait {

  /**
   * This function gets all fields from a given node.
   *
   * @param \Drupal\Core\Entity\Entity|null $node
   *   A entity which you want all fields from.
   * @param array|null $returnArray
   *   The array that should be returned.
   *
   * @return array
   *   The full node.
   */
  private function getFullNode($node = NULL, $returnArray = array()) {
    $moduleHandler = \Drupal::moduleHandler();

    if ($node) {
      // Get all the fields from the node.
      foreach ($node->getFields() as $field) {
        $name = $field->getName();

        // This variable shows how many values are allowed in a field.
        // -1 means unlimited.
        $cardinality = -1;
        if ($fieldDefinition = $field->getFieldDefinition()) {
          $cardinality = $fieldDefinition->getFieldStorageDefinition()->getCardinality();
        }

        // Loop through all the values in a field.
        foreach ($field as $value) {

          // Check if the value is a entity reference.
          if ($value instanceof EntityReferenceItem) {

            // Loop through all the properties.
            foreach ($value->getProperties(TRUE) as $property) {
              // Check if property is a entityReference and not a type,
              // because when it is a type the property->getValue()
              // doesn't work.
              // @TODO: Check if getValue() on type property in a not dirty way.
              if ($property instanceof EntityReference && $name !== 'type') {
                $property = $property->getValue();

                self::getReferencedNode($property, $name, $returnArray);
              }

              // Call hook if you want to return custom data for a entity
              // reference value.
              $moduleHandler->invokeAll('api_alter_entity_reference_data',
                array(
                  'property' => $property,
                  'value' => $value,
                  'returnArray' => &$returnArray[$name],
                ));
            }
            // If type get value.
            // @TODO: This is really dirty i think, should do it another way.
            if ($name === 'type') {
              self::getValue($field, $returnArray[$name]);
            }

            continue;
          }

          self::getValue($value, $returnArray[$name]);

          $moduleHandler->invokeAll('api_alter_field_data',
            array(
              'value' => $value,
              'returnArray' => &$returnArray[$name],
            ));
        }
        self::arrayOrObject($returnArray[$name], $cardinality);
      }
    }
    return $returnArray;
  }

  /**
   * Get the value or target_id from a normal field.
   *
   * @param TypedData $fieldValue
   *   The field the value/target_id should be obtained from.
   * @param array|null $returnArray
   *   A referenced array.
   */
  private function getValue(TypedData $fieldValue, &$returnArray) {
    if (isset($fieldValue->value)) {
      $returnArray[] = $fieldValue->value;
    }
    elseif (isset($fieldValue->target_id)) {
      $returnArray[] = $fieldValue->target_id;
    }
  }

  /**
   * Get the full node for a referenced Item.
   *
   * @param \Drupal\Core\Entity\Entity|null $entity
   *   A Entity you want the full node from.
   * @param string $name
   *   The name of the field.
   * @param array|null $returnArray
   *   The referenced array.
   */
  private function getReferencedNode($entity, $name, &$returnArray) {
    if (method_exists($entity, 'getFields')) {
      $node = self::getFullNode($entity);
      $returnArray[$name][] = $node;
    }
  }

  /**
   * This function checks if it should be a array.
   *
   * @param array|null $returnArray
   *   A referenced array.
   */
  private function arrayOrObject(&$returnArray, $cardinality) {
    if (is_array($returnArray) && count($returnArray) == 1 && $cardinality !== -1 && $cardinality === 1) {
      $returnArray = $returnArray[0];
    }
  }

  static function getFields($node, $returnArray) {
    return self::getFullNode($node, $returnArray);
  }

}
