/**
 * Quotation form behavior:
 * - Handles product popup, attribute saving, and line-item refresh.
 * - Auto-calculates subtotal, discount, tax, and total.
 * - Updates both visible fields and hidden mirrors for backend save.
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
            Drupal.attachBehaviors($dlg[0]);

            $dlg.off('click.saveAttr').on('click.saveAttr', '#save-attr', function (e) {
              e.preventDefault();

              const payload = { attributes: {}, quantity: 1 };
              const qty = parseFloat($dlg.find('#sq-quantity').val()) || 1;
              payload.quantity = qty;

              $dlg.find('input, select, textarea').each(function () {
                const name = $(this).attr('name');
                const val = $(this).val();
                if (!name || val === '' || val === null) return;
                if (name === 'sq_quantity') return;

                const m = name.match(/^(field_[a-z0-9_]+)/i);
                if (!m) return;
                const base = m[1];

                let cleanVal = val;
                const match = String(val).match(/\((\d+)\)$/);
                if (match) cleanVal = match[1];
                payload.attributes[base] = cleanVal;
              });

              $.ajax({
                url: Drupal.url(`quotation/ajax/save-item/${product}/${quotation}`),
                method: 'POST',
                data: JSON.stringify(payload),
                contentType: 'application/json',
                success(resp) {
                  if (resp.status === 'success') {
                    $dlg.dialog('close');
                    $('#product-autocomplete').val('').removeData('entity-id');
                    refreshTableAndTotals(quotation);
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
          const subtotal = parseFloat(res.subtotal || 0);
          $('input[name="field_subtotal_amount"]').val(subtotal.toFixed(2));
          $('#hidden-subtotal').val(subtotal.toFixed(2));

          recomputeTotals();
          bindTableActions(qid);
          Drupal.attachBehaviors($('#line-items-wrapper')[0]);
        });
      }

      // --- Bind view/remove actions ---
      function bindTableActions(qid) {
        $('.view-item').off('click').on('click', function (e) {
          e.preventDefault();
          const id = $(this).data('id');
          $.get(Drupal.url(`quotation/ajax/view-item/${id}`), function (res) {
            if (res.status !== 'success') return;
            const $v = $('<div></div>').html(res.html);
            Drupal.dialog($v, { title: 'Item Details', width: 550 }).showModal();
          });
        });

        $('.remove-item').off('click').on('click', function (e) {
          e.preventDefault();
          const id = $(this).data('id');
          if (!confirm('Are you sure you want to remove this item?')) return;
          $.get(Drupal.url(`quotation/ajax/delete-item/${id}`), function () {
            refreshTableAndTotals(qid);
          });
        });
      }

      // --- Totals recompute when discount changes ---
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

        const taxableBase = Math.max(0, subtotal - discount);
        const tax = (taxableBase * taxRate) / 100.0;
        const total = taxableBase + tax;

        // Update visible fields
        $('input[name="field_tax_amount"]').val(tax.toFixed(2));
        $('input[name="field_total_amount"]').val(total.toFixed(2));

        // 🔥 Update hidden mirrors for backend submission
        $('#hidden-subtotal').val(subtotal.toFixed(2));
        $('#hidden-tax').val(tax.toFixed(2));
        $('#hidden-total').val(total.toFixed(2));
      }

      // --- Initial table load (only once) ---
      once('sqInitialTableLoad', 'body', context).forEach(() => {
        const qid = $('[data-quotation-id]').data('quotation-id');
        if (qid) refreshTableAndTotals(qid);
      });
    },
  };
})(jQuery, Drupal, once);