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

    // -----------------------------
    // 7) Issue / Due date
    // -----------------------------
    $issue_date = $node->get('field_date_of_purchase')->value ?? date('Y-m-d');
    $due_date   = date('Y-m-d', strtotime($issue_date . ' +7 days'));

    // -----------------------------
    // 8) Totals
    // -----------------------------
    $subtotal = (float) ($node->get('field_subtotal_amount')->value ?? 0);
    $tax      = (float) ($node->get('field_tax_amount')->value ?? 0);
    $total    = (float) ($node->get('field_total_amount')->value ?? 0);

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

    // Final params array
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

    // 1) Get customer user from field_customer_reference.
    $customer = $node->get('field_customer_reference')->entity;
    if (!$customer instanceof \Drupal\user\UserInterface) {
      return;
    }

    $email = $customer->getEmail();
    $username = $customer->getDisplayName();
    if (empty($email)) {
      return;
    }

    $langcode = $node->language()->getId();
    $base_url = \Drupal::request()->getSchemeAndHttpHost();
    $pdf_link = $base_url . '/dashboard/quotation/' . $node->id() . '/pdf';

    // 2) ERP config (for tax %, site name etc.)
    $config = $this->configFactory->get('stitchlyn_basic.erp_settings');
    $tax_percentage = (float) ($config->get('tax_percentage') ?? 0);
    $site_name = \Drupal::config('system.site')->get('name');

    // 3) Customer profile details (profile type = customer).
    $customer_profile = [
      'name' => $username,
      'email' => $email,
      'billing_address' => '',
      'phone' => '',
      'gst' => '',
    ];

    $profiles = \Drupal::entityTypeManager()
      ->getStorage('profile')
      ->loadByProperties([
        'uid' => $customer->id(),
        'type' => 'customer',
      ]);

    if (!empty($profiles)) {
      $profile = reset($profiles);
      $customer_profile['billing_address'] = nl2br($profile->get('field_billing_address')->value ?? '');
      $customer_profile['phone'] = $profile->get('field_phone_number')->value ?? '';
      $customer_profile['gst'] = $profile->get('field_gst_number')->value ?? '';
    }

    // 4) Quotation dates.
    $issue_date = $node->get('field_quotation_date')->value ?? date('Y-m-d');
    $due_date = $node->get('field_expected_due_date')->value ?? date('Y-m-d');

    // 5) Line items (quatation_line_items linked via field_linked_quotation).
    $items = [];
    $subtotal = 0;

    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $line_item_nids = $storage->getQuery()
      ->condition('type', 'quatation_line_items')
      ->condition('field_linked_quotation', $node->id())
      ->accessCheck(FALSE)
      ->execute();

    if (!empty($line_item_nids)) {
      $line_items = $storage->loadMultiple($line_item_nids);

      foreach ($line_items as $item) {
        $product = $item->get('field_product')->entity;
        $pname = $product ? $product->label() : '';
        $quantity = (float) $item->get('field_quantity')->value;

        $unit_price = $product && $product->hasField('field_cost_price')
          ? (float) $product->get('field_cost_price')->value
          : 0;

        $total_price = $unit_price * $quantity;
        $subtotal += $total_price;

        $items[] = [
          'name'       => $pname,
          'quantity'   => $quantity,
          'unit_price' => $unit_price,
          'total'      => $total_price,
        ];
      }
    }

    // 6) Totals: discount, tax, grand total.
    $discount = (float) ($node->get('field_discount')->value ?? 0);
    $tax = ($subtotal - $discount) * ($tax_percentage / 100);
    $total = $subtotal - $discount + $tax;

    // 7) Build params for the mail template.
    $params = [
      'username'        => $username,
      'invoice_no'      => 'Quotation #' . $node->get('field_quotation_number')->value,
      'customer'        => $customer_profile,
      'items'           => $items,
      'subtotal'        => $subtotal,
      'discount'        => $discount,
      'tax_percentage'  => $tax_percentage,
      'tax'             => $tax,
      'total'           => $total,
      'issue_date'      => $issue_date,
      'due_date'        => $due_date,
      'pdf_link'        => $pdf_link,
      'site_name'       => $site_name,
      'pdf_available' => FALSE,
    ];

    // 8) Send the mail.
    $result = $this->mailManager->mail(
      'purchase_order_notify',
      'quotation_accepted',
      $email,
      $langcode,
      $params
    );

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
