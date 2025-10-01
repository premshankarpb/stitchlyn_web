<?php

namespace Drupal\stitchlyn_quotation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Response;

class QuotationController extends ControllerBase {

  public function title(NodeInterface $node): string {
    if ($node->bundle() !== 'quotation') {
      throw new NotFoundHttpException();
    }
    return $node->label();
  }

  public function view(NodeInterface $node) {
    if ($node->bundle() !== 'quotation') {
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
   * Generate PDF for Quotation.
   */
  public function pdf(NodeInterface $node) {
    if ($node->bundle() !== 'quotation') {
      throw new NotFoundHttpException();
    }

    // Load customer profile if exists
    $customer_profile = NULL;
    if ($node->hasField('field_customer_reference') && !$node->get('field_customer_reference')->isEmpty()) {
      $customer = $node->field_customer_reference->entity;
      if ($customer) {
        $profiles = \Drupal::entityTypeManager()
          ->getStorage('profile')
          ->loadByProperties([
            'uid' => $customer->id(),
            'type' => 'customer',
          ]);
        $customer_profile = reset($profiles);
      }
    }

    // Load quotation items (assuming quotation_items content type or paragraph ref)
    $quotation_items = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'type' => 'quatation_line_items',
        'field_linked_quotation' => $node->id(),
      ]);

    // Render Twig template for PDF
    $build = [
      '#theme' => 'stitchlyn_quotation_pdf',
      '#node' => $node,
      '#customer_profile' => $customer_profile,
      '#quotation_items' => $quotation_items,
      '#title' => $node->label(),
    ];

    $html = \Drupal::service('renderer')->renderPlain($build);

    // Dompdf
    $dompdf = \Drupal::service('stitchlyn_vendor.dompdf'); // reuse the same Dompdf service
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $pdf_content = $dompdf->output();

    return new Response(
      $pdf_content,
      200,
      [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'attachment; filename="quotation_' . $node->id() . '.pdf"',
      ]
    );
  }
}