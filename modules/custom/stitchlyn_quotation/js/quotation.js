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