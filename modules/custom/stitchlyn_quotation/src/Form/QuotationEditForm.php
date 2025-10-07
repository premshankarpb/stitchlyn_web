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
    $form_state->set('node', $node);
    $quotation_id = $node->id();

    // Product autocomplete + add button
    $form['product_section'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['inline-form']],
    ];

    $form['product_section']['product_autocomplete'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Select Product'),
      '#target_type' => 'node',
      '#selection_settings' => ['target_bundles' => ['product']],
      '#attributes' => ['id' => 'product-autocomplete'],
    ];

    $form['product_section']['add_product'] = [
      '#type' => 'button',
      '#value' => $this->t('Add Product'),
      '#ajax' => [
        'callback' => '::openAttributePopup',
        'event' => 'click',
      ],
    ];

    // Table wrapper
    // $form['line_items'] = [
    //   '#type' => 'container',
    //   '#attributes' => ['id' => 'line-items-wrapper'],
    //   'table_markup' => [
    //     '#markup' => \Drupal::service('stitchlyn_quotation.helper')->renderLineItemTable($quotation_id),
    //   ],
    // ];
    $form['line_items'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'line-items-wrapper'],
    ];

    $form['line_items']['table'] = \Drupal::service('stitchlyn_quotation.helper')->renderLineItemTable($quotation_id);


    // Totals
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

    return $form;
  }

  public function openAttributePopup(array &$form, FormStateInterface $form_state) {
    $response = new \Drupal\Core\Ajax\AjaxResponse();
    $product_nid = $form_state->getValue('product_autocomplete');
    $quotation_nid = $form_state->get('node')->id();

    if ($product_nid) {
      $url = '/quotation/popup/attributes?product_nid=' . $product_nid . '&quotation_nid=' . $quotation_nid;
      $response->addCommand(new \Drupal\Core\Ajax\InvokeCommand(NULL, 'drupalAjax', [[
        'url' => $url,
      ]]));
    }
    return $response;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {}
}