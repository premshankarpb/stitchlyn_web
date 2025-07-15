<?php

namespace Drupal\stitchlyn_vendor\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;

class InventoryDetailController extends ControllerBase {

  public function view(NodeInterface $node) {
    // Ensure it's the right content type
    if ($node->bundle() !== 'inventory_item') {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    return [
      '#theme' => 'stitchlyn_inventory_detail',
      '#node' => $node,
      '#title' => $node->label(),
    ];
  }
}
