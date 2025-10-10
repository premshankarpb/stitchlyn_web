<?php

namespace Drupal\stitchlyn_quotation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\Component\Render\PlainTextOutput;

/**
 * Handles AJAX operations for quotation items.
 */
class QuotationAjaxController extends ControllerBase {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    FormBuilderInterface $form_builder,
    RendererInterface $renderer
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->formBuilder = $form_builder;
    $this->renderer = $renderer;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('form_builder'),
      $container->get('renderer')
    );
  }

  // ---------------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------------

  /**
   * Find the first paragraph reference field on the quotation node.
   *
   * We prefer known names but fall back to any field that is
   * entity_reference_revisions targeting paragraphs.
   */
  protected function resolveLineItemField(NodeInterface $node): ?string {
    // 1) Prefer common field machine names.
    $preferred = [
      'field_quotation_items',
      'field_items',
      'field_line_items',
      'field_attributes',
    ];
    foreach ($preferred as $candidate) {
      if ($node->hasField($candidate)) {
        $def = $node->get($candidate)->getFieldDefinition();
        $type = $def->getType();
        $settings = $def->getSettings();
        if ($type === 'entity_reference_revisions'
          && ($settings['target_type'] ?? '') === 'paragraph') {
          return $candidate;
        }
      }
    }

    // 2) Otherwise scan all fields for a paragraph E/R Revisions field.
    foreach ($node->getFieldDefinitions() as $name => $def) {
      if ($node->hasField($name)) {
        $type = $def->getType();
        $settings = $def->getSettings();
        if ($type === 'entity_reference_revisions'
          && ($settings['target_type'] ?? '') === 'paragraph') {
          return $name;
        }
      }
    }

    return NULL;
  }

  /**
   * Determine attribute paragraph bundle from product node (first ref in product->field_attributes).
   */
  protected function detectAttributeBundle(?NodeInterface $product_node): string {
    $bundle = 'shirt_attribute'; // safe default
    if ($product_node && $product_node->hasField('field_attributes') && !$product_node->get('field_attributes')->isEmpty()) {
      $ref = $product_node->get('field_attributes')->referencedEntities();
      if (!empty($ref) && $ref[0] instanceof ParagraphInterface) {
        $bundle = $ref[0]->bundle();
      }
    }
    return $bundle;
  }

  // ---------------------------------------------------------------------------
  // Endpoints
  // ---------------------------------------------------------------------------

  /**
   * Popup attribute form.
   * URL: /quotation/ajax/attributes/{product}/{quotation}
   */
  public function getAttributeForm($product, $quotation) {
    // Load the product node.
    $product_node = $this->entityTypeManager->getStorage('node')->load($product);
    if (!$product_node) {
      return new JsonResponse(['status' => 'error', 'message' => 'Invalid product.']);
    }

    // Detect the attribute paragraph bundle.
    $bundle = 'shirt_attribute';
    if ($product_node->hasField('field_attributes') && !$product_node->get('field_attributes')->isEmpty()) {
      $refs = $product_node->get('field_attributes')->referencedEntities();
      if (!empty($refs)) {
        $bundle = $refs[0]->bundle();
      }
    }

    // Create an empty paragraph of that bundle.
    $paragraph = $this->entityTypeManager
      ->getStorage('paragraph')
      ->create(['type' => $bundle]);

    // Build the default form for that paragraph.
    $form_obj = $this->entityTypeManager
      ->getFormObject('paragraph', 'default')
      ->setEntity($paragraph);
    $form = $this->formBuilder->getForm($form_obj);

    // Hide system/internal fields we don’t want in popup.
    foreach ([
      'uuid',
      'langcode',
      'status',
      'created',
      'behavior_settings',
      'default_langcode',
      'revision_translation_affected',
      'author',
      'revision_log',
    ] as $remove) {
      unset($form[$remove]);
    }

    // ✅ Add our own Quantity field — clean name and stable ID.
    $form['sq_quantity'] = [
      '#type' => 'number',
      '#title' => $this->t('Quantity'),
      '#default_value' => 1,
      '#min' => 1,
      '#required' => TRUE,
      // Explicitly set name and id so it appears in HTML
      '#name' => 'sq_quantity',
      '#attributes' => [
        'id' => 'sq-quantity',
        'name' => 'sq_quantity',
        'class' => ['form-number', 'required'],
      ],
      '#weight' => 100,
    ];

    // ✅ Add the Save button (JS handles the click).
    $form['actions'] = [
      '#type' => 'actions',
      '#weight' => 110,
      'submit' => [
        '#type' => 'button',
        '#value' => $this->t('Save'),
        '#attributes' => [
          'id' => 'save-attr',
          'class' => ['button', 'button--primary'],
          'type' => 'button', // prevents default form submit
        ],
      ],
    ];

    // Render the form to HTML for the popup.
    $html = $this->renderer->renderRoot($form);

    return new JsonResponse([
      'status' => 'success',
      'bundle' => $bundle,
      'html' => $html,
    ]);
  }

  /**
   * AJAX: Save attribute + quantity for product into Quotation Line Item.
   */
  public function saveAttributeItem(Request $request, $product_id, $quotation_id) {
    try {
      // Decode payload.
      $data = json_decode($request->getContent(), TRUE);
      if (empty($data)) {
        return new JsonResponse([
          'status' => 'error',
          'message' => 'No data received.',
        ]);
      }

      $attributes = $data['attributes'] ?? [];
      $quantity = !empty($data['quantity']) ? (float) $data['quantity'] : 1;

      // 1️⃣ Load Quotation node.
      $quotation = $this->entityTypeManager->getStorage('node')->load($quotation_id);
      if (!$quotation) {
        return new JsonResponse(['status' => 'error', 'message' => 'Invalid quotation ID.']);
      }

      // 2️⃣ Load Product node.
      $product_node = $this->entityTypeManager->getStorage('node')->load($product_id);
      if (!$product_node) {
        return new JsonResponse(['status' => 'error', 'message' => 'Invalid product ID.']);
      }

      // 3️⃣ Determine paragraph type (fallback: shirt_attribute).
      $paragraph_type = 'shirt_attribute';
      if ($product_node->hasField('field_attributes') && !$product_node->get('field_attributes')->isEmpty()) {
        $refs = $product_node->get('field_attributes')->referencedEntities();
        if (!empty($refs)) {
          $paragraph_type = $refs[0]->bundle();
        }
      }

      // 4️⃣ Create new Paragraph.
      $paragraph = $this->entityTypeManager->getStorage('paragraph')->create([
        'type' => $paragraph_type,
      ]);

      // 5️⃣ Populate paragraph fields.
      foreach ($attributes as $field_name => $value) {
        if (!$paragraph->hasField($field_name)) {
          continue;
        }

        $field_def = $paragraph->get($field_name)->getFieldDefinition();
        $field_type = $field_def->getType();

        // Handle taxonomy/entity reference.
        if (is_numeric($value) && $field_type === 'entity_reference') {
          $paragraph->set($field_name, ['target_id' => (int) $value]);
        }
        // Handle text/number fields.
        elseif (in_array($field_type, ['string', 'string_long', 'text', 'text_long', 'integer', 'decimal', 'float'])) {
          $paragraph->set($field_name, ['value' => $value]);
        }
        // Fallback (rare cases).
        else {
          $paragraph->set($field_name, $value);
        }
      }

      $paragraph->save();

      // 6️⃣ Create Line Item node (type: quatation_line_items).
      $line_item = $this->entityTypeManager->getStorage('node')->create([
        'type' => 'quatation_line_items',
        'title' => $product_node->label(),
        'field_product' => ['target_id' => $product_id],
        'field_quantity' => $quantity,
        'field_attributes' => [
          'target_id' => $paragraph->id(),
          'target_revision_id' => $paragraph->getRevisionId(),
        ],
        'field_linked_quotation' => ['target_id' => $quotation_id],
        'status' => 1,
      ]);
      $line_item->save();

      // 7️⃣ Response.
      return new JsonResponse([
        'status' => 'success',
        'message' => 'Item saved successfully.',
        'paragraph_id' => $paragraph->id(),
        'line_item_id' => $line_item->id(),
      ]);
    }
    catch (\Exception $e) {
      \Drupal::logger('stitchlyn_quotation')->error($e->getMessage());
      return new JsonResponse([
        'status' => 'error',
        'message' => 'Exception: ' . $e->getMessage(),
      ]);
    }
  }

  /**
   * AJAX: Return all quotation line items as HTML table.
   */
  public function getLineItems($quotation_id) {
    try {
      $storage = $this->entityTypeManager->getStorage('node');
      $nids = $storage->getQuery()
        ->condition('type', 'quatation_line_items')
        ->condition('field_linked_quotation', $quotation_id)
        ->accessCheck(FALSE)
        ->execute();

      if (empty($nids)) {
        return new JsonResponse([
          'status' => 'success',
          'html' => '<p class="no-items">No items added yet.</p>',
          'subtotal' => 0,
        ]);
      }

      $nodes = $storage->loadMultiple($nids);
      $rows = [];
      $subtotal = 0;

      foreach ($nodes as $node) {
        $product_name = '';
        $unit_price = 0;
        $quantity = 0;
        $total_price = 0;

        // --- Product name and cost price ---
        if ($node->hasField('field_product') && !$node->get('field_product')->isEmpty()) {
          $product = $node->get('field_product')->entity;
          if ($product) {
            // Sanitize name for safe HTML display.
            $product_name = PlainTextOutput::renderFromHtml($product->label());

            // ✅ Fetch cost price from product.
            if ($product->hasField('field_cost_price') && !$product->get('field_cost_price')->isEmpty()) {
              $unit_price = (float) $product->get('field_cost_price')->value;
            }
          }
        }

        // --- Quantity ---
        if ($node->hasField('field_quantity') && !$node->get('field_quantity')->isEmpty()) {
          $quantity = (float) $node->get('field_quantity')->value;
        }

        // --- Compute total ---
        $total_price = $unit_price * $quantity;
        $subtotal += $total_price;

        // --- Build row ---
        $rows[] = [
          'data' => [
            ['data' => $product_name],
            ['data' => number_format($quantity, 2)],
            ['data' => number_format($unit_price, 2)],
            ['data' => number_format($total_price, 2)],
            [
              'data' => [
                '#markup' => sprintf(
                  '<a href="#" class="view-item" data-id="%d">View</a> | 
                  <a href="#" class="remove-item" data-id="%d">Remove</a>',
                  $node->id(),
                  $node->id()
                ),
              ],
            ],
          ],
        ];
      }

      // --- Table header and render array ---
      $header = ['Product', 'Quantity', 'Unit Price', 'Total Price', 'Actions'];
      $table = [
        '#theme' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#attributes' => ['class' => ['quotation-line-items']],
        '#empty' => $this->t('No items added yet.'),
      ];

      // ✅ Render HTML safely for AJAX.
      $html = \Drupal::service('renderer')->renderPlain($table);

      // --- Return AJAX response ---
      return new JsonResponse([
        'status' => 'success',
        'html' => $html,
        'subtotal' => round($subtotal, 2),
      ]);
    }
    catch (\Exception $e) {
      \Drupal::logger('stitchlyn_quotation')->error($e->getMessage());
      return new JsonResponse([
        'status' => 'error',
        'message' => 'Exception: ' . $e->getMessage(),
      ]);
    }
  }


  /**
   * Delete one line item.
   * URL: /quotation/ajax/delete-item/{item_id}
   */
  public function deleteLineItem($item_id) {
    $node = $this->entityTypeManager->getStorage('node')->load($item_id);
    if ($node && $node->bundle() === 'quatation_line_items') {
      $node->delete();
      return new JsonResponse(['status' => 'success', 'message' => 'Item removed.']);
    }
    return new JsonResponse(['status' => 'error', 'message' => 'Item not found.']);
  }

  /**
   * AJAX: Return quotation line item details (attribute summary popup).
   */
  public function viewAttributeItem($item_id) {
    try {
      $node = $this->entityTypeManager->getStorage('node')->load($item_id);
      if (!$node || $node->bundle() !== 'quatation_line_items') {
        return new JsonResponse([
          'status' => 'error',
          'message' => 'Invalid item or not found.',
        ]);
      }

      $rows = [];

      // Product.
      if ($node->hasField('field_product') && !$node->get('field_product')->isEmpty()) {
        $rows[] = [
          ['data' => ['#markup' => '<strong>Product</strong>']],
          ['data' => ['#markup' => $node->get('field_product')->entity->label()]],
        ];
      }

      // Quantity.
      if ($node->hasField('field_quantity') && !$node->get('field_quantity')->isEmpty()) {
        $rows[] = [
          ['data' => ['#markup' => '<strong>Quantity</strong>']],
          ['data' => ['#markup' => number_format($node->get('field_quantity')->value, 2)]],
        ];
      }

      // Linked quotation.
      if ($node->hasField('field_linked_quotation') && !$node->get('field_linked_quotation')->isEmpty()) {
        $rows[] = [
          ['data' => ['#markup' => '<strong>Linked Quotation</strong>']],
          ['data' => ['#markup' => $node->get('field_linked_quotation')->entity->label()]],
        ];
      }

      // Attributes paragraph.
      if ($node->hasField('field_attributes') && !$node->get('field_attributes')->isEmpty()) {
        $para = $node->get('field_attributes')->entity;
        if ($para) {
          $rows[] = [
            ['data' => ['#markup' => '<strong>Attributes</strong>']],
            ['data' => ['#markup' => '']],
          ];
          foreach ($para->getFields() as $field_name => $field) {
            if (strpos($field_name, 'field_') !== 0) continue;
            if ($field->isEmpty()) continue;

            // Handle referenced taxonomy terms.
            $value = '';
            if ($field->getFieldDefinition()->getType() === 'entity_reference' && $field->entity) {
              $value = $field->entity->label();
            }
            else {
              $value = $field->value;
            }

            $label = ucfirst(str_replace('field_', '', $field_name));
            $rows[] = [
              ['data' => ['#markup' => $label]],
              ['data' => ['#markup' => $value]],
            ];
          }
        }
      }

      // Render table.
      $table = [
        '#theme' => 'table',
        '#header' => ['Attribute', 'Value'],
        '#rows' => $rows,
        '#attributes' => ['class' => ['quotation-item-view']],
      ];

      $html = \Drupal::service('renderer')->renderPlain($table);

      return new JsonResponse([
        'status' => 'success',
        'html' => $html,
      ]);
    }
    catch (\Exception $e) {
      \Drupal::logger('stitchlyn_quotation')->error($e->getMessage());
      return new JsonResponse([
        'status' => 'error',
        'message' => $e->getMessage(),
      ]);
    }
  }


}