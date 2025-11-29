<?php

namespace Drupal\stitchlyn_basic\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\Core\Url;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\node\Entity\Node;

class PaymentController extends ControllerBase {

  /**
   * Payment view page.
   */
  public function view(NodeInterface $node) {
    if ($node->bundle() !== 'payment_record') {
      throw new NotFoundHttpException();
    }

    $view_mode = 'full';
    $build = $this->entityTypeManager()
      ->getViewBuilder('node')
      ->view($node, $view_mode);
    $build['#cache']['contexts'][] = 'user.permissions';

    return $build;
  }

  /**
   * Render the add form for a payment_record node.
   */

  public function add() {
    // Check access: user must have permission to create 'payment_record' content.
    $access = \Drupal::currentUser()->hasPermission('create payment_record content');
    if (!$access) {
      throw new AccessDeniedHttpException();
    }

    // Create a new unsaved node entity of type 'payment_record'.
    $node = Node::create(['type' => 'payment_record']);

    // Use the entity form builder to render the 'add' form.
    return \Drupal::service('entity.form_builder')->getForm($node, 'default');
  }

  /**
   * Render the edit form for a payment_record node.
   */
  public function edit(NodeInterface $node) {
    if ($node->bundle() !== 'payment_record') {
      throw new NotFoundHttpException();
    }

    if (!$node->access('update')) {
      throw new AccessDeniedHttpException();
    }

    // Use the entity form builder to get the edit form.
    $form = \Drupal::service('entity.form_builder')->getForm($node, 'edit');

    return $form;
  }

}