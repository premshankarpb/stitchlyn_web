<?php

namespace Drupal\purchase_order_notify\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Drupal\node\NodeInterface;
use Drupal\file\Entity\File;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Builds PDFs for Quotations and Purchase Orders.
 */
class PdfBuilder {

  protected RendererInterface $renderer;
  protected ConfigFactoryInterface $configFactory;
  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(
    RendererInterface $renderer,
    ConfigFactoryInterface $configFactory,
    EntityTypeManagerInterface $entityTypeManager
  ) {
    $this->renderer = $renderer;
    $this->configFactory = $configFactory;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Build Quotation PDF and return binary content.
   */
  public function buildQuotationPdf(NodeInterface $node): string {

    if ($node->bundle() !== 'quotation') {
      return '';
    }

    // -----------------------------------------------------------------------
    // CONFIG SETTINGS
    // -----------------------------------------------------------------------
    $config = $this->configFactory->get('stitchlyn_basic.erp_settings');

    $client_name = $config->get('client_name') ?? 'Company Name';
    $client_address = nl2br($config->get('client_address') ?? '');
    $client_contact = $config->get('client_contact') ?? '';
    $client_gst = $config->get('client_gst') ?? '';
    $client_banking = nl2br($config->get('client_banking') ?? '');
    $tax_percentage = (float) ($config->get('tax_percentage') ?? 0);

    // Logo
    $client_logo = '';
    if ($fid = $config->get('client_logo')) {
      $file = File::load(reset($fid));
      if ($file) {
        $client_logo = \Drupal::service('file_url_generator')
          ->generateAbsoluteString($file->getFileUri());
      }
    }

    // -----------------------------------------------------------------------
    // CUSTOMER DETAILS
    // -----------------------------------------------------------------------
    $customer_profile = [
      'name' => '',
      'email' => '',
      'billing_address' => '',
      'phone' => '',
      'gst' => '',
    ];

    if ($node->hasField('field_customer_reference') && !$node->get('field_customer_reference')->isEmpty()) {
      $user = $node->get('field_customer_reference')->entity;

      if ($user) {
        $profiles = $this->entityTypeManager
          ->getStorage('profile')
          ->loadByProperties([
            'uid' => $user->id(),
            'type' => 'customer',
          ]);

        $profile = $profiles ? reset($profiles) : NULL;

        $customer_profile = [
          'name' => $user->getDisplayName(),
          'email' => $user->getEmail(),
          'billing_address' => $profile?->get('field_billing_address')->value ?? '',
          'phone' => $profile?->get('field_phone_number')->value ?? '',
          'gst' => $profile?->get('field_gst_number')->value ?? '',
        ];
      }
    }

    // -----------------------------------------------------------------------
    // DATES
    // -----------------------------------------------------------------------
    $issue_date = $node->get('field_quotation_date')->value ?? date('Y-m-d');
    $due_date = $node->get('field_expected_due_date')->value ?? date('Y-m-d');

    // -----------------------------------------------------------------------
    // LINE ITEMS
    // -----------------------------------------------------------------------
    $storage = $this->entityTypeManager->getStorage('node');
    $line_item_nids = $storage->getQuery()
      ->condition('type', 'quatation_line_items')
      ->condition('field_linked_quotation', $node->id())
      ->accessCheck(FALSE)
      ->execute();

    $items = [];
    $subtotal = 0;

    if (!empty($line_item_nids)) {
      $line_items = $storage->loadMultiple($line_item_nids);

      foreach ($line_items as $item) {
        $product = $item->get('field_product')->entity;
        $pname = $product ? $product->label() : '';
        $quantity = (float) $item->get('field_quantity')->value;
        $unit_price = $product?->get('field_cost_price')->value ?? 0;
        $total_price = $unit_price * $quantity;
        $subtotal += $total_price;

        $items[] = [
          'name' => $pname,
          'quantity' => $quantity,
          'unit_price' => $unit_price,
          'total' => $total_price,
        ];
      }
    }

    $discount = (float) ($node->get('field_discount')->value ?? 0);
    $tax = ($subtotal - $discount) * ($tax_percentage / 100);
    $total = $subtotal - $discount + $tax;

    // -----------------------------------------------------------------------
    // RENDER ARRAY
    // -----------------------------------------------------------------------
    $build = [
      '#theme' => 'quotation_pdf',
      '#logo' => $client_logo,
      '#client_name' => $client_name,
      '#client_address' => $client_address,
      '#client_contact' => $client_contact,
      '#client_gst' => $client_gst,
      '#client_banking' => $client_banking,
      '#tax_percentage' => $tax_percentage,
      '#customer_profile' => $customer_profile,
      '#items' => $items,
      '#subtotal' => $subtotal,
      '#discount' => $discount,
      '#tax' => $tax,
      '#total' => $total,
      '#issue_date' => $issue_date,
      '#due_date' => $due_date,
      '#invoice_no' => 'Quotation #' . $node->get('field_quotation_number')->value,
    ];

    // Render HTML
    $html = $this->renderer->renderPlain($build);

    // -----------------------------------------------------------------------
    // GENERATE PDF
    // -----------------------------------------------------------------------
    $options = new Options();
    $options->set('isRemoteEnabled', TRUE);
    $dompdf = new Dompdf($options);

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return $dompdf->output();
  }

  /**
   * Build Purchase Order PDF and return binary content.
   */
  public function buildPurchaseOrderPdf(NodeInterface $node): string {

    if ($node->bundle() !== 'purchase_order') {
        return '';
    }

    // --- ERP CONFIG ---
    $config = $this->configFactory->get('stitchlyn_basic.erp_settings');

    $client_name     = $config->get('client_name') ?? 'Company Name';
    $client_address  = nl2br($config->get('client_address') ?? '');
    $client_contact  = $config->get('client_contact') ?? '';
    $client_gst      = $config->get('client_gst') ?? '';
    $client_banking  = nl2br($config->get('client_banking') ?? '');
    $tax_percentage  = $config->get('tax_percentage') ?? '';

    // Logo
    $client_logo = '';
    if ($fid = $config->get('client_logo')) {
        $file = File::load(reset($fid));
        if ($file) {
        $client_logo = \Drupal::service('file_url_generator')
            ->generateAbsoluteString($file->getFileUri());
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
        $term = $this->entityTypeManager->getStorage('taxonomy_term')
        ->load($node->get('field_payment_status')->target_id);
        $payment_status = $term?->label() ?? '';
    }

    // --- Vendor & vendor profile ---
    $vendor_user = NULL;
    $vendor_profile = NULL;

    if (!$node->get('field_vendor')->isEmpty()) {
        $vendor_user = $node->get('field_vendor')->entity;

        if ($vendor_user) {
        $profiles = $this->entityTypeManager->getStorage('profile')
            ->loadByProperties([
            'uid'  => $vendor_user->id(),
            'type' => 'vendor',
            ]);
        $vendor_profile = reset($profiles) ?: NULL;
        }
    }

    // --- Line items ---
    $items = [];
    $storage = $this->entityTypeManager->getStorage('node');
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
        $line_total = ($qty * $rate);

        $items[] = [
            'name'       => $name,
            'quantity'   => $qty,
            'unit_price' => $rate,
            'total'      => $line_total,
        ];
        }
    }

    // --- Render array for Twig template ---
    $build = [
        '#theme'            => 'stitchlyn_po_pdf',
        // Company
        '#logo'             => $client_logo,
        '#client_name'      => $client_name,
        '#client_address'   => $client_address,
        '#client_contact'   => $client_contact,
        '#client_gst'       => $client_gst,
        '#client_banking'   => $client_banking,
        '#tax_percentage'   => $tax_percentage,
        // PO metadata
        '#invoice_no'       => $po_number,
        '#issue_date'       => $issue_date,
        '#due_date'         => $due_date,
        '#payment_status'   => $payment_status,
        // Vendor
        '#vendor_user'      => $vendor_user,
        '#vendor_profile'   => $vendor_profile,
        // Items
        '#referencing_nodes'=> $items,
        '#subtotal'         => $subtotal,
        '#tax'              => $tax,
        '#total'            => $total,
    ];

    $html = $this->renderer->renderPlain($build);
    \Drupal::logger('PO_html')->warning('<pre><code>' . print_r($html, TRUE) . '</code></pre>');
    // --- Generate PDF ---
    $options = new Options();
    $options->set('isRemoteEnabled', TRUE);
    $dompdf = new Dompdf($options);

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $vv = $dompdf->output();
    \Drupal::logger('PO_output')->warning('<pre><code>' . print_r($vv, TRUE) . '</code></pre>');
    return $dompdf->output();
  }

}