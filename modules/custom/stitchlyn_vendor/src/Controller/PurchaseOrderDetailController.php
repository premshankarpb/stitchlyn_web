<?php

namespace Drupal\stitchlyn_vendor\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\node\Entity\Node;

class PurchaseOrderDetailController extends ControllerBase {

  public function view(NodeInterface $node) {
    // Check that this is a purchase_order node
    if ($node->bundle() !== 'purchase_order') {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    // Fetch related Purchase Order Items via entity reference
    $po_items = [];
    if ($node->hasField('field_po_items')) {
      foreach ($node->get('field_po_items')->referencedEntities() as $item_node) {
        if ($item_node->bundle() === 'purchase_order_items') {
          $po_items[] = $item_node;
        }
      }
    }

    return [
      '#theme' => 'stitchlyn_po_detail',
      '#node' => $node,
      '#items' => $po_items,
      '#title' => $node->label(),
    ];
  }
}
