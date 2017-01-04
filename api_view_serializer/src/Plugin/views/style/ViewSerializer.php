<?php

namespace Drupal\api_view_serializer\Plugin\views\style;

use Drupal\Core\Render\Markup;
use Drupal\rest\Plugin\views\style\Serializer;

/**
 * The style plugin for serialized output formats.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "view_serializer",
 *   title = @Translation("View Serializer"),
 *   help = @Translation("Serializes views."),
 *   display_types = {"data"}
 * )
 */
class ViewSerializer extends Serializer {

  /**
   * {@inheritdoc}
   */
  public function render() {
    $rows = array();

    $viewId = $this->view->id();

    // If the Data Entity row plugin is used, this will be an array of entities
    // which will pass through Serializer to one of the registered Normalizers,
    // which will transform it to arrays/scalars. If the Data field row plugin
    // is used, $rows will not contain objects and will pass directly to the
    // Encoder.
    foreach ($this->view->result as $row_index => $row) {
      $this->view->row_index = $row_index;
      $rows[] = $this->view->rowPlugin->render($row);
    }

    foreach ($rows as $rowKey => $row) {
      foreach ($row as $key => $field) {
        if ($field instanceof Markup) {
          // I create this ugly array because markup returns json string
          // like this:
          // {"id":"2"}, {"id":"3"}, {"id":"4"}
          // There should be array brackets around it, but markup doesn't add
          // them.
          $array = '[' . $field->jsonSerialize() . ']';

          // Decode if markup is json.
          $json = json_decode($array,
            TRUE);
          $result = $json;

          // Check if result is array but only has 1 item.
          if (is_array($result) && count($result) == 1) {
            $result = $result[0];
          }

          // If markup isn't json just use the original value.
          if (json_last_error() !== JSON_ERROR_NONE) {
            $result = $field;
          }

          // Set rows to result.
          $rows[$rowKey][$key] = $result;
        }
      }
    }

    // Get filters from view configuration.
    $config = \Drupal::config('views.view.' . $viewId);
    $filters = $config->get('display.default.display_options.filters');

    unset($this->view->row_index);

    // Get the content type configured in the display or fallback to the
    // default.
    $content_type = !empty($this->options['formats']) ? reset($this->options['formats']) : 'json';
    if ((empty($this->view->live_preview))) {
      $content_type = $this->displayHandler->getContentType();
    }

    $rows = [
      $viewId => $rows,
      'filters' => $filters,
    ];
    return $this->serializer->serialize($rows, $content_type);
  }

}
