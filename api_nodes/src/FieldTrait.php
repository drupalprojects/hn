<?php

namespace Drupal\api_nodes;

use Drupal\Core\Entity\Plugin\DataType\EntityReference;

/**
 * Functions for adding file uri's to file fields.
 */
trait FieldTrait {

  /**
   * All allowed EntityReferences.
   *
   * @var array
   */
  private $allowedEntityReferences = [
    'paragraph',
    'file',
  ];

  /**
   * Get fields for node Object.
   *
   * @param \Drupal\node\Entity\Node|null $node
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
        // Get all values from a field.
        foreach ($field_items as $field_item) {
          // Check if the field is one of the allowed referenced entity fields.
          if (in_array($targetType, $this->allowedEntityReferences)) {
            // Loop through the properties.
            foreach ($field_item->getProperties(TRUE) as $property) {
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
            }
            continue;
          }
          // Check if target_type is a view.
          elseif ($targetType === 'view') {
            // Get all fields from the view paragraph.
            $fields = $field_item->getValue();
            $renderer = \Drupal::service('renderer');
            $embedded_view = views_embed_view($fields['target_id'], $fields['display_id']);
            $rendered_view = $renderer->render($embedded_view);
            $nodeObject[$name] = json_decode($rendered_view);
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
    return $nodeObject;
  }

}
