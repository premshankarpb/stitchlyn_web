<?php

namespace Drupal\stitchlyn_vendor\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;

/**
 * Edit/Add form for Purchase Orders (and their items).
 */
class PurchaseOrderEditForm extends FormBase {

  public function getFormId() {
    return 'stitchlyn_purchase_order_edit_form';
  }

  /**
   * Build rows array from form values for consistent state handling.
   */
  private function collectRowsFromForm(FormStateInterface $form_state): array {
    $rows = [];
    $items = $form_state->getValue('items') ?? [];
    foreach ($items as $r) {
      $item_id = isset($r['item_id']) ? (int) $r['item_id'] : NULL;
      $item = isset($r['item']) ? (int) $r['item'] : NULL;
      $rate = isset($r['rate']) ? (float) $r['rate'] : 0.0;
      $qty  = isset($r['quantity']) ? (float) $r['quantity'] : 0.0;
      $total = $rate * $qty;
      $rows[] = [
        'id'       => $item_id ?: NULL,
        'item'     => $item ?: NULL,
        'rate'     => $rate,
        'quantity' => $qty,
        'total'    => $total,
      ];
    }
    return $rows;
  }

  /**
   * Load existing purchase order items from DB as rows array.
   */
  private function loadPurchaseOrderItems(int $po_id): array {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $linked = $storage->loadByProperties([
      'type' => 'purchase_order_items',
      'field_purchase_order' => $po_id,
    ]);
    $rows = [];
    /** @var \Drupal\node\Entity\Node $n */
    foreach ($linked as $n) {
      $rows[] = [
        'id'       => $n->id(),
        'item'     => (int) ($n->get('field_item_reference')->target_id ?? NULL),
        'rate'     => (float) ($n->get('field_item_rate')->value ?? 0),
        'quantity' => (float) ($n->get('field_quantity')->value ?? 0),
        'total'    => (float) ($n->get('field_total_amount')->value ?? 0),
      ];
    }
    // Keep a predictable order.
    usort($rows, fn($a, $b) => ($a['id'] ?? 0) <=> ($b['id'] ?? 0));
    return $rows;
  }

  public function buildForm(array $form, FormStateInterface $form_state, Node $node = NULL) {
    $form_state->set('node', $node);

    // Initialize rows state on first build.
    if (!$form_state->has('rows')) {
      $initial_rows = [];
      if ($node && !$node->isNew()) {
        $initial_rows = $this->loadPurchaseOrderItems((int) $node->id());
      }
      if (empty($initial_rows)) {
        $initial_rows = [['id' => NULL, 'item' => NULL, 'rate' => 0, 'quantity' => 0, 'total' => 0]];
      }
      $form_state->set('rows', $initial_rows);
    }

    // If there is submitted input in this rebuild, refresh rows from values.
    if ($form_state->getTriggeringElement()) {
      $rows_from_values = $this->collectRowsFromForm($form_state);
      if (!empty($rows_from_values)) {
        $form_state->set('rows', $rows_from_values);
      }
    }

    $rows = $form_state->get('rows');

    // Stable root wrapper (used by AJAX replaces).
    $form['#prefix'] = '<div id="po-form-wrapper">';
    $form['#suffix'] = '</div>';

    // -------------------- GENERAL INFO --------------------
    $form['general_info'] = [
      '#type'  => 'details',
      '#title' => $this->t('Purchase Order Details'),
      '#open'  => TRUE,
    ];

    // Auto title from field_po_number. Show it as disabled (source of truth is saved in submit).
    $po_number = '';
    if ($node && !$node->isNew()) {
      $po_number = (string) ($node->get('field_po_number')->value ?? '');
    }
    $auto_title = 'Purchase-Order-' . $po_number;

    $form['general_info']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $node && $node->getTitle() ? $node->getTitle() : $auto_title,
      '#required' => TRUE,
      '#disabled' => TRUE,
      '#description' => $this->t('Auto-generated from PO Number.'),
    ];

    $form['general_info']['vendor'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Vendor'),
      '#target_type' => 'user',
      '#default_value' => ($node && $node->get('field_vendor')->entity) ? $node->get('field_vendor')->entity : NULL,
      '#attributes' => ['class' => ['vendor-autocomplete']],
    ];

    $form['general_info']['date_of_purchase'] = [
      '#type' => 'date',
      '#title' => $this->t('Date of Purchase'),
      '#default_value' => $node ? ($node->get('field_date_of_purchase')->value ?? '') : '',
    ];

    // Payment status options.
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('payment_status');
    $options = [];
    foreach ($terms as $term) {
      $options[$term->tid] = $term->name;
    }

    $form['general_info']['payment_status'] = [
      '#type' => 'select',
      '#title' => $this->t('Payment Status'),
      '#options' => $options,
      '#default_value' => $node ? ($node->get('field_payment_status')->target_id ?? '') : '',
      '#empty_option' => $this->t('- Select -'),
    ];

    $form['general_info']['remarks'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Remarks'),
      '#default_value' => $node ? ($node->get('body')->value ?? '') : '',
    ];

    // -------------------- ITEMS --------------------
    $form['items_section'] = [
      '#type'  => 'details',
      '#title' => $this->t('Purchase Items'),
      '#open'  => TRUE,
    ];

    $form['items_section']['items_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'items-wrapper'],
    ];

    $form['items_section']['items_wrapper']['items'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Item'),
        $this->t('Rate'),
        $this->t('Quantity'),
        $this->t('Total'),
        $this->t('Action'),
      ],
    ];

    // Global tax.
    $config = $this->config('stitchlyn_basic.erp_settings');
    $tax_percentage = (float) ($config->get('tax_percentage') ?? 0);

    // Build table rows from state $rows.
    foreach ($rows as $i => $r) {
      $item_entity = NULL;
      if (!empty($r['item'])) {
        $item_entity = \Drupal::entityTypeManager()->getStorage('node')->load((int) $r['item']);
      }

      $form['items_section']['items_wrapper']['items'][$i]['item_id'] = [
        '#type' => 'hidden',
        '#value' => $r['id'] ?? NULL,
      ];

      $form['items_section']['items_wrapper']['items'][$i]['item'] = [
        '#type' => 'entity_autocomplete',
        '#target_type' => 'node',
        '#selection_settings' => ['target_bundles' => ['inventory_item']],
        '#default_value' => $item_entity,
        '#attributes' => ['class' => ['item-autocomplete']],
      ];

      $form['items_section']['items_wrapper']['items'][$i]['rate'] = [
        '#type' => 'number',
        '#step' => 0.01,
        '#value' => (float) $r['rate'],
        '#ajax' => [
          'callback' => '::ajaxRecalculate',
          'event' => 'change',     // fires on blur / change
          'wrapper' => 'po-form-wrapper',
        ],
      ];

      $form['items_section']['items_wrapper']['items'][$i]['quantity'] = [
        '#type' => 'number',
        '#step' => 1,
        '#value' => (float) $r['quantity'],
        '#ajax' => [
          'callback' => '::ajaxRecalculate',
          'event' => 'change',
          'wrapper' => 'po-form-wrapper',
        ],
      ];

      $form['items_section']['items_wrapper']['items'][$i]['total'] = [
        '#type' => 'number',
        '#value' => (float) $r['total'],
        '#attributes' => ['readonly' => 'readonly'],
      ];

      $form['items_section']['items_wrapper']['items'][$i]['remove'] = [
        '#type' => 'submit',
        '#value' => $this->t('X'),
        '#name' => 'remove_' . $i,
        '#limit_validation_errors' => [],
        '#submit' => ['::removeItem'],
        '#ajax' => [
          'callback' => '::refreshItems',
          'wrapper' => 'items-wrapper',
        ],
      ];
    }

    $form['items_section']['add_item'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Item'),
      '#limit_validation_errors' => [],
      '#submit' => ['::addItem'],
      '#ajax' => [
        'callback' => '::refreshItems',
        'wrapper' => 'items-wrapper',
      ],
    ];

    // -------------------- SUMMARY --------------------
    $subtotal = 0.0;
    foreach ($rows as $r) {
      $subtotal += (float) $r['total'];
    }
    $tax_total = ($subtotal * $tax_percentage) / 100.0;
    $grand_total = $subtotal + $tax_total;

    $form['summary_section'] = [
      '#type'  => 'details',
      '#title' => $this->t('Purchase Summary'),
      '#open'  => TRUE,
      '#attributes' => ['id' => 'summary-wrapper'],
    ];

    $form['summary_section']['subtotal'] = [
      '#type' => 'number',
      '#title' => $this->t('Subtotal'),
      '#value' => $subtotal,
      '#attributes' => ['readonly' => 'readonly'],
    ];

    $form['summary_section']['tax_total'] = [
      '#type' => 'number',
      '#title' => $this->t('Tax @ @tax%', ['@tax' => $tax_percentage]),
      '#value' => $tax_total,
      '#attributes' => ['readonly' => 'readonly'],
    ];

    $form['summary_section']['grand_total'] = [
      '#type' => 'number',
      '#title' => $this->t('Total Amount'),
      '#value' => $grand_total,
      '#attributes' => ['readonly' => 'readonly'],
    ];

    // -------------------- ACTIONS --------------------
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Purchase Order'),
      '#button_type' => 'primary',
    ];

    // Attach JS for vendor card + item rate autofill.
    $form['#attached']['library'][] = 'stitchlyn_vendor/vendor_info';
    $form['#attached']['drupalSettings']['stitchlyn_vendor']['vendorInfoUrl'] = '/stitchlyn/vendor/info';
    $form['#attached']['drupalSettings']['stitchlyn_vendor']['itemInfoUrl'] = '/stitchlyn/item/info';

    return $form;
  }

  /**
   * Recalculate totals from current values and rebuild the two wrappers.
   */
  public function ajaxRecalculate(array &$form, FormStateInterface $form_state) {
    // Refresh rows from posted values, update totals & summary for this rebuild.
    $rows = $this->collectRowsFromForm($form_state);
    $form_state->set('rows', $rows);

    $config = $this->config('stitchlyn_basic.erp_settings');
    $tax_percentage = (float) ($config->get('tax_percentage') ?? 0);

    $subtotal = 0.0;
    foreach ($rows as $idx => $r) {
      $total = (float) $r['rate'] * (float) $r['quantity'];
      $subtotal += $total;
      // Push computed total back into form element so the rendered element shows updated value.
      if (isset($form['items_section']['items_wrapper']['items'][$idx]['total'])) {
        $form['items_section']['items_wrapper']['items'][$idx]['total']['#value'] = $total;
      }
    }
    $tax_total   = ($subtotal * $tax_percentage) / 100.0;
    $grand_total = $subtotal + $tax_total;

    $form['summary_section']['subtotal']['#value']    = $subtotal;
    $form['summary_section']['tax_total']['#value']   = $tax_total;
    $form['summary_section']['grand_total']['#value'] = $grand_total;

    // Return both sections to update in one response.
    $response = new AjaxResponse();
    $response->addCommand(new HtmlCommand('#items-wrapper', render($form['items_section']['items_wrapper'])));
    $response->addCommand(new HtmlCommand('#summary-wrapper', render($form['summary_section'])));
    return $response;
  }

  /**
   * AJAX: refresh items table only (after add/remove).
   */
  public function refreshItems(array &$form, FormStateInterface $form_state) {
    return $form['items_section']['items_wrapper'];
  }

  /**
   * Add an empty row at the end.
   */
  public function addItem(array &$form, FormStateInterface $form_state) {
    $rows = $this->collectRowsFromForm($form_state);
    $rows[] = ['id' => NULL, 'item' => NULL, 'rate' => 0, 'quantity' => 0, 'total' => 0];
    $form_state->set('rows', array_values($rows));
    $form_state->setRebuild(TRUE);
  }

  /**
   * Remove the row which contains the clicked remove button.
   */
  public function removeItem(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    // The remove button name is 'remove_<index>'.
    $name = $trigger['#name'] ?? '';
    $index = NULL;
    if (preg_match('/^remove_(\d+)$/', $name, $m)) {
      $index = (int) $m[1];
    }

    $rows = $this->collectRowsFromForm($form_state);
    if ($index !== NULL && isset($rows[$index])) {
      unset($rows[$index]);
      $rows = array_values($rows); // reindex to keep table tidy
      $form_state->set('rows', $rows);
    }
    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit: save PO node + item rows.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\node\Entity\Node $node */
    $node = $form_state->get('node');
    if (!$node instanceof Node) {
      $this->messenger()->addError($this->t('Invalid purchase order node.'));
      return;
    }

    // Values (safe getters for nested containers).
    $vendor    = $form_state->getValue(['general_info', 'vendor']) ?: NULL;
    $dop       = $form_state->getValue(['general_info', 'date_of_purchase']) ?: NULL;
    $status    = $form_state->getValue(['general_info', 'payment_status']) ?: NULL;
    $remarks   = $form_state->getValue(['general_info', 'remarks']) ?? '';
    $subtotal  = (float) ($form_state->getValue(['summary_section', 'subtotal']) ?? 0);
    $tax_total = (float) ($form_state->getValue(['summary_section', 'tax_total']) ?? 0);
    $grand     = (float) ($form_state->getValue(['summary_section', 'grand_total']) ?? 0);

    // Auto-title from PO number.
    $po_number = (string) ($node->get('field_po_number')->value ?? '');
    $auto_title = 'Purchase-Order-' . $po_number;

    // Save main node.
    $node->setTitle($auto_title);
    $node->set('field_vendor', $vendor);
    $node->set('field_date_of_purchase', $dop);
    $node->set('field_payment_status', $status ?: NULL);
    $node->set('body', ['value' => $remarks, 'format' => 'basic_html']);
    $node->set('field_subtotal_amount', $subtotal);
    $node->set('field_tax_amount', $tax_total);
    $node->set('field_total_amount', $grand);
    $node->save();

    // Save items.
    $this->savePurchaseOrderItems((int) $node->id(), $form_state);

    $this->messenger()->addStatus($this->t('Purchase Order @num saved successfully.', ['@num' => $po_number]));
  }

  /**
   * Persist purchase_order_items list: add/update/delete.
   */
  private function savePurchaseOrderItems(int $po_id, FormStateInterface $form_state): void {
    $storage = \Drupal::entityTypeManager()->getStorage('node');

    // Existing items by id for diff.
    $existing = $storage->loadByProperties([
      'type' => 'purchase_order_items',
      'field_purchase_order' => $po_id,
    ]);
    $existing_by_id = [];
    /** @var \Drupal\node\Entity\Node $n */
    foreach ($existing as $n) {
      $existing_by_id[$n->id()] = $n;
    }

    $rows = $this->collectRowsFromForm($form_state);

    foreach ($rows as $r) {
      if (empty($r['item'])) {
        continue; // skip empty rows
      }
      $rate = (float) $r['rate'];
      $qty  = (float) $r['quantity'];
      $tot  = (float) ($rate * $qty);

      // Update or create.
      if (!empty($r['id']) && isset($existing_by_id[$r['id']])) {
        $item_node = $existing_by_id[$r['id']];
        unset($existing_by_id[$r['id']]);
      }
      else {
        $item_node = Node::create([
          'type' => 'purchase_order_items',
          'field_purchase_order' => $po_id,
        ]);
      }

      $item_node->setTitle('Item ' . $r['item'] . ' for PO ' . $po_id);
      $item_node->set('field_item_reference', (int) $r['item']);
      $item_node->set('field_item_rate', $rate);
      $item_node->set('field_quantity', $qty);
      $item_node->set('field_total_amount', $tot);
      $item_node->save();
    }

    // Delete items that were removed in the form.
    foreach ($existing_by_id as $leftover) {
      $leftover->delete();
    }
  }

}
