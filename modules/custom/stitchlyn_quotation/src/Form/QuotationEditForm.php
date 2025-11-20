<?php

namespace Drupal\stitchlyn_quotation\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Render\Markup;
use Drupal\taxonomy\Entity\Term;

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

    $form['quotation_info']['field_expected_due_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Due Date'),
      '#default_value' => $node->get('field_expected_due_date')->value ?? date('Y-m-d'),
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

    // ==================== Status & Payment ====================
    $form['quotation_workflow'] = [
      '#type' => 'details',
      '#title' => $this->t('Status & Payment'),
      '#open' => TRUE,
    ];

    // Resolve workflow and available states.
    $moderation_info = \Drupal::service('content_moderation.moderation_information');

    $current_state_label = $this->t('Not moderated');
    $state_options = [];
    $current_state_id = '';

    if ($moderation_info->isModeratedEntity($node)) {
      /** @var \Drupal\workflows\WorkflowInterface $workflow */
      $workflow = $moderation_info->getWorkflowForEntity($node);
      if ($workflow) {
        $type = $workflow->getTypePlugin();

        // Load all states from this workflow.
        $states = $type->getStates();
        foreach ($states as $sid => $state) {
          $state_options[$sid] = $state->label();
        }

        // Current state details.
        $current_state_id = $node->hasField('moderation_state') ? (string) $node->get('moderation_state')->value : '';
        if ($current_state_id !== '' && isset($states[$current_state_id])) {
          $current_state_label = $states[$current_state_id]->label();
        }
      }
    }

    // Display current status (read-only).
    $form['quotation_workflow']['current_status'] = [
      '#type' => 'item',
      '#title' => $this->t('Current Status'),
      '#markup' => '<strong>' . $current_state_label . '</strong>',
    ];

    // Allow direct state selection (dropdown of all workflow states).
    $form['quotation_workflow']['moderation_state'] = [
      '#type' => 'select',
      '#title' => $this->t('Change Status'),
      '#options' => $state_options,
      '#default_value' => $current_state_id,
      '#empty_option' => $this->t('- Select -'),
      '#description' => $this->t('Directly assign a new workflow state.'),
    ];

    // Payment status dropdown from vocabulary.
    $payment_options = [];
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('payment_status', 0, 1, TRUE);
    foreach ($terms as $term) {
      $payment_options[$term->id()] = $term->label();
    }

    $form['quotation_workflow']['field_payment_status'] = [
      '#type' => 'select',
      '#title' => $this->t('Payment Status'),
      '#options' => $payment_options,
      '#default_value' => $node->hasField('field_payment_status') && !$node->get('field_payment_status')->isEmpty()
        ? (int) $node->get('field_payment_status')->target_id
        : NULL,
      '#empty_option' => $this->t('- Select -'),
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
    $node->set('field_expected_due_date', $values['field_expected_due_date']);
    $node->set('body', ['value' => $values['body'], 'format' => 'basic_html']);
    $node->set('field_discount', $values['field_discount']);

    $subtotal = $values['field_subtotal_amount'];
    $tax = $values['field_tax_amount'];
    $total = $values['field_total_amount'];

    // Save totals
    $node->set('field_subtotal_amount', $subtotal);
    $node->set('field_tax_amount', $tax);
    $node->set('field_total_amount', $total);

    // --- Payment status update ---
    if ($node->hasField('field_payment_status')) {
      $payment_tid = (int) ($values['field_payment_status'] ?? 0);
      $node->set('field_payment_status', $payment_tid ? ['target_id' => $payment_tid] : NULL);
    }

    // --- Directly update moderation state (selected workflow state) ---
    if ($node->hasField('moderation_state') && !empty($values['moderation_state'])) {
      $node->set('moderation_state', $values['moderation_state']);
    }

       // Get all payment records for this order.
    $pay_query = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', 'payment_record')
      ->condition('status', 1)
      ->condition('field_reference_order', $node->id());
    $payment_ids = $pay_query->execute();

    $total_collected = 0;
    if (!empty($payment_ids)) {
      $payments = Node::loadMultiple($payment_ids);
      foreach ($payments as $p) {
        $amount = (float) ($p->get('field_amount_paid')->value ?? 0);
        $total_collected += $amount;
      }
    }

    // Fetch total order amount (field_total_amount).
    $pending_amount = max($total - $total_collected, 0);

    // Update order fields.
    $node->set('field_amount_collected', $total_collected);
    $node->set('field_amount_pending', $pending_amount);

    $node->save();

    // Get moderation state from form or from node object.
    $moderation_state = NULL;

    // If moderation field exists in form submission, use that.
    if ($form_state->hasValue('moderation_state')) {
      $moderation_state = $form_state->getValue('moderation_state');
    }
    else {
      // Else fallback: load it from the current node being edited.
      /** @var \Drupal\node\Entity\Node $node */
      $node = $form_state->getFormObject()->getEntity();
      if ($node->hasField('moderation_state') && !$node->get('moderation_state')->isEmpty()) {
        $moderation_state = $node->get('moderation_state')->value;
      }
    }

    // Normalize capitalization just in case.
    $moderation_state = strtolower(trim($moderation_state));


    if ($moderation_state == 'accepted') {
      // Get the quotation node ID.
      $quotation_id = $form_state->getValue('nid') ?? $form_state->getValue('node_id') ?? NULL;

      if ($quotation_id) {
        // Fetch all inventory logs linked to this quotation.
        $log_ids = \Drupal::entityQuery('node')
          ->condition('type', 'inventory_transaction_log')
          ->condition('field_purchase_order', $quotation_id)
          ->accessCheck(FALSE)
          ->execute();

        if (!empty($log_ids)) {
          $logs = \Drupal\node\Entity\Node::loadMultiple($log_ids);

          foreach ($logs as $log) {
            /** @var \Drupal\node\Entity\Node $log */
            $inv_target_id = $log->get('field_inventory_item')->target_id ?? NULL;
            $used_qty      = (float) ($log->get('field_quantity')->value ?? 0);

            if ($inv_target_id && $used_qty > 0) {
              $inventory_item = \Drupal\node\Entity\Node::load($inv_target_id);

              if ($inventory_item && $inventory_item->bundle() === 'inventory_item') {
                $opening_stock = (float) ($inventory_item->get('field_opening_stock')->value ?? 0);
                $new_stock     = max(0, $opening_stock - $used_qty);

                // --- Create new revision and comment ---
                $inventory_item->setNewRevision(TRUE);
                $inventory_item->setRevisionUserId(\Drupal::currentUser()->id());
                $inventory_item->setRevisionCreationTime(REQUEST_TIME);
                $inventory_item->setRevisionLogMessage(
                  'Stock reduced by ' . $used_qty . ' due to Quotation ID #' . $quotation_id .
                  ' (previous stock: ' . $opening_stock . ', new stock: ' . $new_stock . ').'
                );

                // --- Update stock field and save ---
                $inventory_item->set('field_opening_stock', $new_stock);
                $inventory_item->save();
              }
            }
          }
        }
      }
    }

    $this->messenger()->addMessage($this->t('Quotation saved successfully with updated totals.'));
  }

}