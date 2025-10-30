<?php

namespace Drupal\stitchlyn_vendor\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\node\Entity\Node;

/**
 * Returns item data (rate, sku, etc.) for AJAX fetch.
 */
class ItemInfoController extends ControllerBase {

  /**
   * AJAX endpoint to fetch item info by node ID.
   */
  public function itemInfo() {
    $nid = \Drupal::request()->query->get('nid');
    $data = ['rate' => 0];

    if ($nid && $node = Node::load($nid)) {
      if ($node->bundle() === 'inventory_item') {
        $data['rate'] = (float) ($node->get('field_cost_price')->value ?? 0);
        $data['sku'] = $node->get('field_sku_code')->value ?? '';
        $data['unit'] = $node->get('field_unit_of_measure')->entity->label() ?? '';
      }
    }

    return new JsonResponse($data);
  }

}