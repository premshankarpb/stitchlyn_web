<?php

namespace Drupal\stitchlyn_quotation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles Work Order creation, autocomplete, and deletion.
 */
class WorkOrderController extends ControllerBase {

  /**
   * Autocomplete for Quotation Line Items (case-insensitive).
   */
  public function lineItemAutocomplete($quotation, Request $request) {
    $search = trim($request->query->get('q'));
    $results = [];

    if (strlen($search) > 1 && !empty($quotation)) {
      $query = \Drupal::entityQuery('node')
        ->condition('type', 'quatation_line_items') // ✅ also fix the typo here
        ->condition('title', $search, 'CONTAINS')
        ->condition('field_linked_quotation', $quotation)
        ->accessCheck(FALSE)
        ->range(0, 10);
      $nids = $query->execute();

      if (!empty($nids)) {
        $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);
        foreach ($nodes as $node) {
          $results[] = [
            'label' => $node->label(),
            'value' => $node->id(),
          ];
        }
      }
    }

    return new JsonResponse($results);
  }

  /**
   * Autocomplete for Production Units taxonomy.
   */
  public function productionUnitAutocomplete(Request $request) {
    $search = trim($request->query->get('q'));
    $results = [];

    if (strlen($search) > 1) {
      $terms = \Drupal::entityTypeManager()
        ->getStorage('taxonomy_term')
        ->loadByProperties(['vid' => 'production_units']);

      foreach ($terms as $term) {
        if (stripos($term->label(), $search) !== FALSE) {
          $results[] = [
            'value' => $term->id(),
            'label' => $term->label(),
          ];
        }
      }
    }

    return new JsonResponse($results);
  }

  /**
   * Autocomplete for Order Status terms (taxonomy: order_status).
   */
  public function orderStatusAutocomplete(Request $request) {
    $query = $request->query->get('q');
    $results = [];

    if (!$query) {
      return new JsonResponse($results);
    }

    $query = trim($query);
    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $terms = $storage->loadByProperties(['vid' => 'order_status']);
    foreach ($terms as $term) {
      if (stripos($term->label(), $query) !== FALSE) {
        $results[] = [
          'label' => $term->label(),
          'value' => $term->id(),
        ];
      }
    }

    return new JsonResponse($results);
  }

  /**
   * Save Work Order (with Expected Due Date and Order Status).
   */
  public function saveWorkOrder(Request $request) {

    $quotation = $request->get('quotation');
    $line_item_id = trim($request->get('line_item'));
    $unit_id = trim($request->get('unit'));
    $quantity = (float) trim($request->get('quantity'));
    $due_date = trim($request->get('due_date'));
    $status_id = trim($request->get('status'));
    $date_of_completion = trim($request->get('date_of_completion'));
    $assignee = trim($request->get('assignee'));
    $remarks = trim($request->get('remarks'));
    $assignee = trim($request->get('assignee'));
    $date_of_completion = trim($request->get('date_of_completion'));

    // --- Validation ---
    if (empty($quotation) || empty($line_item_id) || empty($unit_id) || empty($quantity) || empty($status_id)) {
        return new JsonResponse([
            'status' => 'error',
            'message' => 'Missing or invalid input. Ensure all fields are selected properly.',
        ]);
    }

    $em = \Drupal::entityTypeManager();

    // --- Load related entities ---
    $line_item = $em->getStorage('node')->load($line_item_id);
    $unit = $em->getStorage('taxonomy_term')->load($unit_id);
    $status_term = $em->getStorage('taxonomy_term')->load($status_id);
    $assignee_term = $em->getStorage('taxonomy_term')->load($assignee);

    if (!$line_item || !$unit || !$status_term) {
        return new JsonResponse([
            'status' => 'error',
            'message' => 'One or more related entities could not be loaded (line item, unit, or status).',
        ]);
    }

    // ===========================================================
    // VALIDATION — Ensure assigned qty does NOT exceed quotation qty
    // ===========================================================

    // Quotation item full quantity
    $quotation_qty = (float) ($line_item->get('field_quantity')->value ?? 0);

    // Sum quantities from all existing work orders
    $existing_ids = \Drupal::entityQuery('node')
        ->condition('type', 'work_order')
        ->condition('field_linked_quotation', $quotation)
        ->condition('field_linked_line_item', $line_item_id)
        ->accessCheck(FALSE)
        ->execute();

    $assigned_qty = 0;

    if (!empty($existing_ids)) {
        $existing_wos = $em->getStorage('node')->loadMultiple($existing_ids);
        foreach ($existing_wos as $wo) {
            $assigned_qty += (float) $wo->get('field_quantity')->value;
        }
    }

    // Remaining quantity available
    $remaining_qty = $quotation_qty - $assigned_qty;

    if ($quantity > $remaining_qty) {
        return new JsonResponse([
            'status' => 'error',
            'message' => "Maximum assignable quantity for this item is {$remaining_qty}.",
        ]);
    }

    // --- Format due date ---
    $formatted_due_date = NULL;
    if (!empty($due_date)) {
        try {
            $formatted_due_date = (new \DateTime($due_date))->format('Y-m-d');
        } catch (\Exception $e) {
            $formatted_due_date = NULL;
        }
    }

    // --- Format date of completion ---
    $formatted_date_of_completion = NULL;
    if (!empty($date_of_completion)) {
        try {
            $formatted_date_of_completion = (new \DateTime($date_of_completion))->format('Y-m-d');
        } catch (\Exception $e) {
            $formatted_date_of_completion = NULL;
        }
    }

    // --- Create the Work Order node ---
    $node_storage = $em->getStorage('node');
    $work_order = $node_storage->create([
        'type' => 'work_order',
        'title' => 'Temporary',
        'field_linked_quotation' => ['target_id' => $quotation],
        'field_linked_line_item' => ['target_id' => $line_item_id],
        'field_unit_assigned' => ['target_id' => $unit_id],
        'field_quantity' => $quantity,
        'field_order_status' => ['target_id' => $status_id],
        'field_expected_due_date' => $formatted_due_date ?: NULL,
        'field_date_of_completion' => $formatted_date_of_completion ?: NULL,
        'field_assignee' => ['target_id' => $assignee],
        'body' => ['value' => $remarks, 'format' => 'basic_html'],
        'status' => 1,
    ]);

    $work_order->save();

    // --- Update title with Serial Number ---
    if ($work_order->hasField('field_work_order_number')) {
        $serial = $work_order->get('field_work_order_number')->value ?? $work_order->id();
        $work_order->setTitle('Work Order - ' . $serial);
        $work_order->save();
    }

    // --- Rebuild Work Orders Table ---
    $work_orders_data = [];

    $nids = $node_storage->getQuery()
        ->condition('type', 'work_order')
        ->condition('field_linked_quotation', $quotation)
        ->sort('created', 'DESC')
        ->accessCheck(FALSE)
        ->execute();

    if (!empty($nids)) {
        $nodes = $node_storage->loadMultiple($nids);
        foreach ($nodes as $wo) {
            $work_orders_data[] = [
                'id' => $wo->id(),
                'title' => $wo->label(),
                'serial' => $wo->get('field_work_order_number')->value ?? '',
                'line_item' => $wo->get('field_linked_line_item')->entity->label() ?? '',
                'unit' => $wo->get('field_unit_assigned')->entity->label() ?? '',
                'quantity' => $wo->get('field_quantity')->value ?? '',
                'expected_due' => $wo->get('field_expected_due_date')->value ?? '',
                'date_of_completion' => $wo->get('field_date_of_completion')->value ?? '',
                'assignee' => $wo->get('field_assignee')->entity->label() ?? '',
                'order_status' => $wo->get('field_order_status')->entity->label() ?? '',
            ];
        }
    }

    // --- Generate updated table HTML ---
    $html = '<table class="table table-bordered table-striped align-middle" id="workorder-table">';
    $html .= '<thead class="table-light"><tr>';
    $html .= '<th>Work Order #</th><th>Line Item</th><th>Unit Assigned</th><th>Date of Completion</th><th>Assignee</th><th>Quantity</th><th>Due Date</th><th>Status</th><th>Actions</th>';
    $html .= '</tr></thead><tbody>';

    if (!empty($work_orders_data)) {
        foreach ($work_orders_data as $wo) {
            $html .= '<tr>';
            $html .= '<td>' . $wo['title'] . '</td>';
            $html .= '<td>' . $wo['line_item'] . '</td>';
            $html .= '<td>' . $wo['unit'] . '</td>';
            $html .= '<td>' . $wo['date_of_completion'] . '</td>';
            $html .= '<td>' . $wo['assignee'] . '</td>';
            $html .= '<td>' . $wo['quantity'] . '</td>';
            $html .= '<td>' . $wo['expected_due'] . '</td>';
            $html .= '<td>' . $wo['order_status'] . '</td>';
            $html .= '<td>
                <button class="btn btn-outline-primary btn-sm view-workorder" data-id="' . $wo['id'] . '">View</button>
                <button class="btn btn-outline-secondary btn-sm edit-workorder" data-id="' . $wo['id'] . '">Edit</button>
                <a href="/node/add/order_logs?workorder=' . $wo['id'] . '"  target="_blank" class="btn btn-outline-success">Add Logs</a>
            </td>';
            $html .= '</tr>';
        }
    } else {
        $html .= '<tr><td colspan="7" class="text-center text-muted">No work orders found.</td></tr>';
    }

    $html .= '</tbody></table>';

    // --- Return Response ---
    $response = new JsonResponse([
        'status' => 'success',
        'message' => 'Work order added successfully.',
        'html' => $html,
    ]);

    $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
    $response->headers->set('Pragma', 'no-cache');
    $response->headers->set('Expires', '0');

    return $response;
  }

  /**
   * Delete a Work Order entry.
   */
  public function deleteWorkOrder($id) {
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($id);
    if ($node && $node->bundle() === 'work_order') {
      $node->delete();
      return new JsonResponse(['status' => 'success', 'message' => 'Work order deleted.']);
    }
    return new JsonResponse(['status' => 'error', 'message' => 'Work order not found.']);
  }

  /**
   * AJAX callback to update work order status and remarks.
   */
  public function updateWorkOrder($id, Request $request) {
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($id);
    if (!$node || $node->bundle() !== 'work_order') {
      return new JsonResponse(['status' => 'error', 'message' => 'Work order not found.']);
    }

    $status = trim($request->request->get('status'));
    $remarks = trim($request->request->get('remarks'));
    $date_of_completion = trim($request->request->get('date_of_completion'));
    $assignee = trim($request->request->get('assignee'));

    // --- Update order status ---
    if ($status) {
      $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
        'name' => $status,
        'vid' => 'order_status',
      ]);
      if ($terms) {
        $term = reset($terms);
        $node->set('field_order_status', ['target_id' => $term->id()]);
      }
    }

    // --- Update date of completion ---
    if ($date_of_completion) {
      $node->set('field_date_of_completion', $date_of_completion);
    }

    // --- Update assignee ---
    if ($assignee) {
      $node->set('field_assignee', ['target_id' => $assignee]);
    }

    // --- Update remarks ---
    if ($remarks !== '') {
      $node->set('body', ['value' => $remarks, 'format' => 'basic_html']);
    }

    $node->save();

    // =========================================================
    // REBUILD FULL WORKORDER TABLE HTML
    // =========================================================

    $quotation_id = $node->get('field_linked_quotation')->target_id ?? NULL;
    if (!$quotation_id) {
      return new JsonResponse(['status' => 'success', 'html' => '', 'message' => 'Updated.']);
    }

    // Load ALL work orders under this quotation
    $workorder_ids = \Drupal::entityQuery('node')
      ->condition('type', 'work_order')
      ->condition('field_linked_quotation', $quotation_id)
      ->accessCheck(FALSE)
      ->execute();

    $workorders = \Drupal\node\Entity\Node::loadMultiple($workorder_ids);

    $rows_html = '';

    foreach ($workorders as $wo) {
      $rows_html .= '
        <tr>
          <td>' . $wo->label() . '</td>
          <td>' . ($wo->get('field_linked_line_item')->entity->label() ?? '') . '</td>
          <td>' . ($wo->get('field_unit_assigned')->entity->label() ?? '') . '</td>
          <td>' . $wo->get('field_date_of_completion')->value . '</td>
          <td>' . ($wo->get('field_assignee')->entity->label() ?? '') . '</td>
          <td>' . $wo->get('field_quantity')->value . '</td>
          <td>' . $wo->get('field_expected_due_date')->value . '</td>
          <td>' . $wo->get('field_order_status')->entity->label() . '</td>
          <td>
            <button class="btn btn-outline-primary btn-sm view-workorder" data-id="' . $wo->id() . '">View</button>
            <button class="btn btn-outline-secondary btn-sm edit-workorder" data-id="' . $wo->id() . '">Edit</button>
            <a href="/node/add/order_logs?workorder=' . $wo->id() . '"  target="_blank" class="btn btn-outline-success">Add Logs</a>
          </td>
        </tr>
      ';
    }

    // Wrap inside <table> if your JS expects full replacement
    $table_html = '
      <table class="table table-bordered table-striped align-middle" id="workorder-table">
        <thead class="table-light">
          <tr>
            <th>Work Order #</th>
            <th>Linked Line Item</th>
            <th>Unit Assigned</th>
            <th>Date of Completion</th>
            <th>Assignee</th>
            <th>Quantity</th>
            <th>Expected Due Date</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          ' . $rows_html . '
        </tbody>
      </table>
    ';

    return new JsonResponse([
      'status' => 'success',
      'html' => $table_html,
      'message' => 'Work order updated successfully.',
    ]);
  }

  /**
   * AJAX callback to fetch work order details.
   */
  public function getWorkOrder($id) {
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($id);
    if (!$node || $node->bundle() !== 'work_order') {
      return new JsonResponse(['status' => 'error', 'message' => 'Work order not found.']);
    }
    $work_order_logs = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'type' => 'order_logs',
        'field_order_reference' => $id,
      ]);
    $wo_log_data = [];
    foreach ($work_order_logs as $wo_log) {
      $log_data = $wo_log->get('body')->value ?? '';
      $wo_log_data[] = [
        'id' => $wo_log->id(),
        'log_data' => $log_data,
        'created' => $wo_log->getCreatedTime(),
      ];
    }

    $data = [
      'title' => $node->label(),
      'line_item' => $node->get('field_linked_line_item')->entity->label() ?? '',
      'unit' => $node->get('field_unit_assigned')->entity->label() ?? '',
      'quantity' => $node->get('field_quantity')->value ?? '',
      'expected_due_date' => $node->get('field_expected_due_date')->value ?? '',
      'date_of_completion' => $node->get('field_date_of_completion')->value ?? '',
      'assignee' => isset($node->get('field_assignee')->entity) ? $node->get('field_assignee')->entity->label() : '',
      'order_status' => $node->get('field_order_status')->entity->label() ?? '',
      'remarks' => $node->get('body')->value ?? '',
      'work_order_logs' => $wo_log_data,
    ];

    return new JsonResponse(['status' => 'success', 'data' => $data]);
  }


}