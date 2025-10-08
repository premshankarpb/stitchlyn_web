<?php

namespace Drupal\stitchlyn_quotation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

class QuotationAjaxController extends ControllerBase {

  public function getProductAttributes($product, $quotation) {
    $product_node = Node::load($product);
    $html = '<div id="attr-form"><input type="hidden" name="product" value="' . $product . '">';
    $html .= '<input type="hidden" name="quotation" value="' . $quotation . '">';

    if ($product_node && $product_node->hasField('field_attributes')) {
      foreach ($product_node->get('field_attributes')->referencedEntities() as $para) {
        $bundle = $para->bundle();
        $html .= '<fieldset><legend>' . ucfirst($bundle) . '</legend>';
        foreach ($para->getFieldDefinitions() as $f => $def) {
          if (!in_array($f, ['parent_id','parent_type','parent_field_name','revision_id','id','type'])) {
            $html .= '<label>' . $def->getLabel() . '</label><input type="text" name="' . $bundle . '[' . $f . ']" /><br>';
          }
        }
        $html .= '</fieldset>';
      }
    }

    $html .= '<label>Quantity</label><input type="number" name="quantity" value="1" min="1">';
    $html .= '<button type="button" id="save-attr" class="button button--primary">Save</button></div>';
    return new JsonResponse(['html' => $html]);
  }

  public function saveLineItem($product, $quotation) {
    $req = \Drupal::request();
    $data = json_decode($req->getContent(), TRUE);
    $qty = $data['quantity'] ?? 1;

    $line = Node::create([
      'type' => 'quotation_line_items',
      'title' => 'Item',
      'field_product' => $product,
      'field_linked_quotation' => $quotation,
      'field_quantity' => $qty,
    ]);
    $line->save();

    return new JsonResponse(['status' => 'ok']);
  }

  public function getLineItems($quotation) {
    $table = \Drupal::service('stitchlyn_quotation.helper')->renderLineItemTable($quotation);
    $html = \Drupal::service('renderer')->renderRoot($table);
    return new JsonResponse(['html' => $html]);
  }
}
