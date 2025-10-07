<?php

namespace Drupal\stitchlyn_quotation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller for creating a new Quotation node and redirecting to edit page.
 */
class QuotationAddController extends ControllerBase {

  /**
   * Create a new Quotation node and redirect to /quotation/{nid}/edit.
   */
  public function createQuotation() {
    $current_user = $this->currentUser();

    // Auto-generate quotation number (simple increment logic).
    $last_nid = \Drupal::entityQuery('node')
      ->accessCheck(TRUE)
      ->condition('type', 'quotation')
      ->sort('created', 'DESC')
      ->range(0, 1)
      ->execute();
    $last_number = 1000;
    if (!empty($last_nid)) {
      $last_node = Node::load(reset($last_nid));
      if ($last_node->hasField('field_quotation_number')) {
        $last_number = (int) $last_node->get('field_quotation_number')->value;
      }
    }
    $new_number = $last_number + 1;

    // Set default payment status if available (first term in the vocabulary).
    $default_payment_status = NULL;
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('payment_status');
    if (!empty($terms)) {
      $default_payment_status = reset($terms)->tid;
    }

    // Create the Quotation node.
    $node = Node::create([
      'type' => 'quotation',
      'title' => 'Quotation #' . $new_number,
      'uid' => $current_user->id(),
      'field_quotation_number' => $new_number,
      'field_quotation_date' => date('Y-m-d'),
      'field_payment_status' => $default_payment_status,
      'field_discount' => 0,
      'field_subtotal_amount' => 0,
      'field_tax_amount' => 0,
      'field_total_amount' => 0,
      'status' => 0, // Unpublished until finalized.
    ]);
    $node->save();

    // Redirect to edit form.
    $url = '/dashboard/quotation/' . $node->id() . '/edit';
    return new RedirectResponse($url);
  }

}
