<?php

namespace Drupal\stitchlyn_vendor\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;

class PurchaseOrderForm extends FormBase {

  public function getFormId() {
    return 'stitchlyn_vendor_purchase_order_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#prefix'] = '<div id="purchase-order-form-wrapper">';
    $form['#suffix'] = '</div>';

    // Top section fields
    $form['field_date_of_purchase'] = [
      '#type' => 'date',
      '#title' => $this->t('Date of Purchase'),
      '#required' => TRUE,
    ];

    $form['field_vendor'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Vendor'),
      '#target_type' => 'user',
      '#required' => TRUE,
    ];

    $form['field_payment_status'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Payment Status'),
      '#target_type' => 'taxonomy_term',
      '#selection_settings' => [
        'target_bundles' => ['payment_status'],
      ],
      '#required' => TRUE,
    ];

    $form['body'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Remarks'),
      '#format' => 'basic_html',
    ];

    // Purchase items table
    $items = $form_state->get('items') ?: [0];
    $form['items_table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Item'),
        $this->t('Qty'),
        $this->t('Rate'),
        $this->t('Tax'),
        $this->t('Total'),
        $this->t('Action')
      ],
      '#prefix' => '<div id="items-wrapper">',
      '#suffix' => '</div>',
    ];

    foreach ($items as $delta) {
      $form['items_table'][$delta]['item'] = [
        '#type' => 'textfield',
        '#attributes' => ['class' => ['autocomplete-item']],
        '#autocomplete_route_name' => 'stitchlyn_vendor.autocomplete_inventory',
      ];
      $form['items_table'][$delta]['qty'] = ['#type' => 'number'];
      $form['items_table'][$delta]['rate'] = ['#type' => 'number'];
      $form['items_table'][$delta]['tax'] = ['#type' => 'number'];
      $form['items_table'][$delta]['total'] = ['#type' => 'number', '#attributes' => ['readonly' => 'readonly']];
      $form['items_table'][$delta]['remove'] = [
        '#type' => 'submit',
        '#name' => 'remove_row_' . $delta,
        '#value' => $this->t('Remove'),
        '#submit' => ['::removeRow'],
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => '::ajaxRefresh',
          'wrapper' => 'purchase-order-form-wrapper',
        ],
      ];
    }

    $form['add_item'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Item'),
      '#submit' => ['::addRow'],
      '#limit_validation_errors' => [],
      '#ajax' => [
        'callback' => '::ajaxRefresh',
        'wrapper' => 'purchase-order-form-wrapper',
      ],
    ];

    // Calculated fields
    $form['field_subtotal_amount'] = ['#type' => 'number', '#title' => 'Subtotal'];
    $form['field_tax_amount'] = ['#type' => 'number', '#title' => 'Tax'];
    $form['field_total_amount'] = ['#type' => 'number', '#title' => 'Total'];

    $form['submit'] = ['#type' => 'submit', '#value' => 'Save'];

    return $form;
  }

  public function ajaxRefresh(array &$form, FormStateInterface $form_state) {
    return $form;
  }

  public function addRow(array &$form, FormStateInterface $form_state) {
    $items = $form_state->get('items') ?: [0];
    $items[] = max($items) + 1;
    $form_state->set('items', $items);
    $form_state->setRebuild();
  }

  public function removeRow(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement()['#name'];
    $delta = str_replace('remove_row_', '', $trigger);
    $items = array_diff($form_state->get('items') ?: [], [$delta]);
    $form_state->set('items', $items);
    $form_state->setRebuild();
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Save purchase order
    $node = Node::create([
      'type' => 'purchase_order',
      'title' => 'PO - ' . date('Y-m-d'),
      'field_date_of_purchase' => $form_state->getValue('field_date_of_purchase'),
      'field_vendor' => $form_state->getValue('field_vendor'),
      'field_payment_status' => $form_state->getValue('field_payment_status'),
      'field_subtotal_amount' => $form_state->getValue('field_subtotal_amount'),
      'field_tax_amount' => $form_state->getValue('field_tax_amount'),
      'field_total_amount' => $form_state->getValue('field_total_amount'),
      'body' => $form_state->getValue('body'),
    ]);
    $node->save();

    // Save items
    foreach ($form_state->get('items') as $delta) {
      Node::create([
        'type' => 'purchase_order_items',
        'title' => 'PO Item',
        'field_item_reference' => $form['items_table'][$delta]['item']['#value'],
        'field_quantity' => $form['items_table'][$delta]['qty']['#value'],
        'field_item_rate' => $form['items_table'][$delta]['rate']['#value'],
        'field_tax_amount' => $form['items_table'][$delta]['tax']['#value'],
        'field_total_amount' => $form['items_table'][$delta]['total']['#value'],
        'field_purchase_order' => $node->id(),
      ])->save();
    }
  }
}
