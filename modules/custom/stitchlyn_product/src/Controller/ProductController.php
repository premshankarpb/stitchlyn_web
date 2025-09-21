<?php

namespace Drupal\stitchlyn_product\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\Core\Url;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\node\Entity\Node;

class ProductController extends ControllerBase {

  /**
   * Page title: Product name.
   */
  public function title(NodeInterface $node): string {
    // 404 if not a Product.
    if ($node->bundle() !== 'product') {
      throw new NotFoundHttpException();
    }
    return $node->label();
  }

  /**
   * Render the product node using the node view builder.
   */
  public function view(NodeInterface $node) {
    if ($node->bundle() !== 'product') {
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

  /**
   * Render the add form for a work_order node.
   */

  public function add() {
    // Check access: user must have permission to create 'work_order' content.
    $access = \Drupal::currentUser()->hasPermission('create work_order content');
    if (!$access) {
      throw new AccessDeniedHttpException();
    }

    // Create a new unsaved node entity of type 'work_order'.
    $node = Node::create(['type' => 'product']);

    // Use the entity form builder to render the 'add' form.
    return \Drupal::service('entity.form_builder')->getForm($node, 'default');
  }

  /**
   * Render the edit form for a work_order node.
   */
  public function edit(NodeInterface $node) {
    if ($node->bundle() !== 'product') {
      throw new NotFoundHttpException();
    }

    if (!$node->access('update')) {
      throw new AccessDeniedHttpException();
    }

    // Use the entity form builder to get the edit form.
    $form = \Drupal::service('entity.form_builder')->getForm($node, 'edit');

    return $form;
  }

  /**
   * Access control for the controller routes.
   */
  public function access(NodeInterface $node) {
    if ($node->bundle() !== 'product') {
      return AccessResult::forbidden();
    }

    return $node->access('update') ? AccessResult::allowed() : AccessResult::forbidden();
  }

}