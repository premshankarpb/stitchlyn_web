<?php

namespace Drupal\stitchlyn_quotation\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;

class QuotationEditForm extends FormBase {

  public function getFormId() {
    return 'stitchlyn_quotation_edit_form';
  }

  public static function title(NodeInterface $node) {
    return 'Edit Quotation #' . $node->get('field_quotation_number')->value;
  }

  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    $form['#attached']['library'][] = 'stitchlyn_quotation/quotation';
    $form_state->set('node', $node);
    $quotation_id = $node->id();

    // ========== Quotation Information ==========
    $form['quotation_info'] = [
      '#type' => 'details',
      '#title' => $this->t('Quotation Information'),
      '#open' => TRUE,
    ];

    $form['quotation_info']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $node->label(),
      '#required' => TRUE,
    ];

    $form['quotation_info']['field_customer_reference'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Customer'),
      '#target_type' => 'user',
      '#default_value' => $node->get('field_customer_reference')->entity ?? NULL,
    ];

    $form['quotation_info']['field_quotation_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Date'),
      '#default_value' => $node->get('field_quotation_date')->value ?? date('Y-m-d'),
    ];

    $form['quotation_info']['body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Remarks'),
      '#default_value' => $node->get('body')->value ?? '',
    ];

    // ========== Product Section ==========
    $form['product_section'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['product-section']],
    ];

    $form['product_section']['product'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Select Product'),
      '#target_type' => 'node',
      '#selection_settings' => ['target_bundles' => ['product']],
      '#attributes' => ['id' => 'product-autocomplete'],
    ];

    $form['product_section']['add_product'] = [
      '#type' => 'button',
      '#value' => $this->t('Add Product'),
      '#attributes' => [
        'class' => ['button', 'add-product-btn'],
        'data-quotation-id' => $quotation_id,
      ],
    ];

    // ========== Line Items ==========
    $form['line_items'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'line-items-wrapper'],
      'table' => \Drupal::service('stitchlyn_quotation.helper')->renderLineItemTable($quotation_id),
    ];

    // ========== Totals ==========
    $helper = \Drupal::service('stitchlyn_quotation.helper');
    $totals = $helper->computeTotals($quotation_id);

    $form['totals'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Totals'),
    ];

    $form['totals']['field_subtotal_amount'] = [
      '#type' => 'number',
      '#title' => $this->t('Subtotal'),
      '#default_value' => $totals['subtotal'],
      '#attributes' => ['readonly' => 'readonly'],
    ];

    $form['totals']['field_discount'] = [
      '#type' => 'number',
      '#title' => $this->t('Discount'),
      '#default_value' => $node->get('field_discount')->value ?? 0,
      '#step' => 0.01,
    ];

    $form['totals']['field_tax_amount'] = [
      '#type' => 'number',
      '#title' => $this->t('Tax'),
      '#default_value' => $totals['tax'],
      '#attributes' => ['readonly' => 'readonly'],
    ];

    $form['totals']['field_total_amount'] = [
      '#type' => 'number',
      '#title' => $this->t('Total'),
      '#default_value' => $totals['total'],
      '#attributes' => ['readonly' => 'readonly'],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Quotation'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $node = $form_state->get('node');
    $v = $form_state->getValues();

    $node->setTitle($v['title']);
    $node->set('field_customer_reference', $v['field_customer_reference']);
    $node->set('field_quotation_date', $v['field_quotation_date']);
    $node->set('body', ['value' => $v['body'], 'format' => 'basic_html']);
    $node->set('field_discount', $v['field_discount']);

    $totals = \Drupal::service('stitchlyn_quotation.helper')->computeTotals($node->id());
    $node->set('field_subtotal_amount', $totals['subtotal']);
    $node->set('field_tax_amount', $totals['tax']);
    $node->set('field_total_amount', $totals['total']);
    $node->save();

    $this->messenger()->addMessage($this->t('Quotation saved successfully.'));
  }
}