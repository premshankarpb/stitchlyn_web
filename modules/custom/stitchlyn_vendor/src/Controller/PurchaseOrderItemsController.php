<?php

namespace Drupal\stitchlyn_vendor\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Render\Markup;
use Drupal\Component\Utility\Html;

/**
 * Controller for handling Purchase Order Items AJAX.
 */
class PurchaseOrderItemsController extends ControllerBase {

  /**
   * Load all items for a purchase order.
   */
  public function itemsTable($nid) {
    $po = Node::load($nid);

    if (!$po || $po->bundle() !== 'purchase_order') {
      return new JsonResponse([
        'status' => 'error',
        'message' => 'Invalid Purchase Order.',
      ], 400);
    }

    $query = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', 'purchase_order_items')
      ->condition('field_purchase_order', $nid);
    $item_ids = $query->execute();

    $rows = '';
    $subtotal = 0;

    if (!empty($item_ids)) {
      $items = Node::loadMultiple($item_ids);
      foreach ($items as $item) {
        $rate = (float) $item->get('field_item_rate')->value ?? 0;
        $qty = (float) $item->get('field_quantity')->value ?? 0;
        $total = (float) $item->get('field_total_amount')->value ?? ($rate * $qty);
        $subtotal += $total;
        $remarks = Html::escape($item->get('body')->value ?? '-');

        $rows .= '<tr>'
          . '<td>' . Markup::create($item->label()) . '</td>'
          . '<td>' . number_format($rate, 2) . '</td>'
          . '<td>' . number_format($qty, 2) . '</td>'
          . '<td>' . number_format($total, 2) . '</td>'
          // . '<td>' . $remarks . '</td>'
          . '<td>'
          . '<button type="button" class="btn btn-outline-info btn-sm po-item-view" data-id="' . $item->id() . '">'
          . '<i class="bi bi-eye"></i> View</button> '
          . '<button type="button" class="btn btn-outline-secondary btn-sm po-item-edit" data-id="' . $item->id() . '">'
          . '<i class="bi bi-pencil"></i> Edit</button> '
          . '<button type="button" class="btn btn-outline-danger btn-sm po-item-remove" data-id="' . $item->id() . '">'
          . '<i class="bi bi-trash"></i> Remove</button>'
          . '</td>'
          . '</tr>';
      }
    }
    else {
      $rows = '<tr><td colspan="5"><em>No items added yet.</em></td></tr>';
    }

    $html = '
      <table class="table table-bordered table-striped align-middle">
        <thead class="table-light">
          <tr>
            <th>Item</th>
            <th>Rate</th>
            <th>Quantity</th>
            <th>Total</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>' . $rows . '</tbody>
      </table>
    ';

    return new JsonResponse([
      'status' => 'success',
      'html' => $html,
      'summary' => [
        'subtotal' => $subtotal,
        'tax' => (float) ($po->get('field_tax_amount')->value ?? 0),
        'total' => $subtotal + (float) ($po->get('field_tax_amount')->value ?? 0),
      ],
    ]);
  }

  /**
   * Add a new item.
   */
  public function addItem(Request $request, $po) {
    $po_nid = (int) $po;

    $inventory_nid = (int) $request->request->get('inventory_nid');
    $rate = (float) $request->request->get('rate');
    $quantity = (float) $request->request->get('quantity');
    $remarks = $request->request->get('remarks');

    // Load inventory item to get title.
    $inventory_node = \Drupal\node\Entity\Node::load($inventory_nid);
    if (!$inventory_node) {
      return new JsonResponse(['status' => 'error', 'message' => 'Invalid inventory item.']);
    }

    $item_title = $inventory_node->label();
    $total = $rate * $quantity;

    // Check if this inventory item already exists under this PO.
    $existing = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', 'purchase_order_items')
      ->condition('field_purchase_order', $po_nid)
      ->condition('field_item_reference', $inventory_nid)
      ->execute();

    if ($existing) {
      // Update existing PO item.
      $nid = reset($existing);
      $node = \Drupal\node\Entity\Node::load($nid);
    } else {
      // Create new PO item.
      $node = \Drupal\node\Entity\Node::create([
        'type' => 'purchase_order_items',
        'field_purchase_order' => $po_nid,
        'field_item_reference' => $inventory_nid,
      ]);
    }

    // Update fields.
    $node->setTitle($item_title);
    $node->set('field_item_rate', $rate);
    $node->set('field_quantity', $quantity);
    $node->set('field_total_amount', $total);
    $node->set('body', ['value' => $remarks, 'format' => 'basic_html']);
    $node->save();

    // Recompute summary.
    $summary = $this->computeSummary($po_nid);
    $html = $this->buildItemsTable($po_nid);

    return new JsonResponse([
      'status' => 'success',
      'message' => 'Item saved successfully.',
      'html' => $html,
      'summary' => [
        'subtotal' => number_format($summary['subtotal'], 2, '.', ''),
        'tax' => number_format($summary['tax_amount'], 2, '.', ''),
        'total' => number_format($summary['total'], 2, '.', ''),
      ],
    ]);
  }


  /**
   * Build the items table HTML fragment for the PO.
   */
  protected function buildItemsTable($po_nid) {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $query = $storage->getQuery()
      ->condition('type', 'purchase_order_items')
      ->condition('field_purchase_order', $po_nid)
      ->accessCheck(FALSE);
    $nids = $query->execute();

    if (empty($nids)) {
      return '<div class="text-center text-muted py-3"><em>No items added yet. Click “Add Item” to begin.</em></div>';
    }

    $nodes = $storage->loadMultiple($nids);

    $rows = '';
    foreach ($nodes as $node) {
      $id = $node->id();
      $title = Html::escape($node->label());
      $rate = number_format((float) $node->get('field_item_rate')->value, 2, '.', ',');
      $qty = number_format((float) $node->get('field_quantity')->value, 2, '.', ',');
      $total = number_format((float) $node->get('field_total_amount')->value, 2, '.', ',');
      $remarks = Html::escape($node->get('body')->value ?? '-');

      $rows .= '<tr>'
        . '<td>' . $title . '</td>'
        . '<td>' . $rate . '</td>'
        . '<td>' . $qty . '</td>'
        . '<td>' . $total . '</td>'
        // . '<td>' . $remarks . '</td>'
        . '<td class="text-nowrap">'
        . '<button type="button" class="btn btn-outline-info btn-sm po-item-view" data-id="' . $id . '">'
        . '<i class="bi bi-eye"></i> View</button> '
        . '<button type="button" class="btn btn-outline-secondary btn-sm po-item-edit" data-id="' . $id . '">'
        . '<i class="bi bi-pencil"></i> Edit</button> '
        . '<button type="button" class="btn btn-outline-danger btn-sm po-item-remove" data-id="' . $id . '">'
        . '<i class="bi bi-trash"></i> Remove</button>'
        . '</td>'
        . '</tr>';
    }

    $table = '
      <table class="table table-bordered table-striped align-middle">
        <thead class="table-light">
          <tr>
            <th>Item</th>
            <th>Rate (₹)</th>
            <th>Quantity</th>
            <th>Total (₹)</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>' . $rows . '</tbody>
      </table>
    ';

    return Markup::create($table);
  }

  /**
   * Sum totals for summary.
   * If you have tax logic, plug it here.
   */
  protected function computeSummary(int $po_nid): array {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $nids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'purchase_order_items')
      ->condition('field_purchase_order', $po_nid)
      ->execute();

    $subtotal = 0.0;
    if ($nids) {
      foreach ($storage->loadMultiple($nids) as $node) {
        $subtotal += (float) $node->get('field_total_amount')->value;
      }
    }
    $config = \Drupal::config('stitchlyn_basic.erp_settings');
    $tax_percent = (float) ($config->get('tax_percentage') ?? 18);

    $tax_amount = ($subtotal * $tax_percent) / 100;
    $total_after_tax = $subtotal + $tax_amount;

    // Optional: Round properly
    $subtotal = round($subtotal, 2);
    $tax_amount = round($tax_amount, 2);
    $total_after_tax = round($total_after_tax, 2);

    // ✅ Return expanded array
    return [
      'subtotal' => $subtotal,
      'tax_percent' => $tax_percent,
      'tax_amount' => $tax_amount,
      'total_after_tax' => $total_after_tax,
    ];
  }

  /**
   * Update existing item.
   */
  public function updateItem($nid, $item_id, Request $request) {
    $po = Node::load($nid);
    $item = Node::load($item_id);

    if (!$po || $po->bundle() !== 'purchase_order' || !$item || $item->bundle() !== 'purchase_order_items') {
      return new JsonResponse(['status' => 'error', 'message' => 'Invalid request.'], 400);
    }

    $rate = (float) $request->request->get('rate');
    $qty = (float) $request->request->get('quantity');
    $remarks = $request->request->get('remarks');
    $total = $rate * $qty;

    $item->set('field_item_rate', $rate);
    $item->set('field_quantity', $qty);
    $item->set('field_total_amount', $total);
    $item->set('body', ['value' => $remarks, 'format' => 'basic_html']);
    $item->save();

    return $this->itemsTable($nid);
  }

  /**
   * Delete item.
   */
  public function deleteItem($nid, $item_id) {
    $po = Node::load($nid);
    $item = Node::load($item_id);

    if ($po && $item && $po->bundle() === 'purchase_order' && $item->bundle() === 'purchase_order_items') {
      $item->delete();
    }

    return $this->itemsTable($nid);
  }

  /**
   * Returns JSON details for a single Purchase Order item.
   */
  public function getItemJson(NodeInterface $po, $item_id) {
    $node = Node::load($item_id);

    if ($node && $node->bundle() === 'purchase_order_items') {
      $data = [
        'id' => $node->id(),
        'title' => $node->label(),
        'rate' => (float) $node->get('field_item_rate')->value,
        'quantity' => (float) $node->get('field_quantity')->value,
        'total' => (float) $node->get('field_total_amount')->value,
        'remarks' => $node->get('body')->value ?? '',
      ];

      return new JsonResponse($data);
    }

    return new JsonResponse(['error' => 'Item not found'], 404);
  }

}