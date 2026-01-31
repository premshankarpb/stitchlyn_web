<?php

namespace Drupal\stitchlyn_basic\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Datetime\DrupalDateTime;


/**
 * Provides the Dashboard page controller.
 */
class DashboardController extends ControllerBase {

  /**
   * Dashboard page callback.
   */
  public function view() {

    // --- PURCHASE ORDERS ---
    $purchase_orders = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => 'purchase_order']);
    $total_po_amount = 0;
    $collected_po_amount = 0;
    $pending_po_amount = 0;

    foreach ($purchase_orders as $po) {
      $total_po_amount += (float) $po->get('field_total_amount')->value ?? 0;
      $collected_po_amount += (float) $po->get('field_amount_collected')->value ?? 0;
      $pending_po_amount += (float) $po->get('field_amount_pending')->value ?? 0;
    }

    // --- QUOTATIONS ---
    $quotations = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => 'quotation']);
    $total_qt_amount = 0;
    $collected_qt_amount = 0;
    $pending_qt_amount = 0;

    foreach ($quotations as $qt) {
      $total_qt_amount += (float) $qt->get('field_total_amount')->value ?? 0;
      $collected_qt_amount += (float) $qt->get('field_amount_collected')->value ?? 0;
      $pending_qt_amount += (float) $qt->get('field_amount_pending')->value ?? 0;
    }


    $counts = [
      'product' => $this->getInventoryCount('Finished Product'),
      'raw_material' => $this->getInventoryCount('Raw Material'),
      'tool' => $this->getInventoryCount('Tool'),

      'quotations' => [
        'total' => $this->getNodeCount('quotation'),
        'draft' => $this->getNodeCountByStatus('quotation', 0, 'draft'), // Unpublished = draft
        'approved' => $this->getNodeCountByStatus('quotation', 0, 'accepted'), // Unpublished = approved
        'in_progress' => $this->getNodeCountByStatus('quotation', 0, 'in_progress'), // Unpublished = in_progress
        'follow_up' => $this->getNodeCountByStatus('quotation', 0, 'to_update'), // Unpublished = follow_up

      ],

      'work_orders' => [
        'total' => $this->getNodeCount('work_order'),
        'done' => $this->getNodeCountByTaxonomy('work_order', 'field_order_status', 'Done'),
        'in_progress_due' => $this->getNodesWithDPlusOrMinus3('minus'),
        'in_progress' => $this->getNodesWithDPlusOrMinus3('plus'),
        'to_do' => $this->getNodeCountByTaxonomy('work_order', 'field_order_status', 'To Do'),
        'rejected' => $this->getNodeCountByTaxonomy('work_order', 'field_order_status', 'Rejected'),
      ],

      'purchase_orders' => [
        'total' => $this->getNodeCount('purchase_order'),
        'paid' => $this->getNodeCountByTaxonomy('purchase_order', 'field_payment_status', 'Paid'),
        'issued' => $this->getNodeCountByTaxonomy('purchase_order', 'field_purchase_order_status', 'Issued'),
        'ship_in_progress' => $this->getNodeCountByTaxonomy('purchase_order', 'field_purchase_order_status', 'Shipment In Progress'),
        'fullfilled' => $this->getNodeCountByTaxonomy('purchase_order', 'field_purchase_order_status', 'Fulfilled'),
      ],
    ];

     // Add to counts array
    $counts['purchase_orders']['total_amount'] = $total_po_amount;
    $counts['purchase_orders']['collected'] = $collected_po_amount;
    $counts['purchase_orders']['pending'] = $pending_po_amount;

    $counts['quotations']['total_amount'] = $total_qt_amount;
    $counts['quotations']['collected'] = $collected_qt_amount;
    $counts['quotations']['pending'] = $pending_qt_amount;

    \Drupal::logger('counts')->warning('<pre><code>' . print_r($counts, TRUE) . '</code></pre>');

    return [
      '#theme' => 'stitchlyn_dashboard',
      '#counts' => $counts,
      '#attached' => [
        'library' => ['stitchlyn_basic/dashboard'],
      ],
    ];
  }

  /**
   * Manager dashboard page callback.
   */
  public function manager_view() {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $today = new DrupalDateTime('today');
    $plus3 = new DrupalDateTime('+3 days');

    // Load all work orders (manager dashboard = overview)
    $work_orders = $storage->loadByProperties([
      'type' => 'work_order',
    ]);

    // ---- COUNTERS ----
    $pending = 0;
    $due_3_days = 0;
    $overdue = 0;

    // Monthly production (quantity based)
    $monthly = [
      'completed' => 0,
      'in_progress' => 0,
      'to_do' => 0,
      'total' => 0,
    ];

    $current_month = $today->format('Y-m');

    foreach ($work_orders as $wo) {
      if ($wo->isPublished() === FALSE) {
        continue;
      }

      $status = $wo->get('field_order_status')->entity?->label();
      $qty = (float) ($wo->get('field_quantity')->value ?? 0);
      $due_date_raw = $wo->get('field_expected_due_date')->value;

      $due_date = $due_date_raw ? new DrupalDateTime($due_date_raw) : NULL;

      // ---- Pending ----
      if (in_array($status, ['In Progress', 'To Do'], TRUE)) {
        $pending++;
      }

      // ---- Due logic ----
      if ($due_date && $status !== 'Done') {
        if ($due_date < $today) {
          $overdue++;
        }
        elseif ($due_date <= $plus3) {
          $due_3_days++;
        }
      }

      // ---- Monthly production ----
      $created_date = DrupalDateTime::createFromTimestamp($wo->getCreatedTime());
      if ($created_date->format('Y-m') === $current_month) {
        $monthly['total'] += $qty;
        switch ($status) {
          case 'Done':
            $monthly['completed'] += $qty;
            break;

          case 'In Progress':
            $monthly['in_progress'] += $qty;
            break;

          case 'To Do':
            $monthly['to_do'] += $qty;
            break;
        }
      }

    }

    $percent = [
      'completed' => 0,
      'in_progress' => 0,
      'to_do' => 0,
    ];

    if ($monthly['total'] > 0) {
      $percent['completed'] = round(($monthly['completed'] / $monthly['total']) * 100);
      $percent['in_progress'] = round(($monthly['in_progress'] / $monthly['total']) * 100);
      $percent['to_do'] = round(($monthly['to_do'] / $monthly['total']) * 100);
    }

    $counts = [
      'pending' => $pending,
      'due_3_days' => $due_3_days,
      'overdue' => $overdue,

      'monthly_production' => [
        'completed' => $monthly['completed'],
        'in_progress' => $monthly['in_progress'],
        'to_do' => $monthly['to_do'],
        'percent' => $percent,
      ],
    ];

    return [
      '#theme' => 'stitchlyn_manager_dashboard',
      '#counts' => $counts,
      '#attached' => [
        'library' => ['stitchlyn_basic/dashboard'],
      ],
    ];
  }

  /**
   * Count nodes by type.
   */
  protected function getNodeCount($type) {
    $query = \Drupal::entityQuery('node')
      ->condition('type', $type)
      ->condition('status', 1)
      ->accessCheck(FALSE);
    return $query->count()->execute();
  }

  protected function getNodeCountByStatus($type, $status, $mod) {

    $cms_query = \Drupal::entityQuery('content_moderation_state')
      ->condition('content_entity_type_id', 'node')
      ->condition('moderation_state', $mod)
      ->accessCheck(FALSE);

    $cms_ids = $cms_query->execute();

    if (empty($cms_ids)) {
      $cms_ids = [0]; // invalid nid → returns zero nodes
    }

    return $cms_query->count()->execute();
  }

  /**
   * Count inventory items by taxonomy term (Inventory Type).
   */
  protected function getInventoryCount($inventory_type_name) {
    $term = $this->getTermByName($inventory_type_name, 'inventory_type');
    if ($term) {
      $query = \Drupal::entityQuery('node')
        ->condition('type', 'inventory_item')
        ->condition('status', 1)
        ->condition('field_inventory_type', $term->id())
        ->accessCheck(FALSE);
      return $query->count()->execute();
    }
    return 0;
  }

  /**
   * Count nodes by taxonomy field and term name.
   */
  protected function getNodeCountByTaxonomy($content_type, $field_name, $term_name) {
    $term = $this->getTermByName($term_name);
    if ($term) {
      $query = \Drupal::entityQuery('node')
        ->condition('type', $content_type)
        ->condition('status', 1)
        ->condition($field_name, $term->id())
        ->accessCheck(FALSE);
      return $query->count()->execute();
    }
    return 0;
  }


function getNodesWithDPlusOrMinus3($sign) {

  if($sign == 'plus'){
    $the_sign = '>=';
  }
  if($sign == 'minus'){
    $the_sign = '<';
  }
  // Compute "now + 3 days"
  $threshold = new DrupalDateTime('now');
  $threshold->modify('+3 days');

  // Normalize time to start of day
  $threshold->setTime(0, 0, 0);

  // Convert to storage format (adjust depending on field type)
  //$threshold_storage = $threshold->format('Y-m-d\TH:i:s'); // For datetime
  $threshold_storage = $threshold->format('Y-m-d'); // For date-only field

  // Query nodes
  $query = \Drupal::entityQuery('node')
    ->condition('type', 'work_order')
    ->condition('field_expected_due_date', $threshold_storage, $the_sign)
    ->accessCheck(FALSE);

  $nids = $query->count()->execute();
  return $nids;

}


  /**
   * Count nodes by workflow moderation state.
   */
  protected function getNodeCountByModeration($content_type, $state) {
    $fields = \Drupal::service('entity_field.manager')->getBaseFieldDefinitions('node');
    if (!isset($fields['moderation_state'])) {
      return 0; // Safely skip if field missing
    }
    $query = \Drupal::entityQuery('node')
      ->condition('type', $content_type)
      ->condition('status', 1)
      ->condition('moderation_state', $state)
      ->accessCheck(FALSE);
    return $query->count()->execute();
  }

  /**
   * Load taxonomy term by name and vocabulary.
   */
  protected function getTermByName($name, $vocabulary = NULL) {
    $query = \Drupal::entityQuery('taxonomy_term')
      ->condition('name', $name)
      ->accessCheck(FALSE);
    if ($vocabulary) {
      $query->condition('vid', $vocabulary);
    }
    $tids = $query->execute();
    if (!empty($tids)) {
      return Term::load(reset($tids));
    }
    return NULL;
  }

}