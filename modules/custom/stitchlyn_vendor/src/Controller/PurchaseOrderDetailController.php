<?php

namespace Drupal\stitchlyn_vendor\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Drupal\profile\Entity\Profile;

class PurchaseOrderDetailController extends ControllerBase {

  public function view(NodeInterface $node) {
    // Check that this is a purchase_order node
    if ($node->bundle() !== 'purchase_order') {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    // Fetch related Purchase Order Items via entity reference
    $po_items = [];
    if ($node->hasField('field_po_items')) {
      foreach ($node->get('field_po_items')->referencedEntities() as $item_node) {
        if ($item_node->bundle() === 'purchase_order_items') {
          $po_items[] = $item_node;
        }
      }
    }

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
      '#items' => $po_items,
      '#vendor_user' => $user,
      '#vendor_profile' => $profile,
      '#title' => $node->label(),
    ];
  }
}
