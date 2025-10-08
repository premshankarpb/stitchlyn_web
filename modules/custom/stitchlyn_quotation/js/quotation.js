(function ($, Drupal) {
  Drupal.behaviors.stitchlynQuotation = {
    attach: function (context) {

      // Open popup
      $('.add-product-btn', context).once('sqAddProduct').on('click', function (e) {
        e.preventDefault();
        const product = $('#product-autocomplete').val();
        const quotation = $(this).data('quotation-id');
        if (!product) { alert('Please select a product.'); return; }

        $.get(Drupal.url('quotation/ajax/attributes/' + product + '/' + quotation), function (res) {
          const $dlg = $('<div class="sq-dialog"></div>').html(res.html);
          Drupal.dialog($dlg, { title: 'Add Product', width: 640 }).showModal();

          // Save inside popup
          $dlg.on('click', '#save-attr', function () {
            const payload = {
              quantity: parseInt($dlg.find('input[name="quantity"]').val() || '1', 10)
            };
            $.ajax({
              url: Drupal.url('quotation/ajax/save-item/' + product + '/' + quotation),
              method: 'POST',
              data: JSON.stringify(payload),
              contentType: 'application/json',
              success: function (resp) {
                if (resp.status === 'ok') {
                  $dlg.dialog('close');
                  // Refresh table
                  $.get(Drupal.url('quotation/ajax/line-items/' + quotation), function (res2) {
                    $('#line-items-wrapper').html(res2.html);
                    // Recompute totals client-side using current tax config
                    recomputeTotals();
                  });
                } else {
                  alert('Failed to save item.');
                }
              },
              error: function () { alert('Failed to save item.'); }
            });
          });
        });
      });

      // Recompute when discount changes
      $('input[name="field_discount"]', context).once('sqDisc').on('input', function () {
        recomputeTotals();
      });

      function recomputeTotals() {
        const subtotal = parseFloat($('input[name="field_subtotal_amount"]').val() || '0');
        const discount = parseFloat($('input[name="field_discount"]').val() || '0');
        const taxRate = parseFloat(Drupal.settings?.stitchlynTax || drupalSettings?.stitchlynTax || '0');
        const tax = (subtotal * taxRate) / 100.0;
        const total = subtotal - discount + tax;
        $('input[name="field_tax_amount"]').val(tax.toFixed(2));
        $('input[name="field_total_amount"]').val(total.toFixed(2));
      }
    }
  };
})(jQuery, Drupal);
