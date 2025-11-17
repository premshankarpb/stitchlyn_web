<?php

namespace Drupal\stitchlyn_vendor\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Database\Database;

/**
 * Class PurchaseOrderEditForm.
 */
class PurchaseOrderEditForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'stitchlyn_vendor_po_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $nid = NULL) {
    if (!$nid || !is_numeric($nid)) {
      \Drupal::messenger()->addError($this->t('Invalid Purchase Order.'));
      return $form;
    }

    $po = Node::load($nid);
    if (!$po || $po->bundle() !== 'purchase_order') {
      \Drupal::messenger()->addError($this->t('Invalid Purchase Order.'));
      return $form;
    }

    $form['#attributes']['id'] = 'stitchlyn-po-edit-form';
    $form['#attributes']['data-po-nid'] = $nid;

    $form['po_details'] = [
      '#type' => 'details',
      '#title' => $this->t('Purchase Order Details'),
      '#open' => TRUE,
    ];

    $form['po_details']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $po->label(),
      '#required' => TRUE,
    ];

    $form['po_details']['field_vendor'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Vendor'),
      '#target_type' => 'user',
      '#default_value' => $po->get('field_vendor')->entity ?? NULL,
      '#required' => TRUE,
      '#attributes' => [
        'class' => ['vendor-autocomplete'],
      ],
    ];

    $form['po_details']['field_date_of_purchase'] = [
      '#type' => 'date',
      '#title' => $this->t('Date of Purchase'),
      '#default_value' => $po->get('field_date_of_purchase')->value ?? '',
    ];

    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('purchase_order_status');
    $options = [];
    foreach ($terms as $term) {
      $options[$term->tid] = $term->name;
    }

    $form['po_details']['order_status'] = [
      '#type' => 'select',
      '#title' => $this->t('Order Status'),
      '#options' => $options,
      '#default_value' => $po ? $po->get('field_purchase_order_status')->target_id : '',
      '#required' => TRUE,
    ];

    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('payment_status');
    $pay_options = [];
    foreach ($terms as $term) {
      $pay_options[$term->tid] = $term->name;
    }

    $form['po_details']['payment_status'] = [
      '#type' => 'select',
      '#title' => $this->t('Payment Status'),
      '#options' => $pay_options,
      '#default_value' => $po ? $po->get('field_payment_status')->target_id : '',
      '#required' => TRUE,
    ];

    $form['po_details']['body'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Remarks'),
      '#default_value' => $po->get('body')->value ?? '',
      '#format' => 'basic_html',
    ];

    // ---------------------------------------------------------------------------
    // Purchase Items Section (with Add Item button + wrapper div)
    // ---------------------------------------------------------------------------
    $form['po_items'] = [
      '#type' => 'details',
      '#title' => $this->t('Purchase Items'),
      '#open' => TRUE,
      'content' => [
        '#type' => 'inline_template',
        '#template' => '
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="mb-0">Purchase Order Items</h5>
            <button type="button" class="btn btn-primary btn-sm po-add-item">
              <i class="bi bi-plus-lg"></i> Add Item
            </button>
          </div>
          <div id="po-items-wrapper" class="border rounded p-2 bg-light">
            <div class="text-muted">Loading items...</div>
          </div>

          <!-- Modal -->
          <div id="po-item-modal" class="modal fade" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-dialog-centered" role="document">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 id="po-item-modal-title" class="modal-title">Add Item</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                  <input type="hidden" name="item_id">
                  <input type="hidden" id="po-inventory-nid" name="inventory_nid">

                  <div class="mb-3">
                    <label class="form-label">Inventory Item</label>
                    <input type="text" name="item_reference" class="form-control inventory-autocomplete" placeholder="Search Inventory Item">
                  </div>

                  <div class="row g-2">
                    <div class="col">
                      <label class="form-label">Rate</label>
                      <input type="number" name="rate" class="form-control">
                    </div>
                    <div class="col">
                      <label class="form-label">Quantity</label>
                      <input type="number" name="quantity" class="form-control">
                    </div>
                    <div class="col">
                      <label class="form-label">Total</label>
                      <input type="number" name="total" class="form-control" readonly>
                    </div>
                  </div>

                  <div class="mb-3 mt-3">
                    <label class="form-label">Remarks</label>
                    <textarea name="remarks" class="form-control"></textarea>
                  </div>
                </div>
                <div class="modal-footer">
                  <button id="po-item-cancel" type="button" class="btn btn-secondary">Cancel</button>
                  <button id="po-item-save" type="button" class="btn btn-primary">Save</button>
                </div>
              </div>
            </div>
          </div>
        ',
      ],
    ];

    // Summary section.
    $form['po_summary'] = [
      '#type' => 'details',
      '#title' => $this->t('Purchase Summary'),
      '#open' => TRUE,
    ];
    $form['po_summary']['field_subtotal_amount'] = [
      '#type' => 'number',
      '#title' => $this->t('Subtotal'),
      '#step' => '0.01',
      '#default_value' => $po->get('field_subtotal_amount')->value ?? 0,
      '#attributes' => [
        'readonly' => 'readonly',
        'style' => 'background:#f3f3f3; cursor:not-allowed;',
      ],
    ];
    $form['po_summary']['field_tax_amount'] = [
      '#type' => 'number',
      '#title' => $this->t('Tax'),
      '#step' => '0.01',
      '#default_value' => $po->get('field_tax_amount')->value ?? 0,
      '#attributes' => [
        'readonly' => 'readonly',
        'style' => 'background:#f3f3f3; cursor:not-allowed;',
      ],
    ];
    $form['po_summary']['field_total_amount'] = [
      '#type' => 'number',
      '#title' => $this->t('Total Amount'),
      '#step' => '0.01',
      '#default_value' => $po->get('field_total_amount')->value ?? 0,
      '#attributes' => [
        'readonly' => 'readonly',
        'style' => 'background:#f3f3f3; cursor:not-allowed;',
      ],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Purchase Order'),
      '#attributes' => ['class' => ['btn', 'btn-primary']],
    ];

    $form['#attached']['library'][] = 'stitchlyn_vendor/purchase_order_edit';
    $form['#attached']['library'][] = 'stitchlyn_vendor/vendor_info';
    $form['#submit'][] = [$this, 'submitForm'];
    $form['#action'] = \Drupal::request()->getRequestUri();
    $form['#method'] = 'post';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $nid = $form['#attributes']['data-po-nid'];
    $po = Node::load($nid);

    if (!$po || $po->bundle() !== 'purchase_order') {
      $this->messenger()->addError($this->t('Invalid Purchase Order.'));
      return;
    }

    // Start DB transaction for rollback safety.
    $transaction = Database::getConnection()->startTransaction();
    try {
      $title            = $form_state->getValue('title');
      $vendor_id        = $form_state->getValue('field_vendor');
      $date_of_purchase = $form_state->getValue('field_date_of_purchase');
      $status_tid       = $form_state->getValue('order_status');
      $payment_tid      = $form_state->getValue('payment_status');
      $body_data        = $form_state->getValue('body');
      $subtotal         = (float) $form_state->getValue('field_subtotal_amount');
      $tax              = (float) $form_state->getValue('field_tax_amount');
      $total            = (float) $form_state->getValue('field_total_amount');

      $po->setTitle($title ?: 'PO-' . $nid);
      $po->set('field_vendor', is_numeric($vendor_id) ? $vendor_id : NULL);
      $po->set('field_date_of_purchase', $date_of_purchase ?: NULL);
      $po->set('field_purchase_order_status', is_numeric($status_tid) ? $status_tid : NULL);
      $po->set('field_payment_status', is_numeric($payment_tid) ? $payment_tid : NULL);

      if (is_array($body_data) && isset($body_data['value'])) {
        $po->set('body', $body_data);
      }

      $po->set('field_subtotal_amount', $subtotal);
      $po->set('field_tax_amount', $tax);
      $po->set('field_total_amount', $total);

      $po->save();

      $this->messenger()->addMessage($this->t('Purchase Order updated successfully.'));

      // -----------------------------------------------------------------------
      // 2️⃣ FETCH AND UPDATE PURCHASE ORDER ITEMS
      // -----------------------------------------------------------------------
      $connection = \Drupal::database();
      $query = $connection->select('node_field_data', 'n');
      $query->fields('n', ['nid']);
      $query->condition('n.type', 'purchase_order_items');
      $query->join('node__field_purchase_order', 'po', 'po.entity_id = n.nid');
      $query->condition('po.field_purchase_order_target_id', $nid);
      $item_ids = $query->execute()->fetchCol();

      $entity_manager = \Drupal::entityTypeManager()->getStorage('node');

      foreach ($item_ids as $item_nid) {
        $item_node = $entity_manager->load($item_nid);
        if ($item_node) {
          $rate = (float) $item_node->get('field_item_rate')->value ?? 0;
          $qty = (float) $item_node->get('field_quantity')->value ?? 0;
          $item_node->set('field_total_amount', $rate * $qty);
          $item_node->save();
        }
      }

      // -----------------------------------------------------------------------
      // 3️⃣ IF STATUS = "Stock Updated" → CREATE LOG + UPDATE INVENTORY
      // -----------------------------------------------------------------------
      $status_term = \Drupal::entityTypeManager()
        ->getStorage('taxonomy_term')
        ->load($po->get('field_purchase_order_status')->target_id);

      $status_name = $status_term ? strtolower(trim($status_term->label())) : '';

      if ($status_name === 'stock updated') {
        foreach ($item_ids as $item_nid) {
          $item_node = $entity_manager->load($item_nid);
          if ($item_node) {
            $inventory_item_nid = $item_node->get('field_item_reference')->target_id ?? NULL;
            $quantity = (float) $item_node->get('field_quantity')->value ?? 0;

            if ($inventory_item_nid) {
              // ✅ Create Inventory Transaction Log
              $log_node = Node::create([
                'type' => 'inventory_transaction_log',
                'title' => 'log-po-' . $nid . '-' . $inventory_item_nid,
                'field_inventory_item' => $inventory_item_nid,
                'field_purchase_order' => $nid,
                'field_order_type' => self::getTaxonomyTermId('order_type', 'Purchase Order'),
                'field_transaction_type' => self::getTaxonomyTermId('transaction_type', 'In'),
                'field_quantity' => $quantity,
                'body' => [
                  'value' => 'Stock auto-updated from Purchase Order #' . $nid,
                  'format' => 'basic_html',
                ],
                'status' => 1,
              ]);
              $log_node->save();

              // ✅ Update Inventory Item Stock
              $inventory_node = Node::load($inventory_item_nid);
              if ($inventory_node && $inventory_node->bundle() === 'inventory_item') {
                $current_stock = (float) $inventory_node->get('field_opening_stock')->value ?? 0;
                $inventory_node->set('field_opening_stock', $current_stock + $quantity);
                $inventory_node->save();
              }
            }
          }
        }
      }

      $this->messenger()->addStatus($this->t('Purchase Order and related records updated successfully.'));
    }
    catch (\Exception $e) {
      $transaction->rollBack();
      \Drupal::logger('stitchlyn_vendor')->error($e->getMessage());
      $this->messenger()->addError($this->t('Error occurred while saving Purchase Order. All changes were rolled back.'));
    }

    $form_state->setRedirect('<current>');
  }

  /**
   * Utility: Get taxonomy term ID by vocabulary and name.
   */
  private static function getTaxonomyTermId($vocabulary_machine_name, $term_name) {
    $query = \Drupal::entityQuery('taxonomy_term')
      ->condition('vid', strtolower($vocabulary_machine_name))
      ->condition('name', $term_name)
      ->accessCheck(FALSE)
      ->range(0, 1);
    $ids = $query->execute();
    return $ids ? reset($ids) : NULL;
  }

}