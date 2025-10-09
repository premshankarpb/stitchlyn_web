<?php

namespace Drupal\stitchlyn_quotation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * AJAX endpoints for the quotation edit UI.
 */
class QuotationAjaxController extends ControllerBase {

  /**
   * Build the popup form HTML with product attribute fields + quantity.
   */
  public function getProductAttributes($product, $quotation) {
    $product_node = Node::load((int) $product);

    $html = '<div id="attr-form">';
    $html .= '<input type="hidden" name="product" value="' . (int) $product . '">';
    $html .= '<input type="hidden" name="quotation" value="' . (int) $quotation . '">';

    if ($product_node && $product_node->hasField('field_attributes') && !$product_node->get('field_attributes')->isEmpty()) {
      foreach ($product_node->get('field_attributes')->referencedEntities() as $para) {
        $bundle = $para->bundle();
        $html .= '<fieldset class="sq-attr"><legend>' . htmlspecialchars(ucfirst($bundle)) . '</legend>';

        foreach ($para->getFieldDefinitions() as $field_name => $def) {
          // Skip internal fields.
          if (in_array($field_name, ['parent_id','parent_type','parent_field_name','revision_id','id','type'])) {
            continue;
          }
          if (!$para->hasField($field_name)) {
            continue;
          }
          $label = $def->getLabel();
          $html .= '<div class="field"><label>' . htmlspecialchars($label) . '</label>';
          // Keep it simple (text); you can map by field type later.
          $html .= '<input type="text" name="attributes[' . $bundle . '][' . $field_name . ']" />';
          $html .= '</div>';
        }

        $html .= '</fieldset>';
      }
    } else {
      $html .= '<p>' . $this->t('No attributes configured on this product.') . '</p>';
    }

    $html .= '<div class="field"><label>Quantity</label><input type="number" name="quantity" value="1" min="1"></div>';
    $html .= '<div class="actions"><button type="button" id="save-attr" class="button button--primary">Save</button></div>';
    $html .= '</div>';

    return new JsonResponse(['html' => $html]);
  }

  /**
   * Save the line item and create attribute paragraphs with submitted values.
   */
  public function saveLineItem($product, $quotation) {
    $req = \Drupal::request();
    $data = json_decode($req->getContent(), TRUE) ?: [];
    $qty = max(1, (int) ($data['quantity'] ?? 1));

    $product_node = Node::load((int) $product);
    $quotation_node = Node::load((int) $quotation);

    if (!$product_node || !$quotation_node) {
      return new JsonResponse(['status' => 'error', 'message' => 'Invalid references'], 400);
    }

    // Create the line item node (NB: your bundle is "quatation_line_items").
    $line = Node::create([
      'type' => 'quatation_line_items',
      'title' => 'quotation_item_' . $quotation_node->id(),
      'field_product' => ['target_id' => $product_node->id()],
      'field_linked_quotation' => ['target_id' => $quotation_node->id()],
      'field_quantity' => $qty,
      'status' => 1,
    ]);

    // Create new paragraphs for each submitted bundle and set values onto fields.
    if (!empty($data['attributes']) && $line->hasField('field_attributes')) {
      foreach ($data['attributes'] as $bundle => $fields) {
        $paragraph = Paragraph::create(['type' => $bundle]);
        foreach ($fields as $field_name => $value) {
          if ($paragraph->hasField($field_name)) {
            $paragraph->set($field_name, $value);
          }
        }
        $paragraph->save();
        $line->get('field_attributes')->appendItem([
          'target_id' => $paragraph->id(),
          'target_revision_id' => $paragraph->getRevisionId(),
        ]);
      }
    }

    $line->save();

    return new JsonResponse(['status' => 'ok']);
  }

  /**
   * Delete a line item node.
   */
  public function deleteLineItem($item_id) {
    $node = Node::load((int) $item_id);
    if ($node && $node->bundle() === 'quatation_line_items') {
      $node->delete();
      return new JsonResponse(['status' => 'ok']);
    }
    return new JsonResponse(['status' => 'error'], 400);
  }

  /**
   * Return refreshed line items table HTML + current subtotal (for totals recalculation).
   */
  public function getLineItems($quotation) {
    $qid = (int) $quotation;
    $helper = \Drupal::service('stitchlyn_quotation.helper');
    $table = $helper->renderLineItemTable($qid);
    $html = \Drupal::service('renderer')->renderRoot($table);

    $totals = $helper->computeTotals($qid); // includes subtotal

    return new JsonResponse([
      'html' => $html,
      'subtotal' => $totals['subtotal'],
    ]);
  }

  /**
   * Build a "view details" popup with the line item info and attribute values.
   */
  public function viewLineItem($item_id) {
    $node = Node::load((int) $item_id);
    if (!$node || $node->bundle() !== 'quatation_line_items') {
      return new JsonResponse(['html' => '<p>Item not found.</p>'], 404);
    }

    $html = '<div class="line-item-view">';
    $prod = $node->get('field_product')->entity;
    $html .= '<p><strong>Product:</strong> ' . ($prod ? htmlspecialchars($prod->label()) : '-') . '</p>';
    $html .= '<p><strong>Quantity:</strong> ' . htmlspecialchars((string) ($node->get('field_quantity')->value ?? 0)) . '</p>';

    if ($node->hasField('field_attributes') && !$node->get('field_attributes')->isEmpty()) {
      foreach ($node->get('field_attributes')->referencedEntities() as $para) {
        $html .= '<fieldset><legend>' . htmlspecialchars(ucfirst($para->bundle())) . '</legend>';
        foreach ($para->getFieldDefinitions() as $fname => $def) {
          if ($para->hasField($fname) && !$para->get($fname)->isEmpty()) {
            // Simplified display; tailor by field type later.
            $val = $para->get($fname)->value;
            $html .= '<p><strong>' . htmlspecialchars($def->getLabel()) . ':</strong> ' . htmlspecialchars((string) $val) . '</p>';
          }
        }
        $html .= '</fieldset>';
      }
    }

    $html .= '</div>';
    return new JsonResponse(['html' => $html]);
  }

}