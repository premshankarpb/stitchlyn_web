/**
 * Quotation edit UI: popup attributes, view/remove, and auto totals.
 */
(function ($, Drupal) {
  Drupal.behaviors.stitchlynQuotation = {
    attach(context) {
      const onceFn = Drupal.once || once;

      // Add Product
      onceFn('sqAddProduct', '.add-product-btn', context).forEach((el) => {
        $(el).on('click', function (e) {
          e.preventDefault();
          const product = $('#product-autocomplete').val();
          const quotation = $(this).data('quotation-id');
          if (!product) { alert('Select a product first'); return; }

          // Load popup
          $.get(Drupal.url(`quotation/ajax/attributes/${product}/${quotation}`), function (res) {
            const $dlg = $('<div class="sq-dialog"></div>').html(res.html);
            Drupal.dialog($dlg, { title: 'Add Product', width: 640 }).showModal();

            // Save inside popup
            $dlg.on('click', '#save-attr', function () {
              const payload = { attributes: {}, quantity: 1 };

              // Collect attribute inputs: attributes[bundle][field]
              $dlg.find('[name^="attributes"]').each(function () {
                const m = $(this).attr('name').match(/^attributes\[(.+?)\]\[(.+?)\]$/);
                if (m) {
                  const bundle = m[1]; const fname = m[2];
                  payload.attributes[bundle] = payload.attributes[bundle] || {};
                  payload.attributes[bundle][fname] = $(this).val();
                }
              });

              payload.quantity = parseInt($dlg.find('[name="quantity"]').val() || '1', 10);

              $.ajax({
                url: Drupal.url(`quotation/ajax/save-item/${product}/${quotation}`),
                method: 'POST',
                data: JSON.stringify(payload),
                contentType: 'application/json',
                success() {
                  $dlg.dialog('close');
                  // Clear the product field (requirement)
                  $('#product-autocomplete').val('');
                  refreshTableAndTotals(quotation);
                },
                error() { alert('Failed to save item'); }
              });
            });
          });
        });
      });

      // Helper: refresh table + totals
      function refreshTableAndTotals(qid) {
        $.get(Drupal.url(`quotation/ajax/line-items/${qid}`), function (res) {
          $('#line-items-wrapper').html(res.html);
          // Update subtotal from server response
          $('input[name="field_subtotal_amount"]').val(parseFloat(res.subtotal || 0).toFixed(2));
          recomputeTotals();     // uses tax% + discount on client
          bindTableActions(qid); // (re)bind View/Remove
        });
      }

      // Bind actions after table loads
      function bindTableActions(qid) {
        $('.view-item').off().on('click', function () {
          const id = $(this).data('id');
          $.get(Drupal.url(`quotation/ajax/view-item/${id}`), function (res) {
            Drupal.dialog($('<div></div>').html(res.html), { title: 'Item Details', width: 560 }).showModal();
          });
        });

        $('.remove-item').off().on('click', function () {
          const id = $(this).data('id');
          if (!confirm('Remove this item?')) return;
          $.get(Drupal.url(`quotation/ajax/delete-item/${id}`), function () {
            refreshTableAndTotals(qid);
          });
        });
      }

      // Discount change → totals recalc
      onceFn('sqDiscount', 'input[name="field_discount"]', context).forEach((el) => {
        $(el).on('input', function () {
          recomputeTotals();
        });
      });

      // Compute Tax + Total on client (uses tax% from drupalSettings if provided)
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

      // Bind table actions on first load.
      bindTableActions($('[data-quotation-id]').data('quotation-id'));
    }
  };
})(jQuery, Drupal);