<?php

namespace Drupal\stitchlyn_vendor\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;

class VendorController extends ControllerBase {

  public function handleAutocomplete(Request $request) {
    $results = [];
    $string = $request->query->get('q');

    if ($string) {
      $query = \Drupal::entityQuery('node')
        ->condition('type', 'inventory_item')
        ->condition('title', $string, 'CONTAINS')
        ->range(0, 10);

      $nids = $query->execute();
      $nodes = Node::loadMultiple($nids);

      foreach ($nodes as $node) {
        $results[] = ['value' => $node->id(), 'label' => $node->getTitle()];
      }
    }

    return new JsonResponse($results);
  }
}
