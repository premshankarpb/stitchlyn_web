<?php

namespace Drupal\stitchlyn_quotation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller for creating a new Quotation node and redirecting to edit page.
 */
class QuotationAddController extends ControllerBase {

  /**
   * Create a new Quotation node and redirect to /quotation/{nid}/edit.
   */
  public function createQuotation() {
    // Create a new Quotation node.
    $node = Node::create([
      'type' => 'quotation',
      'title' => 'New Quotation - ' . date('Y-m-d H:i:s'),
      'status' => 0, // unpublished by default
      'uid' => $this->currentUser()->id(),
    ]);
    $node->save();

    // Redirect to custom edit page.
    $url = '/dashboard/quotation/' . $node->id() . '/edit';
    return new RedirectResponse($url);
  }

}
