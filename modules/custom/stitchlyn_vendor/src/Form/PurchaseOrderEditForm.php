<?php

namespace Drupal\stitchlyn_vendor\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\taxonomy\Entity\Term;

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
          <div id="po-item-modal" class="po-modal">
            <div class="po-modal-content">
              <h4 id="po-item-modal-title" class="mb-3">Add Item</h4>
              <form id="po-item-form">
                <input type="hidden" name="item_id" value="">
                <div class="mb-2">
                  <label>Inventory Item</label>
                  <input
                    type="text"
                    name="item_reference"
                    id="po-item-reference"
                    class="form-control inventory-autocomplete"
                    placeholder="Search Inventory Item"
                    data-autocomplete-path="/inventory-item/autocomplete"
                  />
                  <input type="hidden" id="po-inventory-nid" name="inventory_nid" value="">
                </div>
                <div class="row mb-2">
                  <div class="col-md-4">
                    <label>Rate</label>
                    <input type="number" name="rate" class="form-control" value="0" step="0.01">
                  </div>
                  <div class="col-md-4">
                    <label>Quantity</label>
                    <input type="number" name="quantity" class="form-control" value="1" step="0.01">
                  </div>
                  <div class="col-md-4">
                    <label>Total</label>
                    <input type="number" name="total" class="form-control" readonly>
                  </div>
                </div>
                <div class="mb-3">
                  <label>Remarks</label>
                  <textarea name="remarks" class="form-control" rows="2"></textarea>
                </div>
                <div class="text-end">
                  <button type="button" id="po-item-cancel" class="btn btn-secondary me-2">Cancel</button>
                  <button type="button" id="po-item-save" class="btn btn-primary">Save</button>
                </div>
              </form>
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
    ];
    $form['po_summary']['field_tax_amount'] = [
      '#type' => 'number',
      '#title' => $this->t('Tax'),
      '#step' => '0.01',
      '#default_value' => $po->get('field_tax_amount')->value ?? 0,
    ];
    $form['po_summary']['field_total_amount'] = [
      '#type' => 'number',
      '#title' => $this->t('Total Amount'),
      '#step' => '0.01',
      '#default_value' => $po->get('field_total_amount')->value ?? 0,
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Purchase Order'),
    ];

    $form['#attached']['library'][] = 'stitchlyn_vendor/purchase_order_edit';
    $form['#attached']['library'][] = 'stitchlyn_vendor/vendor_info';

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

    // Update core fields.
    $po->setTitle($form_state->getValue(['po_details', 'title']));
    $po->set('field_vendor', $form_state->getValue(['po_details', 'field_vendor']) ?: NULL);
    $po->set('field_date_of_purchase', $form_state->getValue(['po_details', 'field_date_of_purchase']) ?: NULL);
    $po->set('field_purchase_order_status', $form_state->getValue(['po_details', 'field_purchase_order_status']) ?: NULL);

    // Body.
    $body = $form_state->getValue(['po_details', 'body']);
    $po->set('body', $body);

    // Summary fields.
    $subtotal = (float) $form_state->getValue(['summary', 'field_subtotal_amount']);
    $tax = (float) $form_state->getValue(['summary', 'field_tax_amount']);
    $total = (float) $form_state->getValue(['summary', 'field_total_amount']);

    $po->set('field_subtotal_amount', $subtotal);
    $po->set('field_tax_amount', $tax);
    $po->set('field_total_amount', $total);
    $po->set('field_payment_status', $form_state->getValue(['summary', 'field_payment_status']) ?: NULL);

    $po->save();
    $this->messenger()->addStatus($this->t('Purchase Order saved successfully.'));
    $form_state->setRedirect('<current>');
  }

}