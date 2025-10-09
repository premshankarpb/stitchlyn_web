<?php

namespace Drupal\stitchlyn_quotation\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Popup form for paragraph attributes.
 */
class AttributePopupForm extends FormBase {

  protected $productId;
  protected $quotationId;

  public function getFormId() {
    return 'stitchlyn_attribute_popup_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $product_id = NULL, $quotation_id = NULL) {
    $this->productId = $product_id;
    $this->quotationId = $quotation_id;

    $form['field_quantity'] = [
      '#type' => 'number',
      '#title' => $this->t('Quantity'),
      '#default_value' => 1,
      '#min' => 1,
      '#required' => TRUE,
    ];

    $form['remarks'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Remarks'),
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Item'),
      '#ajax' => [
        'callback' => '::ajaxSubmit',
      ],
    ];

    return $form;
  }

  public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
    $response = new \Drupal\Core\Ajax\AjaxResponse();

    // Create paragraph.
    $paragraph = Paragraph::create([
      'type' => 'abc',
      'field_remarks' => $form_state->getValue('remarks'),
    ]);
    $paragraph->save();

    // Create line item node.
    $product = Node::load($this->productId);
    $quotation = Node::load($this->quotationId);

    $line_item = Node::create([
      'type' => 'quatation_line_items',
      'title' => 'Item for ' . $product->label(),
      'field_product' => $this->productId,
      'field_linked_quotation' => $this->quotationId,
      'field_quantity' => $form_state->getValue('field_quantity'),
      'field_attributes' => ['target_id' => $paragraph->id(), 'target_revision_id' => $paragraph->getRevisionId()],
    ]);
    $line_item->save();

    // Close modal.
    $response->addCommand(new \Drupal\Core\Ajax\CloseModalDialogCommand());

    // Refresh table markup.
    $table_markup = \Drupal::service('stitchlyn_quotation.helper')->renderLineItemTable($this->quotationId);
    $response->addCommand(new \Drupal\Core\Ajax\HtmlCommand('#line-items-wrapper', $table_markup));

    return $response;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {}
}