<?php

namespace Drupal\stitchlyn_vendor\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\file\Entity\File;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PurchaseOrderDetailController extends ControllerBase {

  public function view(NodeInterface $node) {
    // Check that this is a purchase_order node
    if ($node->bundle() !== 'purchase_order') {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    // Get nodes referencing this purchase_order
    $referencing_nodes = [];

    $item_nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
      'type' => 'purchase_order_items',
      'field_purchase_order' => $node->id(),
    ]);

    // Get user reference (e.g., vendor)
    $user = NULL;
    $profile = NULL;
    if ($node->hasField('field_vendor') && !$node->get('field_vendor')->isEmpty()) {
      $user = $node->field_vendor->entity;
      // Load profile (assuming profile type is 'vendor_profile')
      $profiles = \Drupal::entityTypeManager()
        ->getStorage('profile')
        ->loadByProperties([
          'uid' => $user->id(),
          'type' => 'vendor',
        ]);
      $profile = reset($profiles);
    }

    return [
      '#theme' => 'stitchlyn_po_detail',
      '#node' => $node,
      '#vendor_user' => $user,
      '#vendor_profile' => $profile,
      '#referencing_nodes' => $item_nodes,
      '#title' => $node->label(),
    ];
  }

  /**
   * Generate Purchase Order PDF.
   */
  public function pdf(Node $node) {
    if ($node->bundle() !== 'purchase_order') {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    // --- ERP CONFIG ---
    $config = $this->config('stitchlyn_basic.erp_settings');
    $client_name     = $config->get('client_name') ?? 'Company Name';
    $client_address  = nl2br($config->get('client_address') ?? '');
    $client_contact  = $config->get('client_contact') ?? '';
    $client_gst      = $config->get('client_gst') ?? '';
    $client_banking  = nl2br($config->get('client_banking') ?? '');
    $client_logo     = '';

    if ($fid = $config->get('client_logo')) {
      $file = File::load(reset($fid));
      if ($file) {
        $client_logo = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
      }
    }

    // --- PO meta ---
    $po_number  = $node->get('field_po_number')->value ?? ('PO #' . $node->id());
    $issue_date = $node->get('field_date_of_purchase')->value ?? date('Y-m-d');
    $due_date   = date('Y-m-d', strtotime($issue_date . ' +7 days'));
    $subtotal   = (float) ($node->get('field_subtotal_amount')->value ?? 0);
    $tax        = (float) ($node->get('field_tax_amount')->value ?? 0);
    $total      = (float) ($node->get('field_total_amount')->value ?? 0);

    // --- Payment status (taxonomy term label) ---
    $payment_status = '';
    if (!$node->get('field_payment_status')->isEmpty()) {
      $term = $node->get('field_payment_status')->entity;
      if ($term) {
        $payment_status = $term->label();
      }
    }

    // --- Vendor user & vendor profile (profile type = vendor) ---
    $vendor_user = NULL;
    $vendor_profile = NULL;
    if (!$node->get('field_vendor')->isEmpty()) {
      $vendor_user = $node->get('field_vendor')->entity;
      if ($vendor_user) {
        $profiles = \Drupal::entityTypeManager()
          ->getStorage('profile')
          ->loadByProperties([
            'uid'  => $vendor_user->id(),
            'type' => 'vendor',
          ]);
        $vendor_profile = reset($profiles) ?: NULL;
      }
    }

    // --- Line items (purchase_order_items) ---
    $items = [];
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $item_nids = $storage->getQuery()
      ->condition('type', 'purchase_order_items')
      ->condition('field_purchase_order', $node->id())
      ->accessCheck(FALSE)
      ->execute();

    if ($item_nids) {
      foreach ($storage->loadMultiple($item_nids) as $li) {
        $ref   = $li->get('field_item_reference')->entity;
        $name  = $ref ? $ref->label() : '';
        $qty   = (float) ($li->get('field_quantity')->value ?? 0);
        $rate  = (float) ($li->get('field_item_rate')->value ?? 0);
        $tax_a = (float) ($li->get('field_tax_amount')->value ?? 0);
        $line_total = ($qty * $rate) + $tax_a;

        $items[] = [
          'name'       => $name,
          'quantity'   => $qty,
          'unit_price' => $rate,
          'tax'        => $tax_a,
          'total'      => $line_total,
          'remarks'    => $li->get('body')->value ?? '',
        ];
      }
    }

    // --- Build render array for Twig ---
    $build = [
      '#theme'           => 'stitchlyn_po_pdf',
      // Company
      '#logo'            => $client_logo,
      '#client_name'     => $client_name,
      '#client_address'  => $client_address,
      '#client_contact'  => $client_contact,
      '#client_gst'      => $client_gst,
      '#client_banking'  => $client_banking,
      // PO header
      '#invoice_no'      => $po_number,
      '#issue_date'      => $issue_date,
      '#due_date'        => $due_date,
      '#payment_status'  => $payment_status,
      // Vendor
      '#vendor_user'     => $vendor_user,
      '#vendor_profile'  => $vendor_profile,
      // Items & totals
      '#referencing_nodes'=> $items,
      '#subtotal'        => $subtotal,
      '#tax'             => $tax,
      '#total'           => $total,
    ];

    $html = \Drupal::service('renderer')->renderPlain($build);

    // --- PDF ---
    $options = new Options();
    $options->set('isRemoteEnabled', TRUE);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $filename = 'purchase_order_' . $node->id() . '.pdf';
    return new Response(
      $dompdf->output(),
      200,
      [
        'Content-Type'        => 'application/pdf',
        'Content-Disposition' => 'inline; filename="' . $filename . '"',
      ]
    );
  }

}