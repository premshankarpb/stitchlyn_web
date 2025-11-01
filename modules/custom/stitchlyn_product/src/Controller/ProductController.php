<?php

namespace Drupal\stitchlyn_product\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\Core\Access\AccessResult;
use Drupal\node\Entity\Node;

class ProductController extends ControllerBase {

  public function title(NodeInterface $node): string {
    if ($node->bundle() !== 'product') {
      throw new NotFoundHttpException();
    }
    return $node->label();
  }

  public function view(NodeInterface $node) {
    if ($node->bundle() !== 'product') {
      throw new NotFoundHttpException();
    }

    $view_mode = 'full';
    $build = $this->entityTypeManager()
      ->getViewBuilder('node')
      ->view($node, $view_mode);

    $build['#cache']['contexts'][] = 'user.permissions';
    return $build;
  }

  public function add() {
    $node = Node::create(['type' => 'product']);

    // Check entity-level access.
    if (!$node->access('create')) {
      throw new AccessDeniedHttpException();
    }
    $form = \Drupal::service('entity.form_builder')->getForm($node, 'default');

    // Add a custom submit handler to redirect after save.
    $form['#submit'][] = [$this, 'redirectToListing'];

    return $form;
  }

  public function edit(NodeInterface $node) {
    if ($node->bundle() !== 'product') {
      throw new NotFoundHttpException();
    }

    if (!$node->access('update')) {
      throw new AccessDeniedHttpException();
    }

    $form = \Drupal::service('entity.form_builder')->getForm($node, 'default');
    $form['#submit'][] = [$this, 'redirectToListing'];

    return $form;
  }

  public function access(NodeInterface $node) {
    if ($node->bundle() !== 'product') {
      return AccessResult::forbidden();
    }

    return $node->access('update') ? AccessResult::allowed() : AccessResult::forbidden();
  }

  /**
   * Custom redirect handler: after node save, go back to listing page.
   */
  public function redirectToListing(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $url = \Drupal\Core\Url::fromRoute('<front>')->setPath('/dashboard/product-list');
    $form_state->setRedirectUrl($url);
  }
}