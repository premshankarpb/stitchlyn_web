<?php

namespace Drupal\stitchlyn_vendor\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;

class InventoryItemForm extends FormBase {

  public function getFormId() {
    return 'inventory_item_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['field_inv_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#required' => TRUE,
    ];

    $form['field_sku_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('SKU Code'),
      '#required' => TRUE,
    ];


    $vocabulary_1 = 'item_category';
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($vocabulary_1);
    $category_options = [];

    foreach ($terms as $term) {
      $category_options[$term->tid] = $term->name;
    }

    $form['field_category'] = [
      '#type' => 'select',
      '#title' => $this->t('Item Category'),
      '#options' => $category_options,
      '#empty_option' => $this->t('- Select a category -'),
      '#attributes' => [
        'class' => ['inv-edit-fields'],
      ],
    ];

    $vocabulary_2 = 'inventory_type';
    $terms_2 = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($vocabulary_2);
    $inv_type_options = [];

    foreach ($terms_2 as $term) {
      $inv_type_options[$term->tid] = $term->name;
    }

    $form['field_inventory_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Inventory Type'),
      '#options' => $inv_type_options,
      '#empty_option' => $this->t('- Select an Inventory type -'),
      '#attributes' => [
        'class' => ['inv-edit-fields'],
      ],
    ];

    $vocabulary_3 = 'unit_measurement';
    $terms_3 = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($vocabulary_3);
    $inv_uom_options = [];

    foreach ($terms_3 as $term) {
      $inv_uom_options[$term->tid] = $term->name;
    }

    $form['field_unit_of_measure'] = [
      '#type' => 'select',
      '#title' => $this->t('Unit of Measure'),
      '#options' => $inv_uom_options,
      '#empty_option' => $this->t('- Select a Unit of Measure -'),
      '#attributes' => [
        'class' => ['inv-edit-fields'],
      ],
    ];

    $form['field_cost_price'] = [
      '#type' => 'number',
      '#title' => $this->t('Cost Price'),
      '#step' => '0.01',
      '#required' => TRUE,
    ];

    $form['field_opening_stock'] = [
      '#type' => 'number',
      '#title' => $this->t('Opening Stock'),
      '#required' => TRUE,
    ];

    $form['field_reorder_level'] = [
      '#type' => 'number',
      '#title' => $this->t('Reorder Level'),
    ];

    $form['body'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Description'),
      '#format' => 'basic_html',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Inventory Item'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $node = Node::create([
      'type' => 'inventory_item',
      'title' => $form_state->getValue('field_inv_title'),
      'field_sku_code' => $form_state->getValue('field_sku_code'),
      'field_category' => $form_state->getValue('field_category'),
      'field_inventory_type' => $form_state->getValue('field_inventory_type'),
      'field_unit_of_measure' => $form_state->getValue('field_unit_of_measure'),
      'field_cost_price' => $form_state->getValue('field_cost_price'),
      'field_opening_stock' => $form_state->getValue('field_opening_stock'),
      'field_reorder_level' => $form_state->getValue('field_reorder_level'),
      'body' => $form_state->getValue('body'),
    ]);

    $node->save();
    $this->messenger()->addStatus($this->t('Inventory item has been saved.'));
  }
}