<?php

namespace Drupal\api_nodes;

use Drupal\node\Entity\Node;
use Drupal\Core\Entity\Plugin\DataType\EntityReference;
use Drupal\views\Entity\View;

/**
 * Functions for adding file uri's to file fields.
 */
trait FieldTrait {

  private $allowedEntityReferences = [
    'paragraph', 'file', 'view'
  ];

  /**
   * Get fields for node Object.
   *
   * @param Node|null $node
   *   Node.
   * @param array $nodeObject
   *   Array.
   *
   * @return array
   *   Returns nodeObject.
   */
  private function getFields($node = NULL, array $nodeObject = array()) {
    if ($node) {
      // Loop through all node fields.
      foreach ($node->getFields() as $field_items) {
        $targetType = $field_items->getSetting('target_type');
        $name = $field_items->getName();

        if ($targetType !== 'view') {
          foreach ($field_items as $field_item) {
            // Loop over all properties of a field item.
            foreach ($field_item->getProperties(TRUE) as $property) {
              // Check if the field target is a allowed entity reference.
              if (in_array($targetType, $this->allowedEntityReferences)) {
                // Check if it is a entityreference.
                if ($property instanceof EntityReference && $entity = $property->getValue()) {
                  if (empty($nodeObject[$name])) {
                    $nodeObject[$name] = array();
                  }

                  // Get all fields for a referenced entity field.
                  $fields = $this->getFields($entity);
                  if (!empty($fields['fid']) && !empty($fields['uri'])) {
                    $this->addFileUri($fields);
                  }

                  // If the given entity is one of our custom paragraph types
                  // fill it with our own content.
                  $paragraphContent = pvm_paragraphs_paragraph_content($entity);
                  if (empty($paragraphContent) === FALSE) {
                    $fields = array_merge($fields, $paragraphContent);
                  }

                  // Add all fields to the nodeObject.
                  $nodeObject[$name][] = $fields;
                }
                continue;
              }
              if (isset($field_item->value)) {
                $nodeObject[$name] = $field_item->value;
              }
              elseif (isset($field_item->target_id)) {
                $nodeObject[$name] = $field_item->target_id;
              }
            }
          }
        }
        else {
          foreach ($field_items as $field_item) {
            $fields = $field_item->getValue();
            $renderer = \Drupal::service('renderer');
            $embedded_view = views_embed_view($fields['target_id'], $fields['display_id']);
            $rendered_view = $renderer->render($embedded_view);
            $nodeObject[$name] = json_decode($rendered_view);
          }
        }
      }
    }
    return $nodeObject;
  }

}
