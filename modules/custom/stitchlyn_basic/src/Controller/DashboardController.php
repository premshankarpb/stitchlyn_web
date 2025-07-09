<?php

namespace Drupal\stitchlyn_basic\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class DashboardController extends ControllerBase {

  public function view() {
    $counts = [
      'product' => $this->getInventoryCountByType('Product'),
      'raw_material' => $this->getInventoryCountByType('Raw Material'),
      'tool' => $this->getInventoryCountByType('Tool'),
      'purchase_orders' => $this->getContentTypeCount('purchase_order'),
      'work_orders' => $this->getContentTypeCount('work_order'),
    ];

    return [
      '#theme' => 'stitchlyn_dashboard',
      '#counts' => $counts,
      '#attached' => [
        'library' => [],
      ],
    ];
  }

  private function getInventoryCountByType($type_name) {
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'inventory_item')
      ->condition('field_inventory_type.entity.name', $type_name);
    return $query->count()->execute();
  }

  private function getContentTypeCount($content_type) {
    $query = \Drupal::entityQuery('node')
      ->condition('type', $content_type);
    return $query->count()->execute();
  }
}