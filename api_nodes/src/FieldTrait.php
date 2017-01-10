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
    'file', 'yamlform'
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

    $moduleHandler = \Drupal::moduleHandler();

    // TODO: Check if there are multiple values in a field.
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

                  // Check if target_type is a yaml_form.
                  if ($targetType === 'yamlform') {
                      $elements = $entity->getElementsDecoded();
                      $yamlSettings = $entity->getSettings();

                      $nodeObject[$name] = array(
                          'elements' => $elements,
                          'settings' => $yamlSettings,
                      );

                      continue;
                  }

                if (empty($nodeObject[$name])) {
                  $nodeObject[$name] = array();
                }
                // Get all fields for a referenced entity field.
                $fields = $this->getFields($entity);
                if (!empty($fields['fid']) && !empty($fields['uri'])) {
                  $this->addFileUri($fields);
                }

                // Check if the entity has a bundle.
                if ($bundle = $entity->bundle()) {
                  // Invoke all hooks.
                  $paragraphContent = $moduleHandler->invokeAll('alter_paragraph_json', array('bundle' => $bundle));

                  // Check if the array returned isn't empty.
                  if (empty($paragraphContent) === FALSE) {
                    $fields = array_merge($fields, $paragraphContent);
                  }
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
