/**
 * @file
 * Handles add-product popup and totals recalculation for Quotation form.
 */
import { once } from 'drupal';

(function ($, Drupal, once) {
  Drupal.behaviors.stitchlynQuotation = {
    attach(context) {

      // --- Add Product button ---
      once('sqAddProduct', '.add-product-btn', context).forEach((el) => {
        $(el).on('click', function (e) {
          e.preventDefault();
          const product = $('#product-autocomplete').val();
          const quotation = $(this).data('quotation-id');
          if (!product) {
            alert('Please select a product.');
            return;
          }

          // Load popup markup.
          $.get(Drupal.url('quotation/ajax/attributes/' + product + '/' + quotation), function (res) {
            const $dlg = $('<div class="sq-dialog"></div>').html(res.html);
            Drupal.dialog($dlg, { title: 'Add Product', width: 640 }).showModal();

            // Handle Save inside modal.
            $dlg.on('click', '#save-attr', function () {
              const payload = {
                quantity: parseInt($dlg.find('input[name="quantity"]').val() || '1', 10),
              };

              $.ajax({
                url: Drupal.url('quotation/ajax/save-item/' + product + '/' + quotation),
                method: 'POST',
                data: JSON.stringify(payload),
                contentType: 'application/json',
                success(resp) {
                  if (resp.status === 'ok') {
                    $dlg.dialog('close');
                    // Refresh line items and totals.
                    $.get(Drupal.url('quotation/ajax/line-items/' + quotation), function (res2) {
                      $('#line-items-wrapper').html(res2.html);
                      recomputeTotals();
                    });
                  } else {
                    alert('Failed to save item.');
                  }
                },
                error() {
                  alert('Failed to save item.');
                },
              });
            });
          });
        });
      });

      // --- Discount change watcher ---
      once('sqDiscount', 'input[name="field_discount"]', context).forEach((el) => {
        $(el).on('input', function () {
          recomputeTotals();
        });
      });

      // --- Recompute Totals helper ---
      function recomputeTotals() {
        const subtotal = parseFloat($('input[name="field_subtotal_amount"]').val() || '0');
        const discount = parseFloat($('input[name="field_discount"]').val() || '0');
        const taxRate =
          parseFloat(drupalSettings?.stitchlynTax || Drupal.settings?.stitchlynTax || '0');
        const tax = (subtotal * taxRate) / 100.0;
        const total = subtotal - discount + tax;
        $('input[name="field_tax_amount"]').val(tax.toFixed(2));
        $('input[name="field_total_amount"]').val(total.toFixed(2));
      }
    },
  };
})(jQuery, Drupal, once);
