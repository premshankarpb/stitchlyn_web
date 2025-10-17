<?php

namespace Drupal\stitchlyn_vendor\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\node\Entity\Node;
use Drupal\Core\Database\Database;

class PurchaseOrderController extends ControllerBase {

  /**
   * Creates a new Purchase Order node and redirects to the edit form.
   */
  public function createPurchaseOrder() {
    // Step 1: Generate the next serial number for field_po_number.
    // You can replace this logic with your own sequence table if needed.
    $connection = Database::getConnection();
    $query = $connection->select('node_field_data', 'n');
    $query->leftJoin('node__field_po_number', 'p', 'n.nid = p.entity_id');
    $query->addExpression('MAX(p.field_po_number_value)', 'max_po');
    $result = $query->execute()->fetchField();
    $next_serial = ((int) $result) + 1;

    // Step 2: Create the node with the generated serial and proper title.
    $node = Node::create([
      'type' => 'purchase_order',
      'title' => 'Purchase-Order_' . $next_serial,
      'field_po_number' => $next_serial,
      'status' => 1,
    ]);
    $node->save();

    // Step 3: Redirect to the edit form.
    return new RedirectResponse('/dashboard/purchase-order/' . $node->id() . '/edit');
  }

}