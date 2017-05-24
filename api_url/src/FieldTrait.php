<?php

namespace Drupal\api_url;

use Drupal\Core\Entity\Plugin\DataType\EntityReference;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\TypedData\TypedData;
use Drupal\paragraphs\Entity\Paragraph;

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
   * @param array $parents
   *   Array with all the parent nodes.
   *
   * @return array The full node.
   * The full node.
   */
  static function getFullNode($node = NULL, $returnArray = [], $parents = []) {
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

                FieldTrait::getReferencedNode($property, $name, $returnArray, $parents);
              }

              // Call hook if you want to return custom data for a entity
              // reference value.
              $moduleHandler->invokeAll('api_alter_field_entity_reference', [
                'property' => $property,
                'value' => $value,
                'returnArray' => &$returnArray[$name],
              ]);
            }
            // If type get value.
            // @TODO: This is really dirty i think, should do it another way.
            if ($name === 'type') {
              self::getValue($field, $returnArray[$name]);
            }

            continue;
          }

          self::getValue($value, $returnArray[$name]);

          $moduleHandler->invokeAll('api_alter_field_value', [
            'value' => $value,
            'returnArray' => &$returnArray[$name],
          ]);
        }

        $moduleHandler->invokeAll('api_alter_field', [
          'field' => $field,
          'returnArray' => &$returnArray[$name],
        ]);

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
  static private function getValue(TypedData $fieldValue, &$returnArray) {
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
   * @param array|null $parents
   *   Array with all the parent nodes.
   *
   * @return array
   */
  static function getReferencedNode($entity, $name, &$returnArray, $parents) {
    if (method_exists($entity, 'getFields')) {

      foreach ($parents as $parent) {

        if ($parent == $entity) {
          return $returnArray[$name][] = [
            'test_uuid' => $parent->uuid->getValue()[0]['value'],
            'type' => $parent->type->getValue()[0]['target_id'],
          ];
        }
      }

      if (count($parents) >= 2 && $entity instanceOf Paragraph == FALSE) {
        return $returnArray[$name] = NULL;
      }

      $parents[] = $entity;
      $node = self::getFullNode($entity, [], $parents);
      return $returnArray[$name][] = $node;
    }
  }

  /**
   * This function checks if it should be a array.
   *
   * @param array|null $returnArray
   *   A referenced array.
   */
  public static function arrayOrObject(&$returnArray, $cardinality) {
    if (is_array($returnArray) && count($returnArray) == 1 && $cardinality !== -1 && $cardinality === 1) {
      $returnArray = $returnArray[0];
    }
  }

  static function getFields($node, $returnArray) {
    return self::getFullNode($node, $returnArray);
  }

}
