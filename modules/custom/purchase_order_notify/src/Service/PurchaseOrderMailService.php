<?php

namespace Drupal\purchase_order_notify\Service;

use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Service to send email notifications for Purchase Orders and Quotations.
 */
class PurchaseOrderMailService {

  protected MailManagerInterface $mailManager;
  protected LoggerChannelInterface $logger;
  protected ConfigFactoryInterface $configFactory;

  public function __construct(
    MailManagerInterface $mail_manager,
    LoggerChannelInterface $logger,
    ConfigFactoryInterface $config_factory
  ) {
    $this->mailManager = $mail_manager;
    $this->logger = $logger;
    $this->configFactory = $config_factory;
  }

  /**
   * Send an email when a Purchase Order is marked as "Fulfilled".
   */
  public function sendPurchaseOrderFulfilledMail(NodeInterface $node): void {

    // -----------------------------
    // 1) Vendor user
    // -----------------------------
    $vendor = $node->get('field_vendor')->entity;

    if (!$vendor instanceof \Drupal\user\UserInterface) {
      return;
    }

    $email = $vendor->getEmail();
    $username = $vendor->getDisplayName();
    $langcode = $node->language()->getId();

    if (empty($email)) {
      return;
    }

    // -----------------------------
    // 2) Build PDF using shared service
    // -----------------------------
    $pdf_output = \Drupal::service('purchase_order_notify.pdf_builder')
      ->buildPurchaseOrderPdf($node);

    // -----------------------------
    // 3) Base URL
    // -----------------------------
    $base_url = \Drupal::request()->getSchemeAndHttpHost();

    // Base params
    // $params = [
    //   'username'   => $username,
    //   'po_title'   => $node->label(),
    //   'po_link'    => $base_url . '/dashboard/po/' . $node->id(),
    //   'pdf_link'   => $base_url . '/dashboard/po/' . $node->id() . '/pdf',
    //   'site_name'  => \Drupal::config('system.site')->get('name'),
    // ];

    // -----------------------------
    // 4) PDF Attachment
    // -----------------------------
    $attachment = [];
    if (!empty($pdf_output)) {
      $attachment = [
        'filecontent' => $pdf_output,
        'filename'    => 'purchase-order-' . $node->id() . '.pdf',
        'filemime'    => 'application/pdf',
      ];
    }

    // -----------------------------
    // 5) Vendor profile details
    // -----------------------------
    $vendor_profile = NULL;

    $profiles = \Drupal::entityTypeManager()
      ->getStorage('profile')
      ->loadByProperties([
        'uid' => $vendor->id(),
        'type' => 'vendor',
      ]);

    if (!empty($profiles)) {
      $vendor_profile = reset($profiles);
    }

    // Correct vendor name from profile
    $vendor_name = $vendor_profile ? ($vendor_profile->get('field_vendor_name')->value ?? $username) : $username;
    $vendor_address = $vendor_profile ? nl2br($vendor_profile->get('field_billing_address')->value ?? '') : '';
    $vendor_gst = $vendor_profile ? ($vendor_profile->get('field_gst_number')->value ?? '') : '';
    $vendor_contact = $vendor_profile ? ($vendor_profile->get('field_phone_number')->value ?? '') : '';

    // -----------------------------
    // 6) Payment status (taxonomy)
    // -----------------------------
    $payment_status = '';

    if (!$node->get('field_payment_status')->isEmpty()) {
      $term = \Drupal::entityTypeManager()
        ->getStorage('taxonomy_term')
        ->load($node->get('field_payment_status')->target_id);

      if ($term) {
        $payment_status = $term->label();
      }
    }

    // $params['payment_status'] = $payment_status;

    // -----------------------------
    // 7) Issue / Due date
    // -----------------------------
    $issue_date = $node->get('field_date_of_purchase')->value ?? date('Y-m-d');
    $due_date   = date('Y-m-d', strtotime($issue_date . ' +7 days'));

    // $params['issue_date'] = $issue_date;
    // $params['due_date']   = $due_date;

    // -----------------------------
    // 8) Totals
    // -----------------------------
    $subtotal = (float) ($node->get('field_subtotal_amount')->value ?? 0);
    $tax      = (float) ($node->get('field_tax_amount')->value ?? 0);
    $total    = (float) ($node->get('field_total_amount')->value ?? 0);

    // $params['subtotal'] = $subtotal;
    // $params['tax']      = $tax;
    // $params['total']    = $total;

    // -----------------------------
    // 9) Line Items (Purchase Order Items)
    // -----------------------------
    $items = [];
    $storage = \Drupal::entityTypeManager()->getStorage('node');

    $item_nids = $storage->getQuery()
      ->condition('type', 'purchase_order_items')
      ->condition('field_purchase_order', $node->id())
      ->accessCheck(FALSE)
      ->execute();

    if (!empty($item_nids)) {
      $line_items = $storage->loadMultiple($item_nids);

      foreach ($line_items as $item) {
        $ref = $item->get('field_item_reference')->entity;
        $name = $ref ? $ref->label() : '';
        $qty = (float) ($item->get('field_quantity')->value ?? 0);
        $rate = (float) ($item->get('field_item_rate')->value ?? 0);
        $line_total = $qty * $rate;

        $items[] = [
          'name'       => $name,
          'quantity'   => $qty,
          'unit_price' => $rate,
          'total'      => $line_total,
        ];
      }
    }

    // $params['items'] = $items;

     $params = [
      'username'   => $username,
      'po_title'   => $node->label(),
      'po_link'    => $base_url . '/dashboard/po/' . $node->id(),
      'pdf_link'   => $base_url . '/dashboard/po/' . $node->id() . '/pdf',
      'site_name'  => \Drupal::config('system.site')->get('name'),
      'vendor_name'  => $vendor_name,
      'vendor_address'  => $vendor_address,
      'vendor_gst'  => $vendor_gst,
      'vendor_contact'  => $vendor_contact,
      'payment_status'  => $payment_status,
      'issue_date'  => $issue_date,
      'due_date'  => $due_date,
      'items'  => $items,
      'subtotal'  => $subtotal,
      'tax'  => $tax,
      'total'  => $total,
      'attachment'  => $attachment,
    ];
    $params1 = $params;
    unset($params1['attachment']);
    \Drupal::logger('params')->warning('<pre><code>' . print_r($params1, TRUE) . '</code></pre>');

    // -----------------------------
    // 10) Send email
    // -----------------------------
    $result = $this->mailManager->mail(
      'purchase_order_notify',
      'purchase_order_fulfilled',
      $email,
      $langcode,
      $params
    );

    // Logging
    if (empty($result['result'])) {
      $this->logger->error('Failed to send PO Fulfilled email for @title.', [
        '@title' => $node->label(),
      ]);
    }
    else {
      $this->logger->info('PO Fulfilled email sent successfully for @title.', [
        '@title' => $node->label(),
      ]);
    }
  }



  /**
   * Send an email when a Quotation node is accepted.
   */
  public function sendQuotationAcceptedMail(NodeInterface $node): void {

    // 1️⃣ Get customer user
    $customer = $node->get('field_customer_reference')->entity;

    if (!$customer instanceof \Drupal\user\UserInterface) {
      return;
    }

    $email = $customer->getEmail();
    $username = $customer->getDisplayName();

    if (empty($email)) {
      return;
    }

    $to = $email;
    $langcode = $node->language()->getId();

    // 2️⃣ Build the Quotation PDF using PdfBuilder service
    $pdf_output = \Drupal::service('purchase_order_notify.pdf_builder')
      ->buildQuotationPdf($node);

    // 3️⃣ Prepare template variables
    $params = [
      'username'         => $username,
      'quotation_title'  => $node->label(),
      'quotation_pdf'    => \Drupal::request()->getSchemeAndHttpHost() . "/dashboard/quotation/" . $node->id() . "/pdf",
    ];

    // 4️⃣ Attach PDF if successfully generated
    if (!empty($pdf_output)) {
      $params['attachment'] = [
        'filecontent' => $pdf_output,
        'filename'    => 'quotation-' . $node->id() . '.pdf',
        'filemime'    => 'application/pdf',
      ];
    }

    // 5️⃣ Send email
    $result = $this->mailManager->mail(
      'purchase_order_notify',
      'quotation_accepted',
      $to,
      $langcode,
      $params
    );

    // 6️⃣ Log result
    if (empty($result['result'])) {
      $this->logger->error('Failed to send Quotation Accepted email for @title.', [
        '@title' => $node->label(),
      ]);
    }
    else {
      $this->logger->info('Quotation Accepted email sent successfully for @title.', [
        '@title' => $node->label(),
      ]);
    }
  }

  /**
   * Send an email when an Inventory Item is running low.
   */
  public function sendRestockInventory(NodeInterface $node): void {

    // 1️⃣ Get admin email from Stitchlyn ERP settings
    $config = \Drupal::config('stitchlyn_basic.erp_settings');
    $mail_to = $config->get('client_email');

    if (empty($mail_to)) {
      $this->logger->error('Inventory restock email could not be sent. No admin email configured.');
      return;
    }

    $to = $mail_to;
    $langcode = $node->language()->getId();

    // 2️⃣ Build the item link
    $item_link = \Drupal::request()->getSchemeAndHttpHost() . '/dashboard/inventory/' . $node->id();

    // 3️⃣ Prepare template variables for hook_mail()
    $params = [
      'item_title' => $node->label(),
      'item_link'  => $item_link,
    ];

    // 4️⃣ Send the email
    $result = $this->mailManager->mail(
      'purchase_order_notify',
      'restock_raw_material',
      $to,
      $langcode,
      $params
    );

    // 5️⃣ Logging
    if (empty($result['result'])) {
      $this->logger->error(
        'Failed to send Inventory Restock email for @title.',
        ['@title' => $node->label()]
      );
    }
    else {
      $this->logger->info(
        'Inventory Restock email sent successfully for @title.',
        ['@title' => $node->label()]
      );
    }
  }

}
