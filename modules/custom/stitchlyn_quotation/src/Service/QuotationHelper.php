<?php

namespace Drupal\stitchlyn_quotation\Service;

use Drupal\node\Entity\Node;

class QuotationHelper {

  public function renderLineItemTable($quotation_id) {
    $header = ['Product', 'Quantity', 'Price'];
    $rows = [];

    $nids = \Drupal::entityQuery('node')
      ->accessCheck(TRUE)
      ->condition('type', 'quotation_line_items')
      ->condition('field_linked_quotation', $quotation_id)
      ->execute();

    foreach (Node::loadMultiple($nids) as $i) {
      $p = $i->get('field_product')->entity;
      $qty = $i->get('field_quantity')->value ?? 0;
      $price = $p ? $p->get('field_cost_price')->value ?? 0 : 0;
      $rows[] = [$p ? $p->label() : '-', $qty, number_format($price, 2)];
    }

    return [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => t('No items added yet.'),
    ];
  }

  public function computeTotals($quotation_id) {
    $subtotal = 0;
    $nids = \Drupal::entityQuery('node')
      ->accessCheck(TRUE)
      ->condition('type', 'quotation_line_items')
      ->condition('field_linked_quotation', $quotation_id)
      ->execute();

    foreach (Node::loadMultiple($nids) as $i) {
      $p = $i->get('field_product')->entity;
      $qty = $i->get('field_quantity')->value ?? 0;
      $price = $p ? $p->get('field_cost_price')->value ?? 0 : 0;
      $subtotal += $qty * $price;
    }

    $tax_pct = \Drupal::config('stitchlyn_quotation.settings')->get('tax_percentage') ?? 0;
    $tax = ($subtotal * $tax_pct) / 100;
    $q = Node::load($quotation_id);
    $discount = $q->get('field_discount')->value ?? 0;
    $total = $subtotal - $discount + $tax;

    return [
      'subtotal' => round($subtotal, 2),
      'tax' => round($tax, 2),
      'total' => round($total, 2),
    ];
  }
}
