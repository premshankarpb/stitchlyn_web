<?php

namespace Drupal\stitchlyn_quotation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles Inventory AJAX and save operations.
 */
class InventoryController extends ControllerBase {

  /**
   * Autocomplete for inventory items (case-insensitive).
   */
  public function autocomplete(Request $request) {
    $search = trim($request->query->get('q'));
    $results = [];

    if (strlen($search) > 1) {
      $storage = \Drupal::entityTypeManager()->getStorage('node');
      $query = $storage->getQuery()
        ->condition('type', 'inventory_item')
        ->condition('title', $search, 'CONTAINS')
        ->accessCheck(FALSE)
        ->range(0, 10);
      $nids = $query->execute();

      if (!empty($nids)) {
        $nodes = $storage->loadMultiple($nids);
        foreach ($nodes as $node) {
          $results[] = [
            'value' => $node->label(),
            'label' => $node->label(),
          ];
        }
      }
    }

    return new JsonResponse($results);
  }

  /**
   * Fetch item details by title (for detail preview).
   */
  public function getItemDetails(Request $request) {
    $title = trim($request->query->get('title'));
    if ($title === '') {
      return new JsonResponse(['status' => 'error', 'message' => 'No title provided.']);
    }

    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $query = $storage->getQuery()
      ->condition('type', 'inventory_item')
      ->condition('title', $title, 'CONTAINS')
      ->accessCheck(FALSE)
      ->range(0, 1);
    $nids = $query->execute();

    if (empty($nids)) {
      return new JsonResponse(['status' => 'error', 'message' => 'Item not found']);
    }

    $node = $storage->load(reset($nids));

    $data = [
      'name' => $node->label(),
      'cost' => $node->get('field_cost_price')->value ?? 0,
      'unit' => !$node->get('field_unit_of_measure')->isEmpty()
        ? $node->get('field_unit_of_measure')->entity->label()
        : 'N/A',
      'stock' => $node->get('field_opening_stock')->value ?? 0,
    ];

    return new JsonResponse(['status' => 'success', 'data' => $data]);
  }


  /**
   * Save a new Inventory Transaction Log linked to Quotation (AJAX dynamic).
   */
  public function saveLog($quotation, Request $request) {
    try {
      $data = json_decode($request->getContent(), TRUE);
      $title = trim($data['item'] ?? '');
      $qty = (float) ($data['quantity'] ?? 0);

      if ($title === '' || $qty <= 0) {
        return new JsonResponse(['status' => 'error', 'message' => 'Invalid input data.']);
      }

      // Load Inventory Item by title
      $items = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->loadByProperties([
          'type' => 'inventory_item',
          'title' => $title,
        ]);
      $item = reset($items);

      if (!$item) {
        return new JsonResponse(['status' => 'error', 'message' => 'Item not found.']);
      }

      // --- Fetch Taxonomy terms ---
      $order_type_tid = NULL;
      $transaction_type_tid = NULL;

      $order_terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
        ->loadByProperties(['vid' => 'order_type', 'name' => 'Quotation']);
      if ($order_terms) {
        $order_type_tid = reset($order_terms)->id();
      }

      $txn_terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
        ->loadByProperties(['vid' => 'transaction_type', 'name' => 'In']);
      if ($txn_terms) {
        $transaction_type_tid = reset($txn_terms)->id();
      }

      // --- Create the Inventory Log Node ---
      $log = \Drupal::entityTypeManager()->getStorage('node')->create([
        'type' => 'inventory_transaction_log',
        'title' => 'Inventory Log - ' . $item->label(),
        'field_inventory_item' => ['target_id' => $item->id()],
        'field_purchase_order' => ['target_id' => $quotation],
        'field_order_type' => ['target_id' => $order_type_tid],
        'field_transaction_type' => ['target_id' => $transaction_type_tid],
        'field_quantity' => $qty,
        'status' => 1,
      ]);
      $log->save();

      // ✅ Update inventory item stock and create new revision.
      if ($item->hasField('field_opening_stock')) {
        $current_stock = (float) ($item->get('field_opening_stock')->value ?? 0);
        $new_stock = max(0, $current_stock - $qty); // avoid negative stock
        $item->set('field_opening_stock', $new_stock);

        // Force a new revision for audit
        $item->setNewRevision(TRUE);
        $item->setRevisionLogMessage('Stock decreased by ' . $qty . ' due to Quotation #' . $quotation);
        $item->save();
      }


      // --- Calculate row values ---
      $cost = (float) ($item->get('field_cost_price')->value ?? 0);
      $total = $cost * $qty;

      // --- Return JSON with HTML row ---
      $row_html = '
        <tr data-id="' . $log->id() . '">
          <td>' . $item->label() . '</td>
          <td>' . ($item->get('field_opening_stock')->value ?? '') . '</td>
          <td>' . ($item->get('field_unit_of_measure')->entity->label() ?? '') . '</td>
          <td>₹' . number_format($cost, 2) . '</td>
          <td>' . $qty . '</td>
          <td>₹' . number_format($total, 2) . '</td>
          <td><button class="btn btn-outline-danger btn-sm remove-log" data-id="' . $log->id() . '">Remove</button></td>
        </tr>
      ';

      return new JsonResponse([
        'status' => 'success',
        'message' => 'Inventory log added successfully.',
        'html' => $row_html,
        'total' => $total,
      ]);
    }
    catch (\Exception $e) {
      \Drupal::logger('stitchlyn_quotation')->error($e->getMessage());
      return new JsonResponse([
        'status' => 'error',
        'message' => 'Exception: ' . $e->getMessage(),
      ]);
    }
  }

  /**
   * Delete inventory log entry and restore stock.
   */
  public function deleteLog($id) {
    try {
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($id);
      if ($node && $node->bundle() === 'inventory_transaction_log') {

        // ✅ Restore stock to inventory item before deletion
        if ($node->hasField('field_inventory_item') && !$node->get('field_inventory_item')->isEmpty()) {
          $item = $node->get('field_inventory_item')->entity;
          if ($item && $item->hasField('field_opening_stock')) {
            $qty = (float) ($node->get('field_quantity')->value ?? 0);
            $current_stock = (float) ($item->get('field_opening_stock')->value ?? 0);
            $item->set('field_opening_stock', $current_stock + $qty);
            $item->setNewRevision(TRUE);
            $item->setRevisionLogMessage('Stock restored by ' . $qty . ' after log deletion (Log ID ' . $id . ')');
            $item->save();
          }
        }

        // ✅ Delete the log node
        $node->delete();

        return new JsonResponse([
          'status' => 'success',
          'message' => 'Inventory log deleted successfully and stock restored.',
          'id' => $id,
        ]);
      }

      return new JsonResponse([
        'status' => 'error',
        'message' => 'Inventory log not found or invalid ID.',
      ]);
    }
    catch (\Exception $e) {
      \Drupal::logger('stitchlyn_quotation')->error($e->getMessage());
      return new JsonResponse([
        'status' => 'error',
        'message' => 'Exception: ' . $e->getMessage(),
      ]);
    }
  }

}