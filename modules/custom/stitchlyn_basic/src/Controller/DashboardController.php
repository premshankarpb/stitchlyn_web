<?php

namespace Drupal\stitchlyn_basic\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\taxonomy\Entity\Term;

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

    // Add to counts array
    $counts['purchase_orders']['total_amount'] = $total_po_amount;
    $counts['purchase_orders']['collected'] = $collected_po_amount;
    $counts['purchase_orders']['pending'] = $pending_po_amount;

    $counts['quotations']['total_amount'] = $total_qt_amount;
    $counts['quotations']['collected'] = $collected_qt_amount;
    $counts['quotations']['pending'] = $pending_qt_amount;


    $counts = [
      'product' => $this->getInventoryCount('Finished Product'),
      'raw_material' => $this->getInventoryCount('Raw Material'),
      'tool' => $this->getInventoryCount('Tool'),

      'quotations' => [
        'total' => $this->getNodeCount('quotation'),
        'fulfilled' => $this->getNodeCountByStatus('quotation', 1), // Published = Fulfilled
        'not_completed' => $this->getNodeCountByStatus('quotation', 0), // Unpublished = Not Completed
      ],

      'work_orders' => [
        'total' => $this->getNodeCount('work_order'),
        'done' => $this->getNodeCountByTaxonomy('work_order', 'field_order_status', 'Done'),
        'in_progress' => $this->getNodeCountByTaxonomy('work_order', 'field_order_status', 'In Progress'),
        'to_do' => $this->getNodeCountByTaxonomy('work_order', 'field_order_status', 'To Do'),
        'rejected' => $this->getNodeCountByTaxonomy('work_order', 'field_order_status', 'Rejected'),
      ],

      'purchase_orders' => [
        'total' => $this->getNodeCount('purchase_order'),
        'paid' => $this->getNodeCountByTaxonomy('purchase_order', 'field_payment_status', 'Paid'),
        'partial' => $this->getNodeCountByTaxonomy('purchase_order', 'field_payment_status', 'Partial'),
        'unpaid' => $this->getNodeCountByTaxonomy('purchase_order', 'field_payment_status', 'Unpaid'),
      ],
    ];

    return [
      '#theme' => 'stitchlyn_dashboard',
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

  protected function getNodeCountByStatus($type, $status = 1) {
    $query = \Drupal::entityQuery('node')
      ->condition('type', $type)
      ->condition('status', $status)
      ->accessCheck(FALSE);
    return $query->count()->execute();
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