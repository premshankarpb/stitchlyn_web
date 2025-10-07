<?php

namespace Drupal\stitchlyn_quotation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles popup attribute form and line item actions.
 */
class QuotationPopupController extends ControllerBase {

  /**
   * Opens the attribute paragraph popup form.
   */
  public function attributePopup(Request $request) {
    $product_nid = $request->query->get('product_nid');
    $quotation_nid = $request->query->get('quotation_nid');

    $response = new AjaxResponse();
    $form = \Drupal::formBuilder()->getForm('\Drupal\stitchlyn_quotation\Form\AttributePopupForm', $product_nid, $quotation_nid);
    $response->addCommand(new OpenModalDialogCommand('Product Attributes', $form, ['width' => '600']));
    return $response;
  }

  /**
   * Removes a line item and refreshes the table via AJAX.
   */
  public function removeItem($nid) {
    $response = new AjaxResponse();
    if ($node = Node::load($nid)) {
      $quotation_id = $node->get('field_linked_quotation')->target_id;
      $node->delete();

      // Refresh table markup.
      $table_markup = \Drupal::service('stitchlyn_quotation.helper')->renderLineItemTable($quotation_id);
      $response->addCommand(new HtmlCommand('#line-items-wrapper', $table_markup));
    }
    return $response;
  }

}