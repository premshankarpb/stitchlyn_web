<?php

namespace Drupal\stitchlyn_vendor\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\node\Entity\Node;

class PurchaseOrderController extends ControllerBase {

  /**
   * Creates a new Purchase Order node and redirects to the edit form.
   */
  public function createPurchaseOrder() {
    // Create a new purchase order node.
    $node = Node::create([
      'type' => 'purchase_order',
      'title' => 'Purchase Order ' . date('YmdHis'),
      'status' => 1,
    ]);
    $node->save();

    // Redirect to the edit form.
    return new RedirectResponse('/dashboard/purchase-order/' . $node->id() . '/edit');
  }

}
