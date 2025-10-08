<?php

namespace Drupal\stitchlyn_quotation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\RedirectResponse;

class QuotationAddController extends ControllerBase {

  /**
   * Creates a new Quotation node and redirects to its edit page.
   */
  public function createQuotation() {
    $last_nid = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', 'quotation')
      ->sort('created', 'DESC')
      ->range(0, 1)
      ->execute();
    $last_number = 1000;
    if ($last_nid) {
      $last_node = Node::load(reset($last_nid));
      if ($last_node && $last_node->hasField('field_quotation_number')) {
        $last_number = (int) $last_node->get('field_quotation_number')->value;
      }
    }
    $new_number = $last_number + 1;

    $node = Node::create([
      'type' => 'quotation',
      'title' => 'Quotation #' . $new_number,
      'field_quotation_number' => $new_number,
      'field_quotation_date' => date('Y-m-d'),
      'uid' => $this->currentUser()->id(),
      'status' => 0,
    ]);
    $node->save();

    return new RedirectResponse('/dashboard/quotation/' . $node->id() . '/edit');
  }

}
