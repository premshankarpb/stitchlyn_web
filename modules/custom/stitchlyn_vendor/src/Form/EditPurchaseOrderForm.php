<?php

namespace Drupal\stitchlyn_vendor\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;

class EditPurchaseOrderForm extends FormBase {

  protected $purchaseOrder;

  public static function create(ContainerInterface $container) {
    return new static();
  }

  public function getFormId() {
    return 'stitchlyn_vendor_edit_purchase_order_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, Node $purchase_order = NULL){
    //$this->purchaseOrder = Node::load($purchase_order);
    $this->purchaseOrder = $purchase_order;

    if (!$this->purchaseOrder->access('update')) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }

    if (!$this->purchaseOrder || $this->purchaseOrder->bundle() !== 'purchase_order') {
      $form['message'] = ['#markup' => $this->t('Invalid purchase order.')];
      return $form;
    }

    $form['#prefix'] = '<div id="purchase-order-form-wrapper">';
    $form['#suffix'] = '</div>';

    $form['field_date_of_purchase'] = [
      '#type' => 'date',
      '#title' => $this->t('Date of Purchase'),
      '#default_value' => $this->purchaseOrder->get('field_date_of_purchase')->value,
      '#required' => TRUE,
    ];

    $form['field_vendor'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Vendor'),
      '#target_type' => 'user',
      '#default_value' => $this->purchaseOrder->get('field_vendor')->entity,
      '#required' => TRUE,
    ];

    $form['field_payment_status'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Payment Status'),
      '#target_type' => 'taxonomy_term',
      '#selection_settings' => ['target_bundles' => ['payment_status']],
      '#default_value' => $this->purchaseOrder->get('field_payment_status')->entity,
      '#required' => TRUE,
    ];

    $form['body'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Remarks'),
      '#format' => 'basic_html',
      '#default_value' => $this->purchaseOrder->get('body')->value,
    ];

    // Load purchase items
    $items = $form_state->get('items');
    if (!isset($items)) {
      $items = [];
      $query = \Drupal::entityTypeManager()->getStorage('node')->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'purchase_order_items')
        ->condition('field_purchase_order', $this->purchaseOrder->id());
      $nids = $query->execute();
      $item_nodes = Node::loadMultiple($nids);
      foreach ($item_nodes as $delta => $item_node) {
        $items[] = $delta;
        $form_state->set('item_node_' . $delta, $item_node);
      }
      $form_state->set('items', $items);
    }

    $form['items_table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Item'),
        $this->t('Qty'),
        $this->t('Rate'),
        $this->t('Tax'),
        $this->t('Total'),
        $this->t('Action'),
      ],
      '#prefix' => '<div id="items-wrapper">',
      '#suffix' => '</div>',
    ];

    foreach ($items as $delta) {
      /** @var \Drupal\node\Entity\Node $item_node */
      $item_node = $form_state->get('item_node_' . $delta);

      $form['items_table'][$delta]['item'] = [
        '#type' => 'entity_autocomplete',
        '#target_type' => 'node',
        '#title' => $this->t('Item'),
        '#selection_settings' => ['target_bundles' => ['inventory_item']],
        '#default_value' => $item_node->get('field_item_reference')->entity ?? NULL,
      ];
      $form['items_table'][$delta]['qty'] = ['#type' => 'number', '#default_value' => $item_node->get('field_quantity')->value];
      $form['items_table'][$delta]['rate'] = ['#type' => 'number', '#default_value' => $item_node->get('field_item_rate')->value];
      $form['items_table'][$delta]['tax'] = ['#type' => 'number', '#default_value' => $item_node->get('field_tax_amount')->value];
      $form['items_table'][$delta]['total'] = ['#type' => 'number', '#attributes' => ['readonly' => 'readonly'], '#default_value' => $item_node->get('field_total_amount')->value];

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

    // Subtotal/tax/total
    $form['field_subtotal_amount'] = [
      '#type' => 'number',
      '#title' => 'Subtotal',
      '#default_value' => $this->purchaseOrder->get('field_subtotal_amount')->value,
    ];
    $form['field_tax_amount'] = [
      '#type' => 'number',
      '#title' => 'Tax',
      '#default_value' => $this->purchaseOrder->get('field_tax_amount')->value,
    ];
    $form['field_total_amount'] = [
      '#type' => 'number',
      '#title' => 'Total',
      '#default_value' => $this->purchaseOrder->get('field_total_amount')->value,
    ];

    $form['submit'] = ['#type' => 'submit', '#value' => 'Update'];

    return $form;
  }

  public function ajaxRefresh(array &$form, FormStateInterface $form_state) {
    return $form;
  }

  public function addRow(array &$form, FormStateInterface $form_state) {
    $items = $form_state->get('items') ?: [];
    $next = empty($items) ? 0 : max($items) + 1;
    $items[] = $next;
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
    $purchase_order = $this->purchaseOrder;

    $purchase_order->set('field_date_of_purchase', $form_state->getValue('field_date_of_purchase'));
    $purchase_order->set('field_vendor', $form_state->getValue('field_vendor'));
    $purchase_order->set('field_payment_status', $form_state->getValue('field_payment_status'));
    $purchase_order->set('field_subtotal_amount', $form_state->getValue('field_subtotal_amount'));
    $purchase_order->set('field_tax_amount', $form_state->getValue('field_tax_amount'));
    $purchase_order->set('field_total_amount', $form_state->getValue('field_total_amount'));
    $purchase_order->set('body', $form_state->getValue('body'));
    $purchase_order->save();

    // Delete all existing items
    $query = \Drupal::entityTypeManager()->getStorage('node')->getQuery()
      ->condition('type', 'purchase_order_items')
      ->condition('field_purchase_order', $purchase_order->id());
    $nids = $query->execute();
    $items_to_delete = Node::loadMultiple($nids);
    foreach ($items_to_delete as $item_node) {
      $item_node->delete();
    }

    // Save updated items
    foreach ($form_state->get('items') as $delta) {
      $item_row = $form_state->getValue(['items_table', $delta]);

      if (!empty($item_row['item'])) {
        Node::create([
          'type' => 'purchase_order_items',
          'title' => 'PO Item',
          'field_item_reference' => $item_row['item'],
          'field_quantity' => $item_row['qty'],
          'field_item_rate' => $item_row['rate'],
          'field_tax_amount' => $item_row['tax'],
          'field_total_amount' => $item_row['total'],
          'field_purchase_order' => $purchase_order->id(),
        ])->save();
      }
    }

    \Drupal::messenger()->addMessage($this->t('Purchase Order updated successfully.'));
  }
}

