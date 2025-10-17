<?php

namespace Drupal\stitchlyn_vendor\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Edit / Add Purchase Order (with dynamic items).
 */
class PurchaseOrderEditForm extends FormBase {

  protected EntityTypeManagerInterface $entityTypeManager;

  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  public function getFormId() {
    return 'stitchlyn_purchase_order_edit_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, Node $node = NULL) {
    // Keep the node handy.
    if ($node) {
      $form_state->set('po_node', $node);
    }
    else {
      $node = $form_state->get('po_node');
    }

    // Number of rows.
    if (!$form_state->has('num_rows')) {
      // If existing PO has items, show that many rows; else 1.
      $existing_count = $this->loadItemNodes($node ? $node->id() : 0, TRUE);
      $form_state->set('num_rows', max(1, $existing_count));
    }
    $rows = (int) $form_state->get('num_rows');

    // Use a stable outer wrapper for recalculation.
    $form['#prefix'] = '<div id="po-form-root">';
    $form['#suffix'] = '</div>';

    /***********************  GENERAL INFO  ***************************/
    $form['general'] = [
      '#type'  => 'details',
      '#title' => $this->t('Purchase Order Details'),
      '#open'  => TRUE,
    ];

    $form['general']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $node ? $node->label() : '',
      '#required' => TRUE,
      '#description' => $this->t('Will be overwritten on save to Purchase-Order_&lt;Serial&gt;.'),
    ];

    $form['general']['vendor'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Vendor'),
      '#target_type' => 'user',
      '#default_value' => $node && !$node->isNew() && $node->get('field_vendor')->entity
        ? $node->get('field_vendor')->entity
        : NULL,
      '#attributes' => ['class' => ['vendor-autocomplete']],
    ];

    $form['general']['date_of_purchase'] = [
      '#type' => 'date',
      '#title' => $this->t('Date of Purchase'),
      '#default_value' => $node && !$node->isNew() ? ($node->get('field_date_of_purchase')->value ?: '') : '',
    ];

    // Payment status (taxonomy).
    $options = [];
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('payment_status');
    foreach ($terms as $t) {
      $options[$t->tid] = $t->name;
    }
    $form['general']['payment_status'] = [
      '#type' => 'select',
      '#title' => $this->t('Payment Status'),
      '#options' => $options,
      '#default_value' => $node && !$node->isNew() ? $node->get('field_payment_status')->target_id : '',
      '#empty_option' => $this->t('- Select -'),
    ];

    $form['general']['remarks'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Remarks'),
      '#default_value' => $node && !$node->isNew() ? ($node->get('body')->value ?: '') : '',
    ];

    /********************  RECALC WRAPPER (TABLE + SUMMARY)  ******************/
    $form['recalc'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'po-recalc-wrapper'],
    ];

    // ----------- ITEMS SECTION -----------
    $form['recalc']['items_section'] = [
      '#type' => 'details',
      '#title' => $this->t('Purchase Items'),
      '#open'  => TRUE,
    ];

    $form['recalc']['items_section']['items'] = [
      '#type'   => 'table',
      '#header' => [
        $this->t('Item'),
        $this->t('Rate'),
        $this->t('Quantity'),
        $this->t('Total'),
        $this->t('Action'),
      ],
      '#tree'   => TRUE,
    ];

    // Build rows. Use user input if available to prevent resets.
    $input = (array) $form_state->getUserInput();
    $input_items = $input['items'] ?? [];

    // Preload existing item nodes for edit (first build only).
    $existing_items = $node && !$node->isNew() ? $this->loadItemNodes($node->id()) : [];

    $subtotal = 0;

    for ($i = 0; $i < $rows; $i++) {
      // Source values: user input → existing items → zeros.
      $rate = $input_items[$i]['rate'] ?? ($existing_items[$i]['rate'] ?? 0);
      $qty  = $input_items[$i]['quantity'] ?? ($existing_items[$i]['quantity'] ?? 0);
      $item_default = NULL;
      if (isset($existing_items[$i]['item']) && $existing_items[$i]['item']) {
        $item_default = $this->entityTypeManager->getStorage('node')->load($existing_items[$i]['item']);
      }

      $total = (float) $rate * (float) $qty;
      $subtotal += $total;

      // Item (inventory node).
      $form['recalc']['items_section']['items'][$i]['item'] = [
        '#type' => 'entity_autocomplete',
        '#target_type' => 'node',
        '#selection_settings' => ['target_bundles' => ['inventory_item']],
        '#default_value' => $item_default,
        '#attributes' => ['class' => ['item-autocomplete']],
      ];

      // Rate.
      $form['recalc']['items_section']['items'][$i]['rate'] = [
        '#type' => 'number',
        '#step' => '0.01',
        '#default_value' => $rate,
        '#ajax' => [
          'callback' => '::ajaxRecalculate',
          'event'    => 'change',
          'wrapper'  => 'po-recalc-wrapper',
        ],
      ];

      // Quantity.
      $form['recalc']['items_section']['items'][$i]['quantity'] = [
        '#type' => 'number',
        '#step' => '1',
        '#default_value' => $qty,
        '#ajax' => [
          'callback' => '::ajaxRecalculate',
          'event'    => 'change',
          'wrapper'  => 'po-recalc-wrapper',
        ],
      ];

      // Total (read-only number for visual feedback)
      $form['recalc']['items_section']['items'][$i]['total'] = [
        '#type' => 'number',
        '#default_value' => $total,
        '#attributes' => ['readonly' => 'readonly'],
      ];

      // Remove button
      $form['recalc']['items_section']['items'][$i]['remove'] = [
        '#type'  => 'submit',
        '#value' => $this->t('X'),
        '#name'  => 'remove_' . $i,
        '#limit_validation_errors' => [],
        '#submit' => ['::removeItem'],
        '#ajax'   => [
          'callback' => '::ajaxRecalculate',
          'wrapper'  => 'po-recalc-wrapper',
        ],
      ];
    }

    // Add item
    $form['recalc']['items_section']['add_item'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Item'),
      '#limit_validation_errors' => [],
      '#submit' => ['::addItem'],
      '#ajax'   => [
        'callback' => '::ajaxRecalculate',
        'wrapper'  => 'po-recalc-wrapper',
      ],
    ];

    // --- SUMMARY ---
    $config = $this->config('stitchlyn_basic.erp_settings');
    $tax_percent = (float) ($config->get('tax_percentage') ?? 0);

    // Compute live values from user input, not just defaults.
    $input = $form_state->getUserInput();
    if (!empty($input['items_section']['items'])) {
      $subtotal = 0;
      foreach ($input['items_section']['items'] as $row) {
        $r = (float) ($row['rate'] ?? 0);
        $q = (float) ($row['quantity'] ?? 0);
        $subtotal += $r * $q;
      }
    }

    $tax_total   = ($subtotal * $tax_percent) / 100;
    $grand_total = $subtotal + $tax_total;

    $form['recalc']['summary'] = [
      '#type' => 'details',
      '#title' => $this->t('Purchase Summary'),
      '#open'  => TRUE,
    ];

    $form['recalc']['summary']['subtotal'] = [
      '#type' => 'number',
      '#title' => $this->t('Subtotal'),
      '#value' => $subtotal,
      '#attributes' => ['readonly' => 'readonly'],
    ];

    $form['recalc']['summary']['tax_total'] = [
      '#type' => 'number',
      '#title' => $this->t('Tax @ @t%', ['@t' => $tax_percent]),
      '#value' => $tax_total,
      '#attributes' => ['readonly' => 'readonly'],
    ];

    $form['recalc']['summary']['grand_total'] = [
      '#type' => 'number',
      '#title' => $this->t('Total Amount'),
      '#value' => $grand_total,
      '#attributes' => ['readonly' => 'readonly'],
    ];

    /*************************** ACTIONS ****************************/
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Purchase Order'),
      '#button_type' => 'primary',
    ];

    // Attach our JS
    $form['#attached']['library'][] = 'stitchlyn_vendor/vendor_info';
    $form['#attached']['drupalSettings']['stitchlyn_vendor'] = [
      'vendorInfoUrl' => '/stitchlyn/vendor/info',
      'itemInfoUrl'   => '/stitchlyn/item/info',
    ];

    return $form;
  }

  /** AJAX callback: rebuild items + summary. */
  public function ajaxRecalculate(array &$form, FormStateInterface $form_state) {
    return $form['recalc'];
  }

  /** Add a new row. */
  public function addItem(array &$form, FormStateInterface $form_state) {
    $form_state->set('num_rows', ((int) $form_state->get('num_rows')) + 1);
    $form_state->setRebuild(TRUE);
  }

  /** Remove a row. */
  public function removeItem(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    if (!empty($trigger['#name']) && str_starts_with($trigger['#name'], 'remove_')) {
      $idx = (int) substr($trigger['#name'], 7);
      $rows = (int) $form_state->get('num_rows');
      $rows = max(1, $rows - 1);

      // Drop the removed row from user input (so values shift correctly).
      $input = $form_state->getUserInput();
      if (isset($input['items'][$idx])) {
        array_splice($input['items'], $idx, 1);
        $form_state->setUserInput($input);
      }

      $form_state->set('num_rows', $rows);
      $form_state->setRebuild(TRUE);
    }
  }

  /** Submit handler: save PO and items. */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\node\Entity\Node $po */
    $po = $form_state->get('po_node');

    // Retrieve all submitted values (flattened).
    $values = $form_state->getValues();

    // --- Save Purchase Order fields ---
    $po->set('field_vendor', $values['vendor'] ?: NULL);
    $po->set('field_date_of_purchase', $values['date_of_purchase'] ?: NULL);
    $po->set('field_payment_status', $values['payment_status'] ?: NULL);
    $po->set('body', ['value' => $values['remarks'] ?? '', 'format' => 'basic_html']);

    $po->set('field_subtotal_amount', $values['subtotal'] ?? 0);
    $po->set('field_tax_amount', $values['tax_total'] ?? 0);
    $po->set('field_total_amount', $values['grand_total'] ?? 0);

    // First save (ensures serial field auto-generates).
    $po->save();

    // --- Title Update ---
    $serial = $po->get('field_po_number')->value;
    $title = 'Purchase-Order_' . $serial;
    if ($po->label() !== $title) {
      $po->setTitle($title);
      $po->save();
    }

    // --- Clear old items ---
    $this->deleteExistingItems($po->id());

    // --- Save new Purchase Order Items ---
    $items = $values['items'] ?? $values['items_section']['items'] ?? [];
    foreach ($items as $row) {
      $item_nid = $row['item'] ?? NULL;
      $rate     = isset($row['rate']) ? (float) $row['rate'] : 0;
      $qty      = isset($row['quantity']) ? (float) $row['quantity'] : 0;
      if (!$item_nid || (!$rate && !$qty)) continue;

      $total = $rate * $qty;

      $item_node = Node::create([
        'type' => 'purchase_order_items',
        'title' => 'Item for PO ' . $po->id(),
        'field_purchase_order' => $po->id(),
        'field_item_reference' => $item_nid,
        'field_item_rate' => $rate,
        'field_quantity' => $qty,
        'field_total_amount' => $total,
        'status' => 1,
      ]);
      $item_node->save();
    }

    $this->messenger()->addStatus($this->t('Purchase Order and items saved successfully.'));
  }

  /** Helpers *********************************************************** */

  /**
   * Load existing item rows for a PO.
   *
   * @param int $po_nid
   * @param bool $count_only
   * @return array|int
   */
  protected function loadItemNodes($po_nid, $count_only = FALSE) {
    if (!$po_nid) return $count_only ? 0 : [];

    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->condition('type', 'purchase_order_items')
      ->condition('field_purchase_order', $po_nid)
      ->accessCheck(FALSE)
      ->execute();
    if ($count_only) return count($ids);

    $nodes = $storage->loadMultiple($ids);
    $rows = [];
    foreach ($nodes as $n) {
      $rows[] = [
        'item'     => (int) $n->get('field_item_reference')->target_id,
        'rate'     => (float) $n->get('field_item_rate')->value,
        'quantity' => (float) $n->get('field_quantity')->value,
      ];
    }
    return $rows;
  }

  /** Delete all child items for a PO. */
  protected function deleteExistingItems($po_nid) {
    if (!$po_nid) return;
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->condition('type', 'purchase_order_items')
      ->condition('field_purchase_order', $po_nid)
      ->accessCheck(FALSE)
      ->execute();
    if ($ids) {
      $entities = $storage->loadMultiple($ids);
      $storage->delete($entities);
    }
  }
}