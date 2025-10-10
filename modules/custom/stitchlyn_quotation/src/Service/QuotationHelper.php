<?php

namespace Drupal\stitchlyn_quotation\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Helper service for StitchLyn Quotation operations.
 */
class QuotationHelper {

  use StringTranslationTrait;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs the QuotationHelper service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    RendererInterface $renderer,
    ConfigFactoryInterface $config_factory
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->renderer = $renderer;
    $this->configFactory = $config_factory;
  }

  /**
   * Renders the quotation line items table dynamically based on paragraph fields.
   *
   * @param int $quotation_id
   *   The quotation node ID.
   *
   * @return array
   *   Render array for the table.
   */
  public function renderLineItemTable($quotation_id) {
    $node = $this->entityTypeManager->getStorage('node')->load($quotation_id);
    if (!$node || !$node->hasField('field_attributes')) {
      return [
        '#markup' => '<p>' . $this->t('No items added yet.') . '</p>',
      ];
    }

    $rows = [];
    $headers = [
      $this->t('Product'),
      $this->t('Quantity'),
      $this->t('Unit Price'),
      $this->t('Subtotal'),
      $this->t('Actions'),
    ];

    $total_subtotal = 0;

    /** @var \Drupal\paragraphs\Entity\Paragraph[] $items */
    $items = $node->get('field_attributes')->referencedEntities();

    foreach ($items as $item) {
      $product_name = $item->get('field_product')->entity->label() ?? 'N/A';
      $quantity = (int) ($item->get('field_quantity')->value ?? 1);

      // ✅ Collect dynamic fields (skip system/internal ones)
      $custom_fields = [];
      foreach ($item->getFieldDefinitions() as $field_name => $field_definition) {
        if (str_starts_with($field_name, 'field_') &&
          !in_array($field_name, ['field_product', 'field_quantity', 'field_unit_price'], TRUE)
        ) {
          $label = $field_definition->getLabel();
          $value = '-';

          if (!$item->get($field_name)->isEmpty()) {
            $field = $item->get($field_name);
            // Handle entity reference vs. text field gracefully
            if ($field->getFieldDefinition()->getType() === 'entity_reference') {
              $entities = $field->referencedEntities();
              $labels = array_map(fn($e) => $e->label(), $entities);
              $value = implode(', ', $labels);
            }
            else {
              $value = $field->value;
            }
          }

          $custom_fields[$label] = $value;

          // Add dynamic column header if not already present
          if (!in_array($label, $headers, TRUE)) {
            array_splice($headers, count($headers) - 2, 0, $label);
          }
        }
      }

      // Determine price
      $unit_price = $item->hasField('field_unit_price')
        ? (float) $item->get('field_unit_price')->value
        : 100.0;

      $subtotal = $unit_price * $quantity;
      $total_subtotal += $subtotal;

      // Build row data
      $row_data = [
        'product' => $product_name,
      ] + $custom_fields + [
        'quantity' => $quantity,
        'unit_price' => number_format($unit_price, 2),
        'subtotal' => number_format($subtotal, 2),
        'actions' => [
          'data' => [
            '#type' => 'operations',
            '#links' => [
              'view' => [
                'title' => $this->t('View'),
                'url' => Url::fromRoute('stitchlyn_quotation.ajax_view_item', ['item_id' => $item->id()]),
                'attributes' => [
                  'class' => ['view-item'],
                  'data-id' => $item->id(),
                ],
              ],
              'delete' => [
                'title' => $this->t('Remove'),
                'url' => Url::fromRoute('stitchlyn_quotation.ajax_delete_item', ['item_id' => $item->id()]),
                'attributes' => [
                  'class' => ['remove-item'],
                  'data-id' => $item->id(),
                ],
              ],
            ],
          ],
        ],
      ];

      $rows[] = $row_data;
    }

    if (empty($rows)) {
      return [
        '#markup' => '<p>' . $this->t('No items added yet.') . '</p>',
      ];
    }

    // ✅ Build the render array dynamically
    return [
      '#theme' => 'table',
      '#header' => $headers,
      '#rows' => array_map(function ($row) {
        $cells = [];
        foreach ($row as $key => $value) {
          $cells[] = is_array($value) ? $this->renderer->render($value) : $value;
        }
        return $cells;
      }, $rows),
      '#attributes' => ['class' => ['quotation-line-items']],
    ];
  }

  /**
   * Computes totals (subtotal, tax, total) for a quotation.
   *
   * @param int $quotation_id
   *   The quotation node ID.
   *
   * @return array
   *   Totals with subtotal, tax, and total.
   */
  public function computeTotals($quotation_id) {
    $node = $this->entityTypeManager->getStorage('node')->load($quotation_id);
    if (!$node || !$node->hasField('field_attributes')) {
      return ['subtotal' => 0, 'tax' => 0, 'total' => 0];
    }

    $subtotal = 0;
    $config = $this->configFactory->get('stitchlyn_quotation.settings');
    $tax_rate = (float) ($config->get('tax_rate') ?? 18);
    $discount = (float) ($node->get('field_discount')->value ?? 0);

    foreach ($node->get('field_attributes')->referencedEntities() as $item) {
      $qty = (int) ($item->get('field_quantity')->value ?? 1);
      $price = $item->hasField('field_unit_price')
        ? (float) $item->get('field_unit_price')->value
        : 100;
      $subtotal += $price * $qty;
    }

    $tax = ($subtotal * $tax_rate) / 100;
    $total = $subtotal - $discount + $tax;

    return [
      'subtotal' => round($subtotal, 2),
      'tax' => round($tax, 2),
      'total' => round($total, 2),
    ];
  }

}