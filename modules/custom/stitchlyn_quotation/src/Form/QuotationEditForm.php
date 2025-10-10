<?php

namespace Drupal\stitchlyn_quotation\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;

/**
 * Custom quotation edit form.
 */
class QuotationEditForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'stitchlyn_quotation_edit_form';
  }

  /**
   * Page title callback.
   */
  public static function title(NodeInterface $node) {
    return 'Edit Quotation #' . $node->get('field_quotation_number')->value;
  }

  /**
   * {@inheritdoc}
   */
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
        'type' => 'button',
      ],
      '#limit_validation_errors' => [],
      '#ajax' => FALSE,
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

    // Subtotal (readonly + hidden mirror)
    $form['totals']['field_subtotal_amount'] = [
      '#type' => 'number',
      '#title' => $this->t('Subtotal'),
      '#default_value' => $totals['subtotal'],
      '#attributes' => ['readonly' => 'readonly'],
    ];
    $form['totals']['hidden_subtotal'] = [
      '#type' => 'hidden',
      '#value' => $totals['subtotal'],
      '#attributes' => ['id' => 'hidden-subtotal'],
    ];

    $form['totals']['field_discount'] = [
      '#type' => 'number',
      '#title' => $this->t('Discount'),
      '#default_value' => $node->get('field_discount')->value ?? 0,
      '#step' => 0.01,
    ];

    // Tax (readonly + hidden mirror)
    $form['totals']['field_tax_amount'] = [
      '#type' => 'number',
      '#title' => $this->t('Tax'),
      '#default_value' => $totals['tax'],
      '#attributes' => ['readonly' => 'readonly'],
    ];
    $form['totals']['hidden_tax'] = [
      '#type' => 'hidden',
      '#value' => $totals['tax'],
      '#attributes' => ['id' => 'hidden-tax'],
    ];

    // Total (readonly + hidden mirror)
    $form['totals']['field_total_amount'] = [
      '#type' => 'number',
      '#title' => $this->t('Total'),
      '#default_value' => $totals['total'],
      '#attributes' => ['readonly' => 'readonly'],
    ];
    $form['totals']['hidden_total'] = [
      '#type' => 'hidden',
      '#value' => $totals['total'],
      '#attributes' => ['id' => 'hidden-total'],
    ];

    // ========== Actions ==========
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Quotation'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\node\Entity\Node $node */
    $node = $form_state->get('node');
    $values = $form_state->getValues();

    // Basic fields
    $node->setTitle($values['title']);
    $node->set('field_customer_reference', $values['field_customer_reference']);
    $node->set('field_quotation_date', $values['field_quotation_date']);
    $node->set('body', ['value' => $values['body'], 'format' => 'basic_html']);
    $node->set('field_discount', $values['field_discount']);

    // // Fetch hidden totals (populated by JS)
    // $subtotal = (float) $form_state->getValue('hidden_subtotal');
    // $tax = (float) $form_state->getValue('hidden_tax');
    // $total = (float) $form_state->getValue('hidden_total');

    // // Fallback: if JS didn’t run, recompute from backend
    // if (!$subtotal) {
    //   $helper = \Drupal::service('stitchlyn_quotation.helper');
    //   $computed = $helper->computeTotals($node->id());
    //   $subtotal = (float) $computed['subtotal'];
    //   $config = \Drupal::config('stitchlyn_basic.erp_settings');
    //   $tax_rate = (float) ($config->get('tax_percentage') ?? 18);
    //   $discount = (float) $values['field_discount'];
    //   $tax = ($subtotal - $discount) * ($tax_rate / 100);
    //   $total = $subtotal - $discount + $tax;
    // }

    $subtotal = $values['field_subtotal_amount'];
    $tax = $values['field_tax_amount'];
    $total = $values['field_total_amount'];

    // Save totals
    $node->set('field_subtotal_amount', $subtotal);
    $node->set('field_tax_amount', $tax);
    $node->set('field_total_amount', $total);

    $node->save();

    $this->messenger()->addMessage($this->t('Quotation saved successfully with updated totals.'));
  }

}