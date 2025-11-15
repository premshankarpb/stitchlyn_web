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
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\Component\Render\PlainTextOutput;
use Drupal\node\Entity\Node;
use Drupal\Core\Database\Database;
use Drupal\file\Entity\File;
use Drupal\Component\Utility\Html;

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
   * Handles saving or updating a quotation line item,
   * and updates corresponding inventory transaction logs.
   */
  public function saveAttributeItem(Request $request, $product_id, $quotation_id) {
    try {
      $quotation = Node::load($quotation_id);
      if (!$quotation || $quotation->bundle() !== 'quotation') {
        return new JsonResponse(['status' => 'failed', 'message' => 'Invalid quotation'], 400);
      }

      $product = Node::load($product_id);
      if (!$product || $product->bundle() !== 'product') {
        return new JsonResponse(['status' => 'failed', 'message' => 'Invalid product'], 400);
      }

      /** ----------------------------------------------------
       * 1. READ RAW FORMDATA
       * ----------------------------------------------------*/
      $formData = $request->request->all();
      $files = $request->files->all();

      $quantity = isset($formData['quantity']) ? (float)$formData['quantity'] : 1;

      /** ----------------------------------------------------
       * 2. CREATE PARAGRAPH (same paragraph type as product)
       * ----------------------------------------------------*/
      $paragraphType = $product->get('field_attributes')->referencedEntities()[0]->bundle();

      $paragraph = Paragraph::create(['type' => $paragraphType]);

      foreach ($formData as $key => $value) {

        // Example: field_candle_radius[0][value] => field_candle_radius
        if (preg_match('/^field_[a-z0-9_]+/i', $key, $match)) {
          $fieldName = $match[0];

          // Skip if paragraph does not contain the field
          if (!$paragraph->hasField($fieldName)) {
            continue;
          }

          // Extract actual value
          if (is_array($value)) {
            $paragraph->set($fieldName, $value);
          }
          else {
            $paragraph->set($fieldName, [['value' => $value]]);
          }
        }
      }

      /* ============================================================
      *  HANDLE FILE UPLOADS FROM POPUP (FAPI OVERRIDE)
      * ============================================================ */
      $allFiles = $request->files->all();

      if (!empty($allFiles['files'])) {
        foreach ($allFiles['files'] as $rawFieldName => $uploadedFile) {

          // rawFieldName example: field_candle_images_0
          $fieldName = preg_replace('/_\d+$/', '', $rawFieldName);

          if (!$paragraph->hasField($fieldName)) {
            continue;
          }

          if ($uploadedFile instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {

            // Ensure directory exists
            $directory = 'public://product_attributes/';
            \Drupal::service('file_system')->prepareDirectory(
              $directory,
              \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY
            );

            // Read binary file content
            $data = file_get_contents($uploadedFile->getRealPath());

            // Generate destination path
            $filename = $uploadedFile->getClientOriginalName();
            $destination = $directory . $filename;

            // Save file to managed file system
            $file = \Drupal::service('file.repository')->writeData(
              $data,
              $destination,
              \Drupal\Core\File\FileSystemInterface::EXISTS_RENAME
            );

            if ($file) {
              $file->setPermanent();
              $file->save();

              // Attach the saved file to paragraph field
              $paragraph->set($fieldName, [
                ['target_id' => $file->id()]
              ]);
            }
          }
        }
      }

      $paragraph->save();

      /** ----------------------------------------------------
       * 4. CREATE QUOTATION LINE ITEM
       * ----------------------------------------------------*/
      $lineItem = Node::create([
        'type' => 'quatation_line_items',
        'title' => $product->label() . " – " . $quotation->label(),
        'field_product' => $product_id,
        'field_quantity' => $quantity,
        'field_linked_quotation' => $quotation_id,
        'field_attributes' => [
          ['target_id' => $paragraph->id(), 'target_revision_id' => $paragraph->getRevisionId()]
        ],
        'status' => 1,
      ]);

      $lineItem->save();

      return new JsonResponse([
        'status' => 'success',
        'message' => 'Line item created.'
      ]);

    }
    catch (\Throwable $e) {
      \Drupal::logger('stitchlyn_quotation')->error($e->getMessage());
      return new JsonResponse([
        'status' => 'failed',
        'message' => $e->getMessage()
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

            // Only process real fields
            if (strpos($field_name, 'field_') !== 0) continue;
            if ($field->isEmpty()) continue;

            $label = ucfirst(str_replace('field_', '', $field_name));
            $items = $field->getValue();
            $field_type = $field->getFieldDefinition()->getType();
            $output_items = [];

            foreach ($items as $delta => $item) {

              /* -------------------------------
              * FILE / IMAGE FIELDS (multi OK)
              * ------------------------------- */
              if (isset($item['target_id']) && in_array($field_type, ['image', 'file'])) {
                if ($file = \Drupal\file\Entity\File::load($item['target_id'])) {
                  $url = \Drupal::service('file_url_generator')
                    ->generateAbsoluteString($file->getFileUri());

                  $filename = $file->getFilename();

                  $output_items[] = '<a href="' . $url . '" download target="_blank">'
                    . htmlspecialchars($filename) .
                    '</a>';
                }
              }

              /* -------------------------------
              * ENTITY REFERENCE FIELDS
              * ------------------------------- */
              elseif ($field_type === 'entity_reference' && isset($field->entity)) {
                $output_items[] = htmlspecialchars($field->entity->label());
              }

              /* -------------------------------
              * NORMAL TEXT / NUMBER FIELDS
              * ------------------------------- */
              elseif (isset($item['value'])) {
                $output_items[] = htmlspecialchars($item['value']);
              }
            }

            // Combine multi-values using line breaks
            $value_markup = implode('<br>', $output_items);

            $rows[] = [
              ['data' => ['#markup' => $label]],
              ['data' => ['#markup' => $value_markup]],
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