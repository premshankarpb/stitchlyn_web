(function ($, Drupal, drupalSettings, once) {

  Drupal.behaviors.inventoryLog = {
    attach: function (context) {

      // ---------------------------
      // ADD LOG BUTTON → OPEN MODAL
      // ---------------------------
      once('invAdd', '#add-inventory-log', context).forEach(function (el) {
        $(el).on('click', function () {
          const modal = document.getElementById('inventoryModal');
          if (modal) {
            const modalInstance = new bootstrap.Modal(modal);
            modalInstance.show();
          }
        });
      });

      // ---------------------------
      // ENABLE AUTOCOMPLETE FIELD
      // ---------------------------
      once('invAuto', '#inventory-item-autocomplete', context).forEach(function (el) {
        const $input = $(el);
        const path = $input.data('autocomplete-path');

        // ensure jQuery UI autocomplete is loaded
        if (typeof $input.autocomplete !== 'function') {
          console.warn('⚠️ jQuery UI Autocomplete not loaded. Check library dependencies.');
          return;
        }

        $input
          .attr('autocomplete', 'off')
          .autocomplete({
            minLength: 1,
            autoFocus: true,
            appendTo: '#inventoryModal',      // keep menu inside modal
            source: function (request, response) {
              $.ajax({
                url: path,
                dataType: 'json',
                data: { q: request.term },
                success: function (data) {
                  response($.map(data, function (item) {
                    // expecting {label: "...", value: "..."}
                    return { label: item.label, value: item.value };
                  }));
                },
                error: function () {
                  console.error('❌ Autocomplete request failed.');
                }
              });
            },

            // show label while navigating list
            focus: function (event, ui) {
              $input.val(ui.item.label);
              return false;
            },

            // when user selects an item
            select: function (event, ui) {
              const title = ui.item.value;

              // make sure text box shows the chosen label
              $input.val(ui.item.label);

              // fetch item details from backend
              $.ajax({
                url: '/inventory/item/details?title=' + encodeURIComponent(title),
                dataType: 'json',
                success: function (res) {
                  if (res.status === 'success') {
                    $('#inventory-item-details').show();
                    $('#inv-name').text(res.data.name);
                    $('#inv-cost').text(res.data.cost);
                    $('#inv-unit').text(res.data.unit);
                    $('#inv-stock').text(res.data.stock);

                    // reset total visibility
                    $('#inventory-total').hide();
                    $('#inv-total-cost').text('0.00');
                  } else {
                    alert('Item not found.');
                  }
                },
                error: function () {
                  alert('Error fetching item details.');
                }
              });

              return false; // prevent default behaviour
            }
          });
      });

      // ---------------------------
      // DYNAMIC TOTAL COST UPDATER
      // ---------------------------
      once('invQty', '#inventory-quantity', context).forEach(function (el) {
        $(el).on('input', function () {
          const qty = parseFloat($(this).val()) || 0;
          const cost = parseFloat($('#inv-cost').text()) || 0;
          const total = qty * cost;

          if (total > 0) {
            $('#inv-total-cost').text(total.toFixed(2));
            $('#inventory-total').show();
          } else {
            $('#inventory-total').hide();
          }
        });
      });

      // ---------------------------
      // SAVE INVENTORY LOG ENTRY
      // ---------------------------
      once('invSave', '#save-inventory-log', context).forEach(function (el) {
        el.addEventListener('click', function () {
          const data = {
            item: $('#inventory-item-autocomplete').val(),
            quantity: $('#inventory-quantity').val(),
            quotation_id: drupalSettings.quotationId,
          };

          if (!data.item || !data.quantity) {
            alert('Please fill all required fields.');
            return;
          }

          $.ajax({
            type: 'POST',
            url: '/quotation/' + drupalSettings.quotationId + '/inventory-log/save',
            data: JSON.stringify(data),
            contentType: 'application/json',
            success: function (res) {
              if (res.status === 'success') {
                alert(res.message);

                const modalEl = document.getElementById('inventoryModal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();

                const tableBody = $('.inventory-section table tbody');
                if (tableBody.length) {
                  tableBody.append(res.html);

                  const currentTotal = parseFloat($('#inventory-total-sum').text() || 0);
                  const newTotal = currentTotal + parseFloat(res.total);
                  $('#inventory-total-sum').text(newTotal.toFixed(2));
                } else {
                  $('.inventory-section').html(`
                    <div class="d-flex justify-content-between align-items-center mb-3">
                      <h4>Inventory Logs</h4>
                      <button id="add-inventory-log" class="btn btn-success btn-sm">+ Add Log</button>
                    </div>
                    <table class="table table-bordered table-striped align-middle">
                      <thead class="table-light">
                        <tr>
                          <th>Inventory Item</th>
                          <th>Current Stock</th>
                          <th>Unit of Measure</th>
                          <th>Cost Price</th>
                          <th>Quantity</th>
                          <th>Total</th>
                          <th>Action</th>
                        </tr>
                      </thead>
                      <tbody>${res.html}</tbody>
                    </table>
                    <div class="text-end fw-bold fs-5 mt-2">
                      Total Cost: ₹<span id="inventory-total-sum">${res.total.toFixed(2)}</span>
                    </div>
                  `);
                }

                // Reset form
                $('#inventory-item-autocomplete').val('');
                $('#inventory-quantity').val(1);
                $('#inventory-item-details').hide();
                $('#inventory-total').hide();
              } else {
                alert(res.message);
              }
            },
            error: function () {
              alert('Failed to save inventory log.');
            },
          });
        });
      });

      // ================== DELETE INVENTORY LOG (AJAX) ==================
      $(document).off('click.removeLog').on('click.removeLog', '.remove-log', function (e) {
        e.preventDefault();
        const btn = $(this);
        const id = btn.data('id');
        const row = btn.closest('tr');

        if (!confirm('Are you sure you want to delete this log?')) return;

        $.ajax({
          type: 'POST',
          url: '/inventory-log/' + id + '/delete',
          success: function (res) {
            if (res.status === 'success') {
              const rowTotal = parseFloat(
                row.find('td:nth-child(6)').text().replace(/[₹,]/g, '')
              ) || 0;
              row.fadeOut(300, function () {
                $(this).remove();

                const currentTotal = parseFloat($('#inventory-total-sum').text() || 0);
                const newTotal = (currentTotal - rowTotal).toFixed(2);
                $('#inventory-total-sum').text(newTotal);

                if ($('.inventory-section table tbody tr').length === 0) {
                  $('.inventory-section table').replaceWith('<p>No inventory logs found.</p>');
                  $('.text-end.fw-bold').remove();
                }
              });
            } else {
              alert(res.message);
            }
          },
          error: function () {
            alert('Error deleting inventory log.');
          },
        });
      });

    } // end attach
  };

})(jQuery, Drupal, drupalSettings, once);

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

              const formData = new FormData();
              const qty = parseFloat($dlg.find('#sq-quantity').val()) || 1;
              formData.append('quantity', qty);

              // Include all field values (except files, handled separately)
              $dlg.find('input, select, textarea').each(function () {
                const name = $(this).attr('name');
                if (!name || name === 'sq_quantity') return;

                const type = $(this).attr('type');
                if (type === 'file') {
                  const file = this.files[0];
                  if (file) formData.append(name, file);
                } else {
                  const val = $(this).val();
                  if (val !== '' && val !== null) {
                    const match = String(val).match(/\((\d+)\)$/);
                    const cleanVal = match ? match[1] : val;
                    formData.append(name, cleanVal);
                  }
                }
              });

              $.ajax({
                url: Drupal.url(`quotation/ajax/save-item/${product}/${quotation}`),
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
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
          '18'
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