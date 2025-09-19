<?php

namespace Drupal\stitchlyn_quotation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class WOController extends ControllerBase {

  /**
   * Page title: Product name.
   */
  public function title(NodeInterface $node): string {
    // 404 if not a Product.
    if ($node->bundle() !== 'work_order') {
      throw new NotFoundHttpException();
    }
    return $node->label();
  }

  /**
   * Render the product node using the node view builder.
   */
  public function view(NodeInterface $node) {
    if ($node->bundle() !== 'work_order') {
      throw new NotFoundHttpException();
    }

    // Choose a view mode. 'full' or create a custom one like 'product_detail'.
    $view_mode = 'full';

    $build = $this->entityTypeManager()
      ->getViewBuilder('node')
      ->view($node, $view_mode);

    // Add cacheability (Drupal will merge this with node cache metadata).
    $build['#cache']['contexts'][] = 'user.permissions';

    return $build;
  }

}