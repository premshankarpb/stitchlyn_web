/**
 * Quotation form behavior:
 * - Opens attribute popup for the selected product.
 * - Re-attaches Drupal behaviors so autocomplete works inside the popup.
 * - Serializes paragraph form inputs into a flat {field_*: value} map.
 * - Resolves taxonomy term IDs (from "Blue (15)" → 15).
 * - Sends via AJAX and refreshes the line-items table + totals.
 * - Automatically loads existing line items on page load (only once).
 */
(function ($, Drupal, once) {
  Drupal.behaviors.stitchlynQuotation = {
    attach(context) {

      // --- Autocomplete handling for product field ---
      once('sqAutocomplete', '#product-autocomplete', context).forEach((el) => {
        $(el).on('autocompleteselect', function (event, ui) {
          if (ui && ui.item && ui.item.value) {
            const m = ui.item.value.match(/\((\d+)\)$/);
            if (m) $(this).data('entity-id', m[1]);
          }
        });

        // Clear stored ID if user edits manually.
        $(el).on('input', function () {
          $(this).removeData('entity-id');
        });
      });

      // --- Open popup to add a product ---
      once('sqAddProduct', '.add-product-btn', context).forEach((el) => {
        $(el).on('click', function (e) {
          e.preventDefault();

          const product = $('#product-autocomplete').data('entity-id');
          const quotation = $(this).data('quotation-id');

          if (!product) {
            alert('Please select a product first.');
            return;
          }

          $.get(Drupal.url(`quotation/ajax/attributes/${product}/${quotation}`), function (res) {
            if (res.status !== 'success') {
              alert(res.message || 'Failed to load the attribute form.');
              return;
            }

            const $dlg = $('<div class="sq-dialog"></div>').html(res.html);
            const dialog = Drupal.dialog($dlg, { title: 'Add Product', width: 700 });
            dialog.showModal();

            // Enable autocomplete etc. inside popup.
            Drupal.attachBehaviors($dlg[0]);

            // Save button inside popup.
            $dlg.off('click.saveAttr').on('click.saveAttr', '#save-attr', function (e) {
              e.preventDefault();

              // --- Build payload ---
              const payload = { attributes: {}, quantity: 1 };

              // ✅ Read only the named quantity field.
              const qty = parseFloat($dlg.find('#sq-quantity').val()) || 1;
              payload.quantity = qty;

              // Collect other paragraph fields.
              $dlg.find('input, select, textarea').each(function () {
                const name = $(this).attr('name');
                const val = $(this).val();
                if (!name || val === '' || val === null || typeof val === 'undefined') return;

                // Skip quantity field.
                if (name === 'sq_quantity') return;

                // Extract base field name: field_colour[0][target_id] → field_colour
                const m = name.match(/^(field_[a-z0-9_]+)/i);
                if (!m) return;
                const base = m[1];

                // Handle taxonomy autocomplete values like "Blue (15)".
                let cleanVal = val;
                const match = String(val).match(/\((\d+)\)$/);
                if (match) {
                  cleanVal = match[1]; // use numeric ID directly
                }

                payload.attributes[base] = cleanVal;
              });

              // --- AJAX save ---
              $.ajax({
                url: Drupal.url(`quotation/ajax/save-item/${product}/${quotation}`),
                method: 'POST',
                data: JSON.stringify(payload),
                contentType: 'application/json',
                success(resp) {
                  if (resp.status === 'success') {
                    $dlg.dialog('close');
                    $('#product-autocomplete').val('').removeData('entity-id');
                    refreshTableAndTotals(quotation); // ✅ Reload table after save
                  } else {
                    alert(resp.message || 'Failed to save item.');
                  }
                },
                error(xhr) {
                  console.error(xhr.responseText);
                  alert('Failed to save item.');
                },
              });
            });
          });
        });
      });

      // --- Refresh line-items table & totals ---
      function refreshTableAndTotals(qid) {
        $.get(Drupal.url(`quotation/ajax/line-items/${qid}`), function (res) {
          if (res.status !== 'success') return;

          $('#line-items-wrapper').html(res.html);
          $('input[name="field_subtotal_amount"]').val(parseFloat(res.subtotal || 0).toFixed(2));
          recomputeTotals();
          bindTableActions(qid);
          Drupal.attachBehaviors($('#line-items-wrapper')[0]);
        });
      }

      // --- Bind view/remove actions ---
      function bindTableActions(qid) {
        // View popup
        $('.view-item').off('click').on('click', function (e) {
          e.preventDefault();
          const id = $(this).data('id');
          $.get(Drupal.url(`quotation/ajax/view-item/${id}`), function (res) {
            if (res.status !== 'success') return;
            const $v = $('<div></div>').html(res.html);
            Drupal.dialog($v, { title: 'Item Details', width: 550 }).showModal();
          });
        });

        // Remove item
        $('.remove-item').off('click').on('click', function (e) {
          e.preventDefault();
          const id = $(this).data('id');
          if (!confirm('Are you sure you want to remove this item?')) return;
          $.get(Drupal.url(`quotation/ajax/delete-item/${id}`), function () {
            refreshTableAndTotals(qid); // ✅ Reload only after delete
          });
        });
      }

      // --- Totals on discount change ---
      once('sqDiscount', 'input[name="field_discount"]', context).forEach((el) => {
        $(el).on('input', function () {
          recomputeTotals();
        });
      });

      // --- Client-side tax/total math ---
      function recomputeTotals() {
        const subtotal = parseFloat($('input[name="field_subtotal_amount"]').val() || '0');
        const discount = parseFloat($('input[name="field_discount"]').val() || '0');
        const taxRate = parseFloat(
          (window.drupalSettings && drupalSettings.stitchlynTax) ||
          (window.Drupal && Drupal.settings && Drupal.settings.stitchlynTax) ||
          '0'
        );
        const tax = (subtotal * taxRate) / 100.0;
        const total = subtotal - discount + tax;
        $('input[name="field_tax_amount"]').val(tax.toFixed(2));
        $('input[name="field_total_amount"]').val(total.toFixed(2));
      }

      // --- Initial table load: run only once per page load ---
      once('sqInitialTableLoad', 'body', context).forEach(() => {
        const qid = $('[data-quotation-id]').data('quotation-id');
        if (qid) {
          refreshTableAndTotals(qid); // ✅ Load once on page load
        }
      });
    },
  };
})(jQuery, Drupal, once);