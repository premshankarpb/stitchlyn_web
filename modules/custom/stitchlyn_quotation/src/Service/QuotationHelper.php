<?php

namespace Drupal\stitchlyn_quotation\Service;

use Drupal\node\Entity\Node;

/**
 * Helper for rendering tables and computing totals.
 */
class QuotationHelper {

  /**
   * Render the line items table: Product | Quantity | Unit Price | Actions.
   */
  public function renderLineItemTable(int $quotation_id) {
    $header = ['Product', 'Quantity', 'Unit Price', 'Actions'];
    $rows = [];

    $nids = \Drupal::entityQuery('node')
      ->accessCheck(TRUE)
      ->condition('type', 'quatation_line_items')  // bundle name in your site
      ->condition('field_linked_quotation', $quotation_id)
      ->execute();

    if ($nids) {
      foreach (Node::loadMultiple($nids) as $item) {
        $product = $item->get('field_product')->entity;
        $qty = (float) ($item->get('field_quantity')->value ?? 0);
        $unit = $product ? (float) ($product->get('field_cost_price')->value ?? 0) : 0;

        // Action buttons handled by JS (view/delete).
        $actions_markup =
          '<button class="button button--small view-item" data-id="' . $item->id() . '">View</button> ' .
          '<button class="button button--small remove-item" data-id="' . $item->id() . '">Remove</button>';

        $rows[] = [
          $product ? $product->label() : '-',
          $qty,
          number_format($unit, 2),
          ['data' => ['#markup' => $actions_markup]],
        ];
      }
    }

    return [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => t('No items added yet.'),
      '#attributes' => ['class' => ['sq-items-table']],
    ];
  }

  /**
   * Compute Subtotal, Tax, Total from the current items + config.
   * Discount is stored on the quotation node.
   */
  public function computeTotals(int $quotation_id): array {
    $subtotal = 0.0;

    $nids = \Drupal::entityQuery('node')
      ->accessCheck(TRUE)
      ->condition('type', 'quatation_line_items')
      ->condition('field_linked_quotation', $quotation_id)
      ->execute();

    if ($nids) {
      foreach (Node::loadMultiple($nids) as $item) {
        $product = $item->get('field_product')->entity;
        $qty = (float) ($item->get('field_quantity')->value ?? 0);
        $price = $product ? (float) ($product->get('field_cost_price')->value ?? 0) : 0;
        $subtotal += $qty * $price;
      }
    }

    $tax_pct = (float) (\Drupal::config('stitchlyn_quotation.settings')->get('tax_percentage') ?? 0);
    $tax = ($subtotal * $tax_pct) / 100.0;

    $q = Node::load($quotation_id);
    $discount = $q && $q->hasField('field_discount') ? (float) ($q->get('field_discount')->value ?? 0) : 0.0;

    $total = $subtotal - $discount + $tax;

    return [
      'subtotal' => round($subtotal, 2),
      'tax'      => round($tax, 2),
      'total'    => round($total, 2),
    ];
  }

}