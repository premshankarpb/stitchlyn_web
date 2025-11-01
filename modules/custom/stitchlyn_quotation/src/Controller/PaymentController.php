<?php

namespace Drupal\stitchlyn_quotation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\node\Entity\Node;

class PaymentController extends ControllerBase {

  public function savePayment($quotation, Request $request) {
    $date = $request->get('date');
    $amount = $request->get('amount');
    $mode_tid = $request->get('mode'); // taxonomy term id
    $remarks = $request->get('remarks');

    if (empty($date) || empty($amount)) {
      return new JsonResponse(['status' => 'error', 'message' => 'Missing required fields.']);
    }

    $node = Node::create([
      'type' => 'payment_record',
      'title' => 'Payment - ' . date('YmdHis'),
      'field_reference_order' => ['target_id' => $quotation],
      'field_payment_date' => $date,
      'field_amount_paid' => $amount,
      'field_payment_mode' => ['target_id' => $mode_tid],
      'body' => ['value' => $remarks, 'format' => 'basic_html'],
      'status' => 1,
    ]);
    $node->save();

    return new JsonResponse([
      'status' => 'success',
      'message' => 'Payment added successfully.',
      'html' => $this->buildPaymentTable($quotation),
    ]);
  }

  public function deletePayment($nid) {
    if ($node = Node::load($nid)) {
      $quotation = $node->get('field_reference_order')->target_id;
      $node->delete();
      return new JsonResponse([
        'status' => 'success',
        'html' => $this->buildPaymentTable($quotation),
      ]);
    }
    return new JsonResponse(['status' => 'error', 'message' => 'Record not found.']);
  }

  private function buildPaymentTable($quotation) {
    $rows = '';
    $total = 0;
    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'payment_record')
      ->condition('field_reference_order', $quotation)
      ->accessCheck(FALSE)
      ->execute();

    if ($nids) {
      $nodes = Node::loadMultiple($nids);
      foreach ($nodes as $p) {
        $date = $p->get('field_payment_date')->value;
        $amount = $p->get('field_amount_paid')->value;
        $mode = $p->get('field_payment_mode')->entity->label();
        $serial = $p->get('field_payment_id')->value;
        $remarks = $p->get('body')->value;
        $total += $amount;

        $rows .= "<tr>
          <td>{$date}</td>
          <td>₹{$amount}</td>
          <td>{$mode}</td>
          <td>{$serial}</td>
          <td>{$remarks}</td>
          <td><button class='btn btn-outline-danger btn-sm remove-payment' data-id='{$p->id()}'>Remove</button></td>
        </tr>";
      }
    }

    return "<table class='table table-bordered table-striped align-middle'>
      <thead class='table-light'>
        <tr><th>Date</th><th>Amount Paid</th><th>Mode</th><th>Payment ID</th><th>Remarks</th><th>Action</th></tr>
      </thead><tbody>{$rows}</tbody></table>
      <div class='text-end fw-bold fs-5 mt-2'>Total Paid: ₹<span id='payment-total'>{$total}</span></div>";
  }
}