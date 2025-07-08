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
    $form['field_sku_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('SKU Code'),
      '#required' => TRUE,
    ];

    $form['field_category'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Item Category'),
      '#target_type' => 'taxonomy_term',
      '#selection_settings' => ['target_bundles' => ['item_category']],
    ];

    $form['field_inventory_type'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Inventory Type'),
      '#target_type' => 'taxonomy_term',
      '#selection_settings' => ['target_bundles' => ['inventory_type']],
    ];

    $form['field_unit_of_measure'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Unit of Measure'),
      '#target_type' => 'taxonomy_term',
      '#selection_settings' => ['target_bundles' => ['unit_measurement']],
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
      'title' => $form_state->getValue('field_sku_code'),
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