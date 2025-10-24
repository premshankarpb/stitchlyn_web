<?php

namespace Drupal\stitchlyn_vendor\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MyQuotation extends ControllerBase {

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
      ->condition('type', 'quotation')
      ->condition('field_customer_reference', $current_user_id)
      ->sort('created', 'DESC')
      ->execute();

    $nodes = Node::loadMultiple($nids);

    // Filter by moderation state after loading. Use this variable to filter with workflow states
    $filtered_nodes = array_filter($nodes, function ($node) {
      $state = $node->get('moderation_state')->value ?? '';
      return in_array($state, ['published', 'draft', 'requested']);
    });

    return [
      '#theme' => 'stitchlyn_quotation',
      '#node' => $nodes,
      '#title' => $this->t('My Quotations'),
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }
}

