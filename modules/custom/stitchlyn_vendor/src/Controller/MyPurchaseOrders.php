<?php

namespace Drupal\stitchlyn_vendor\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MyPurchaseOrders extends ControllerBase {

  protected $currentUser;

  public function __construct(AccountProxyInterface $current_user) {
    $this->currentUser = $current_user;
  }  

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user')
    );
  }  

  public function view() {

    $current_user_id = $this->currentUser->id();

    // Load all 'purchase_order' nodes
    $nids = \Drupal::entityQuery('node')
      ->accessCheck(TRUE)
      ->condition('type', 'purchase_order')
      ->condition('status', 1) // only published
      ->condition('field_vendor', $current_user_id)
      ->sort('created', 'DESC')
      ->execute();

    $nodes = Node::loadMultiple($nids);

    return [
      '#theme' => 'stitchlyn_my_purchase_orders',
      '#node' => $nodes,
      '#title' => $this->t('My Purchase Orders'),
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }
}

