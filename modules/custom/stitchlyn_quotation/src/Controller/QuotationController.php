<?php

namespace Drupal\stitchlyn_quotation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;
use Dompdf\Dompdf;
use Dompdf\Options;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for Quotation display and PDF export.
 */
class QuotationController extends ControllerBase {

  /**
   * Title callback.
   */
  public function title(NodeInterface $node): string {
    if ($node->bundle() !== 'quotation') {
      throw new NotFoundHttpException();
    }
    return $node->label();
  }

  /**
   * Quotation view page.
   */
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
   * Generate Quotation PDF.
   */
  public function pdf(Node $node) {
    if ($node->bundle() !== 'quotation') {
      throw new NotFoundHttpException();
    }

    // -----------------------------------------------------------------------
    // ERP CONFIG DETAILS
    // -----------------------------------------------------------------------
    $config = $this->config('stitchlyn_basic.erp_settings');
    $client_name = $config->get('client_name') ?? 'Company Name';
    $client_address = nl2br($config->get('client_address') ?? '');
    $client_contact = $config->get('client_contact') ?? '';
    $client_gst = $config->get('client_gst') ?? '';
    $client_banking = nl2br($config->get('client_banking') ?? '');
    $tax_percentage = (float) ($config->get('tax_percentage') ?? 0);

    // Client logo.
    $client_logo = '';
    if ($fid = $config->get('client_logo')) {
      $file = File::load(reset($fid));
      if ($file) {
        $client_logo = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
      }
    }

    // -----------------------------------------------------------------------
    // DATES
    // -----------------------------------------------------------------------
    $issue_date = $node->get('field_quotation_date')->value ?? date('Y-m-d');
    $due_date = date('Y-m-d', strtotime($issue_date . ' +3 days'));

    // -----------------------------------------------------------------------
    // CUSTOMER DETAILS
    // -----------------------------------------------------------------------
    $customer_profile = [
      'name' => '',
      'billing_address' => '',
      'phone' => '',
      'email' => '',
      'gst' => '',
    ];

    if ($node->hasField('field_customer_reference') && !$node->get('field_customer_reference')->isEmpty()) {
      $user = $node->get('field_customer_reference')->entity;

      if ($user) {
        // Load customer profile.
        $profiles = \Drupal::entityTypeManager()
          ->getStorage('profile')
          ->loadByProperties([
            'uid' => $user->id(),
            'type' => 'customer',
          ]);

        if (!empty($profiles)) {
          $profile = reset($profiles);

          $customer_profile = [
            'name' => $user->getDisplayName(),
            'email' => $user->getEmail(),
            'billing_address' => $profile->get('field_billing_address')->value ?? '',
            'phone' => $profile->get('field_phone_number')->value ?? '',
            'gst' => $profile->get('field_gst_number')->value ?? '',
          ];
        }
      }
    }

    // -----------------------------------------------------------------------
    // QUOTATION LINE ITEMS
    // -----------------------------------------------------------------------
    $storage = \Drupal::entityTypeManager()->getStorage('node');
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
        $unit_price = $product && $product->hasField('field_cost_price')
          ? (float) $product->get('field_cost_price')->value
          : 0;
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

    // -----------------------------------------------------------------------
    // TOTALS
    // -----------------------------------------------------------------------
    $discount = (float) ($node->get('field_discount')->value ?? 0);
    $tax = ($subtotal - $discount) * ($tax_percentage / 100);
    $total = $subtotal - $discount + $tax;

    // -----------------------------------------------------------------------
    // RENDER ARRAY FOR TWIG
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

    // -----------------------------------------------------------------------
    // RENDER HTML
    // -----------------------------------------------------------------------
    $html = \Drupal::service('renderer')->renderPlain($build);

    // -----------------------------------------------------------------------
    // GENERATE PDF
    // -----------------------------------------------------------------------
    $options = new Options();
    $options->set('isRemoteEnabled', TRUE);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $filename = 'quotation_' . $node->id() . '.pdf';
    return new Response(
      $dompdf->output(),
      200,
      [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'inline; filename="' . $filename . '"',
      ]
    );
  }
}