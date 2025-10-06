<?php

namespace Drupal\stitchlyn_quotation\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;

/**
 * Custom quotation edit form.
 */
class QuotationEditForm extends FormBase {

  public function getFormId() {
    return 'stitchlyn_quotation_edit_form';
  }

  public static function title(NodeInterface $node) {
    return 'Edit Quotation #' . $node->get('field_quotation_number')->value;
  }

  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    $form_state->set('node', $node);

    $form['quotation_details'] = [
      '#type' => 'details',
      '#title' => $this->t('Quotation Information'),
      '#open' => TRUE,
    ];

    $form['quotation_details']['field_customer_reference'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Customer Reference'),
      '#target_type' => 'user',
      '#default_value' => $node->get('field_customer_reference')->entity ?? NULL,
    ];

    $form['quotation_details']['field_discount'] = [
      '#type' => 'number',
      '#title' => $this->t('Discount'),
      '#step' => 0.01,
      '#default_value' => $node->get('field_discount')->value ?? 0,
    ];

    $form['quotation_details']['body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Remarks'),
      '#default_value' => $node->get('body')->value ?? '',
    ];

    $form['totals'] = [
      '#type' => 'details',
      '#title' => $this->t('Totals'),
      '#open' => TRUE,
    ];

    $form['totals']['field_subtotal_amount'] = [
      '#type' => 'number',
      '#title' => $this->t('Subtotal'),
      '#default_value' => $node->get('field_subtotal_amount')->value ?? 0,
      '#step' => 0.01,
    ];

    $form['totals']['field_tax_amount'] = [
      '#type' => 'number',
      '#title' => $this->t('Tax'),
      '#default_value' => $node->get('field_tax_amount')->value ?? 0,
      '#step' => 0.01,
    ];

    $form['totals']['field_total_amount'] = [
      '#type' => 'number',
      '#title' => $this->t('Total'),
      '#default_value' => $node->get('field_total_amount')->value ?? 0,
      '#step' => 0.01,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Quotation'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\node\Entity\Node $node */
    $node = $form_state->get('node');
    $values = $form_state->getValues();

    $node->set('field_customer_reference', $values['field_customer_reference']);
    $node->set('field_discount', $values['field_discount']);
    $node->set('field_subtotal_amount', $values['field_subtotal_amount']);
    $node->set('field_tax_amount', $values['field_tax_amount']);
    $node->set('field_total_amount', $values['field_total_amount']);
    $node->set('body', ['value' => $values['body'], 'format' => 'basic_html']);
    $node->save();

    $this->messenger()->addMessage($this->t('Quotation #%num has been saved.', ['%num' => $node->get('field_quotation_number')->value]));
  }

}