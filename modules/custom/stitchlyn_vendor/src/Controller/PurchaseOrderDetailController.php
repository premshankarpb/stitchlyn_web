<?php

namespace Drupal\stitchlyn_vendor\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Drupal\profile\Entity\Profile;
use Symfony\Component\HttpFoundation\Response;

class PurchaseOrderDetailController extends ControllerBase {

  public function view(NodeInterface $node) {
    // Check that this is a purchase_order node
    if ($node->bundle() !== 'purchase_order') {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    // Get nodes referencing this purchase_order
    $referencing_nodes = [];

    $item_nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
      'type' => 'purchase_order_items',
      'field_purchase_order' => $node->id(),
    ]);

    // Get user reference (e.g., vendor)
    $user = NULL;
    $profile = NULL;
    if ($node->hasField('field_vendor') && !$node->get('field_vendor')->isEmpty()) {
      $user = $node->field_vendor->entity;
      // Load profile (assuming profile type is 'vendor_profile')
      $profiles = \Drupal::entityTypeManager()
        ->getStorage('profile')
        ->loadByProperties([
          'uid' => $user->id(),
          'type' => 'vendor',
        ]);
      $profile = reset($profiles);
    }

    return [
      '#theme' => 'stitchlyn_po_detail',
      '#node' => $node,
      '#vendor_user' => $user,
      '#vendor_profile' => $profile,
      '#referencing_nodes' => $item_nodes,
      '#title' => $node->label(),
    ];
  }

  public function pdf(NodeInterface $node) {
    if ($node->bundle() !== 'purchase_order') {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    // Load items
    $item_nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'type' => 'purchase_order_items',
        'field_purchase_order' => $node->id(),
      ]);

    // Load vendor
    $user = NULL;
    $profile = NULL;
    if ($node->hasField('field_vendor') && !$node->get('field_vendor')->isEmpty()) {
      $user = $node->field_vendor->entity;
      $profiles = \Drupal::entityTypeManager()
        ->getStorage('profile')
        ->loadByProperties([
          'uid' => $user->id(),
          'type' => 'vendor',
        ]);
      $profile = reset($profiles);
    }

    // Render twig HTML
    $build = [
      '#theme' => 'stitchlyn_po_pdf',
      '#node' => $node,
      '#vendor_user' => $user,
      '#vendor_profile' => $profile,
      '#referencing_nodes' => $item_nodes,
      '#title' => $node->label(),
    ];

    // Now pass by reference
    $html = \Drupal::service('renderer')->renderPlain($build);


    // Get dompdf service
    $dompdf = \Drupal::service('stitchlyn_vendor.dompdf');
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $pdf_content = $dompdf->output();

    return new Response(
      $pdf_content,
      200,
      [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'attachment; filename="purchase_order_' . $node->id() . '.pdf"',
      ]
    );
  }

}
