<?php

namespace Drupal\stitchlyn_quotation\Service;

use Drupal\node\Entity\Node;
use Drupal\Core\Link;
use Drupal\Core\Url;

class QuotationHelper {

  public function renderLineItemTable($quotation_id) {
    $header = ['Product', 'Quantity', 'Price', 'Actions'];
    $rows = [];

    $query = \Drupal::entityQuery('node')
      ->condition('type', 'quotation_line_items')
      ->condition('field_linked_quotation', $quotation_id);
    $nids = $query->execute();

    foreach (Node::loadMultiple($nids) as $item) {
      $product = $item->get('field_product')->entity;
      $qty = $item->get('field_quantity')->value;
      $price = $product ? $product->get('field_cost_price')->value : 0;
      $view_link = Link::fromTextAndUrl('View', Url::fromRoute('entity.node.canonical', ['node' => $item->id()]))->toString();
      $remove_link = Link::fromTextAndUrl('Remove', Url::fromRoute('stitchlyn_quotation.item_remove', ['nid' => $item->id()]))->toString();

      $rows[] = [
        $product ? $product->label() : '-',
        $qty,
        $price,
        $view_link . ' | ' . $remove_link,
      ];
    }

    return [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => t('No items added yet.'),
    ];
  }

}