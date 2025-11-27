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

    $customer = $node->get('field_vendor')->entity;

    if (!$customer instanceof \Drupal\user\UserInterface) {
      return;
    }

    $email = $customer->getEmail();
    $username = $customer->getDisplayName();

    if (empty($email)) {
      return;
    }

    $langcode = $node->language()->getId();
    $to = $email;

    // Build PDF via shared service
    $pdf_output = \Drupal::service('purchase_order_notify.pdf_builder')
      ->buildPurchaseOrderPdf($node);

    // Mail template params
    $params = [
      'username'  => $username,
      'po_title'  => $node->label(),
      'po_link'   => \Drupal::request()->getSchemeAndHttpHost() . '/po/' . $node->id(),
      'pdf_link'  => \Drupal::request()->getSchemeAndHttpHost() . '/dashboard/po/' . $node->id() . '/pdf',
    ];

    if (!empty($pdf_output)) {
      $params['attachment'] = [
        'filecontent' => $pdf_output,
        'filename'    => 'purchase-order-' . $node->id() . '.pdf',
        'filemime'    => 'application/pdf',
      ];
    }

    $result = $this->mailManager->mail(
      'purchase_order_notify',
      'purchase_order_fulfilled',
      $to,
      $langcode,
      $params
    );

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
