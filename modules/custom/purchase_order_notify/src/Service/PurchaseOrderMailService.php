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

    $customer = $node->get('field_customer_reference')->entity;

    if ($customer instanceof \Drupal\user\UserInterface) {
      $email = $customer->get('mail')->value;
      if (empty($email)) {
        return;
      }
    }

    $pdf_url = \Drupal::request()->getSchemeAndHttpHost() . "/dashboard/quotation/" . $node->id() . "/pdf";

    // 2. Download the PDF content.
    $client = \Drupal::httpClient();
    $response = $client->get($pdf_url);

    if ($response->getStatusCode() !== 200) {
      $this->messenger()->addError('Could not download PDF.');
      return;
    }

    $pdf_data = $response->getBody()->getContents();

    $to = $email;
    $langcode = $node->language()->getId();

    $params['subject'] = 'Quotation Accepted';
    $params['attachment'] = [
        'filecontent' => $pdf_data,
        'filename' => 'quotation-' . $node->id() . '.pdf',
        'filemime' => 'application/pdf',
    ];
    $params['message'] = sprintf(
      "A quotation has been accepted.\n\nTitle: %s\nURL: %s",
      $node->label(),
      $node->toUrl('canonical', ['absolute' => TRUE])->toString()
    );

    $result = $this->mailManager->mail(
      'purchase_order_notify',
      'quotation_accepted', // <-- Correct mail key
      $to,
      $langcode,
      $params
    );

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

    $params['subject'] = 'Restock Inventory';
    $params['message'] = sprintf(
      "Restock the Inventory Raw material.\n\nTitle: %s\nURL: %s",
      $node->label(),
      $node->toUrl('canonical', ['absolute' => TRUE])->toString()
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
