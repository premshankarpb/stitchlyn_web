<?php

namespace Drupal\stitchlyn_quotation\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;

/**
 * Custom form for editing Quotation details.
 */
class QuotationEditForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'stitchlyn_quotation_edit_form';
  }

  /**
   * Title callback.
   */
  public static function title(NodeInterface $node) {
    return 'Edit Quotation: ' . $node->label();
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    $form['quotation_info'] = [
      '#type' => 'details',
      '#title' => $this->t('Quotation Information'),
      '#open' => TRUE,
    ];

    $form['quotation_info']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Quotation Title'),
      '#default_value' => $node ? $node->label() : '',
      '#required' => TRUE,
    ];

    $form['quotation_info']['remarks'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Remarks'),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Quotation'),
      '#button_type' => 'primary',
    ];

    // Store node for submission.
    $form_state->set('node', $node);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\node\Entity\Node $node */
    $node = $form_state->get('node');
    if ($node) {
      $values = $form_state->getValues();
      $node->setTitle($values['title']);
      $node->set('body', ['value' => $values['remarks'], 'format' => 'basic_html']);
      $node->save();
      $this->messenger()->addMessage($this->t('Quotation %title has been updated.', ['%title' => $node->label()]));
    }
  }

}
