<?php

namespace Drupal\hn\Plugin\HnEntityManagerPlugin;

use Drupal\Core\Entity\EntityInterface;
use Drupal\hn\Plugin\HnEntityManagerPluginBase;
use Drupal\taxonomy\Entity\Term;

/**
 * @HnEntityManagerPlugin(
 *   id = "hn_view"
 * )
 */
class ViewHandler extends HnEntityManagerPluginBase {

  protected $supports = 'Drupal\views\Entity\View';

  /**
   * {@inheritdoc}
   */
  public function handle(EntityInterface $entity, $view_mode = 'default') {
    /** @var \Drupal\views\Entity\View $entity */
    /** @var \Drupal\hn\HnResponseService $responseService */
    $responseService = \Drupal::getContainer()->get('hn.response');

    $display = $entity->getDisplay($view_mode);

    $display_view_mode = $display['display_options']['row']['options']['view_mode'];

    $executable = $entity->getExecutable();
    $executable->execute();
    $results = [];
    foreach ($executable->result as $resultRow) {
      $responseService->addEntity($resultRow->_entity, $display_view_mode);
      $results[] = $resultRow->_entity->uuid();
    }

    $response = [];
    $response['display'] = $display['display_options'];
    unset($response['display']['access']);
    unset($response['display']['cache']);
    unset($response['display']['query']);
    unset($response['display']['style']);
    unset($response['display']['row']);
    unset($response['display']['fields']);

    $filters = [];

    foreach ($response['display']['filters'] as $filter_id => $filter) {
      if (!empty($filter['exposed'])) {
        if($filter['plugin_id'] === 'taxonomy_index_tid') {
          $query = \Drupal::entityQuery('taxonomy_term');
          $query->condition('vid', $filter['vid']);
          $tids = $query->execute();
          $terms = Term::loadMultiple($tids);
          foreach ($terms as $term) {
            $responseService->addEntity($term);
            $filter['options'][] = $term->uuid();
          }
        }
        $filters[] = $filter;
      }
    }

    $response['display']['filters'] = $filters;

    $response['results'] = $results;

    return $response;
  }

}
