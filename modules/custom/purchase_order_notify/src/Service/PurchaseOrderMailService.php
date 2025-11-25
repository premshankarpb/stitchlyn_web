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

    if ($customer instanceof \Drupal\user\UserInterface) {
      $email = $customer->get('mail')->value;
      if (empty($email)) {
        return;
      }
    }

    $to = $email;
    $langcode = $node->language()->getId();

    $params['subject'] = 'Purchase Order Fulfilled';
    $params['message'] = sprintf(
      "A Purchase Order has been fulfilled.\n\nTitle: %s\nURL: %s",
      $node->label(),
      $node->toUrl('canonical', ['absolute' => TRUE])->toString()
    );

    $result = $this->mailManager->mail(
      'purchase_order_notify',
      'purchase_order_fulfilled',
      $to,
      $langcode,
      $params
    );

    if (empty($result['result'])) {
      $this->logger->error('Failed to send Fulfilled email for Purchase Order @title.', ['@title' => $node->label()]);
    }
    else {
      $this->logger->info('Fulfilled email sent successfully for Purchase Order @title.', ['@title' => $node->label()]);
    }
  }

  /**
   * Send an email when a Quotation node is created (accepted).
   */
  public function sendQuotationAcceptedMail(NodeInterface $node): void {

    // 1️⃣ Get the customer email
    $customer = $node->get('field_customer_reference')->entity;

    if ($customer instanceof \Drupal\user\UserInterface) {
      $email = $customer->get('mail')->value;
      $username = $user->getDisplayName();
      if (empty($email)) {
        return;
      }
    }
    else {
      // No valid customer user → skip
      return;
    }

    // 2️⃣ Build the PDF URL (existing route)
    $pdf_url = \Drupal::request()->getSchemeAndHttpHost() . "/dashboard/quotation/" . $node->id() . "/pdf";

    // 3️⃣ Use current user's session for authentication
    $request = \Drupal::requestStack()->getCurrentRequest();
    $session_name = session_name(); // Usually SESS...
    $session_id = $request->getSession()->getId();
    $cookie = $session_name . '=' . $session_id;
    $pdf_data = NULL;

    // 4️⃣ Fetch PDF using HTTP client
    try {
      $client = \Drupal::httpClient();
      $response = $client->get($pdf_url, [
        'headers' => [
            'Cookie' => $cookie,
        ],
      ]);

      if ($response->getStatusCode() !== 200) {
        \Drupal::messenger()->addError('Could not download PDF.');
        return;
      }

      $pdf_data = $response->getBody()->getContents();

    } catch (\Exception $e) {
        \Drupal::messenger()->addError('Error fetching PDF: ' . $e->getMessage());
        \Drupal::logger('purchase_order_notify')->error('PDF fetch error: @message', ['@message' => $e->getMessage()]);
        return;
    }

    // 5️⃣ Prepare email parameters
    $params['message'] = sprintf(
      "Hello %s,<br><br>
      A quotation has been accepted.<br><br>
      Please find the quotation attached.<br><br>
      If it is not available, you can also access it using the link below:<br>
      <a href=\"%s\">%s</a>",
      $username,
      \Drupal::request()->getSchemeAndHttpHost() . "/dashboard/quotation/" . $node->id() . "/pdf",
      $node->label()
    );

    // 6️⃣ Add attachment only if PDF is available
    if (!empty($pdf_data)) {
      $params['attachment'] = [
          'filecontent' => $pdf_data,
          'filename' => 'quotation-' . $node->id() . '.pdf',
          'filemime' => 'application/pdf',
      ];
    }

    $result = $this->mailManager->mail(
      'purchase_order_notify',
      'quotation_accepted', // <-- Correct mail key
      $email,
      $langcode,
      $params
    );

    // 7️⃣ Log result
    if (empty($result['result'])) {
        $this->logger->error('Failed to send Quotation Accepted email for @title.', ['@title' => $node->label()]);
    }
    else {
        $this->logger->info('Quotation Accepted email sent successfully for @title.', ['@title' => $node->label()]);
    }
  }

  /**
   * Send an email when a Inventory is running low.
   */
  public function sendRestockInventory(NodeInterface $node): void {
    $config = \Drupal::config('stitchlyn_basic.erp_settings');
    $mail_to = $config->get('client_email');

    $to = $mail_to;
    $langcode = $node->language()->getId();

    $params['subject'] = 'Reminder : Restock Inventory';
    $params['message'] = sprintf(
      "Hello <br> br> Restock the Inventory Raw material.\n\nTitle: %s\nURL: %s",
      $node->label(),
      \Drupal::request()->getSchemeAndHttpHost() . '/dashboard/inventory/' . $node->id()
    );

    $result = $this->mailManager->mail(
      'purchase_order_notify',
      'restock_raw_material', // <-- Correct mail key
      $to,
      $langcode,
      $params
    );

    if (empty($result['result'])) {
      $this->logger->error('Failed to send Inventory restock email for @title.', ['@title' => $node->label()]);
    }
    else {
      $this->logger->info('Inventory restock email sent successfully for @title.', ['@title' => $node->label()]);
    }
  }

}
