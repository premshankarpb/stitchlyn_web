<?php

namespace Drupal\stitchlyn_vendor\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\node\Entity\Node;

/**
 * Handles inventory autocomplete.
 */
class InventoryController extends ControllerBase {

  /**
   * Autocomplete callback.
   */
  public function autocomplete(Request $request) {
    $results = [];
    $string = $request->query->get('q');
    
    if ($string) {
      $query = \Drupal::entityQuery('node')
        ->accessCheck(FALSE)
        ->condition('type', 'inventory_item')
        ->condition('title', $string, 'CONTAINS')
        ->range(0, 10);
      $nids = $query->execute();

      if (!empty($nids)) {
        $nodes = Node::loadMultiple($nids);
        foreach ($nodes as $node) {
          $cost = $node->get('field_cost_price')->value ?? 0;
          $results[] = [
            'value' => $node->label() . ' (₹' . number_format($cost, 2) . ')',
            'label' => $node->label(),
            'nid' => $node->id(),
            'rate' => $cost,
          ];
        }
      }
    }

    return new JsonResponse($results);
  }
}