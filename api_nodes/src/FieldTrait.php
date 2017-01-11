<?php

namespace Drupal\api_nodes;

use Drupal\Core\Entity\Entity;
use Drupal\Core\Entity\Plugin\DataType\EntityReference;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;

/**
 * Function for getting the fields from a node.
 */
trait FieldTrait {

  /**
   * This function gets all fields from a given node.
   *
   * @param Entity $node
   *   A entity which you want all fields from.
   * @param array|null $returnArray
   *   The array that should be returned.
   *
   * @return array
   *   The full node.
   */
  private function getFullNode(Entity $node = NULL, $returnArray = array()) {
    $moduleHandler = \Drupal::moduleHandler();

    if ($node) {
      // Get all the fields from the node.
      foreach ($node->getFields() as $field) {
        $name = $field->getName();

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

                $this->getReferencedNode($property, $name, $returnArray);
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
              $this->getValue($field, $returnArray[$name]);
            }

            continue;
          }

          $this->getValue($field, $returnArray[$name]);

          $moduleHandler->invokeAll('api_alter_field_data',
            array(
              'value' => $value,
              'returnArray' => &$returnArray[$name],
            ));
        }
        $this->arrayOrObject($returnArray[$name]);
      }
    }
    return $returnArray;
  }

  /**
   * Get the value or target_id from a normal field.
   *
   * @param FieldItemList $field
   *   The field the value/target_id should be obtained from.
   * @param array|null $returnArray
   *   A referenced array.
   */
  private function getValue(FieldItemList $field, &$returnArray) {
    if (isset($field->value)) {
      $returnArray[] = $field->value;
    }
    elseif (isset($field->target_id)) {
      $returnArray[] = $field->target_id;
    }
  }

  /**
   * Get the full node for a referenced Item.
   *
   * @param Entity $entity
   *   A Entity you want the full node from.
   * @param string $name
   *   The name of the field.
   * @param array|null $returnArray
   *   The referenced array.
   */
  private function getReferencedNode(Entity $entity, $name, &$returnArray) {
    if (method_exists($entity, 'getFields')) {
      $node = $this->getFullNode($entity);
      $returnArray[$name][] = $node;
    }
  }

  /**
   * This function checks if it should be a array.
   *
   * @param array|null $returnArray
   *   A referenced array.
   */
  private function arrayOrObject(&$returnArray) {
    if (is_array($returnArray) && count($returnArray) == 1) {
      $returnArray = $returnArray[0];
    }
  }

}
