(function ($, Drupal, drupalSettings, once) {
  Drupal.behaviors.workOrder = {
    attach: function (context) {

      // Open modal
      $(document).on('click', '#add-workorder', function () {
        // Clear all inputs before showing modal
        $('#wo-line-item, #wo-unit, #wo-quantity, #wo-due-date, #wo-status, #wo-remarks').val('');
        $('#workOrderModalLabel').text('Add Work Order');
        $('#wo-save').text('Save Work Order').data('id', ''); // ensure no old ID is retained

        const modal = new bootstrap.Modal(document.getElementById('workOrderModal'));
        modal.show();
      });

      // Autocomplete: Line Item
      once('woLine', '#wo-line-item', context).forEach((el) => {
        const path = $(el).data('autocomplete-path');
        if (typeof $(el).autocomplete !== 'function') return;
        $(el).autocomplete({
          minLength: 1,
          source: function (request, response) {
            $.ajax({
              url: path,
              dataType: 'json',
              data: { q: request.term },
              success: function (data) {
                response($.map(data, (item) => ({
                  label: item.label,
                  value: item.value
                })));
              },
            });
          },
          focus: function (event, ui) {
            event.preventDefault();
            $(this).val(ui.item.label); // show label while focusing
          },
          select: function (event, ui) {
            event.preventDefault();
            $(this).val(ui.item.label);         // show label instead of ID
            $(this).data('id', ui.item.value);  // store ID separately
          },
        });
      });

      // Autocomplete: Unit
      once('woUnit', '#wo-unit', context).forEach((el) => {
        const path = $(el).data('autocomplete-path');
        if (typeof $(el).autocomplete !== 'function') return;
        $(el).autocomplete({
          minLength: 1,
          source: function (request, response) {
            $.ajax({
              url: path,
              dataType: 'json',
              data: { q: request.term },
              success: function (data) {
                response($.map(data, (item) => ({
                  label: item.label,
                  value: item.value
                })));
              },
            });
          },
          focus: function (event, ui) {
            event.preventDefault();
            $(this).val(ui.item.label); // show label while focusing
          },
          select: function (event, ui) {
            event.preventDefault();
            $(this).val(ui.item.label);         // show label instead of ID
            $(this).data('id', ui.item.value);  // store ID separately
          },
        });
      });

      // Autocomplete: Status
      once('woStatus', '#wo-status', context).forEach((el) => {
        const path = '/order-status/autocomplete';
        if (typeof $(el).autocomplete !== 'function') return;
        $(el).autocomplete({
          minLength: 1,
          source: function (request, response) {
            $.ajax({
              url: path,
              dataType: 'json',
              data: { q: request.term },
              success: function (data) {
                response($.map(data, (item) => ({
                  label: item.label,
                  value: item.value
                })));
              },
            });
          },
          focus: function (event, ui) {
            event.preventDefault();
            $(this).val(ui.item.label); // show label while focusing
          },
          select: function (event, ui) {
            event.preventDefault();
            $(this).val(ui.item.label);         // show label instead of ID
            $(this).data('id', ui.item.value);  // store ID separately
          },
        });
      });

      // ==================== AUTOCOMPLETE ID CAPTURE ====================
      // Capture the selected entity IDs for line item, unit, and status
      $(document).on('autocompleteselect', '#wo-line-item', function (event, ui) {
        $(this).data('id', ui.item.value);
      });

      $(document).on('autocompleteselect', '#wo-unit', function (event, ui) {
        $(this).data('id', ui.item.value);
      });

      $(document).on('autocompleteselect', '#wo-status', function (event, ui) {
        $(this).data('id', ui.item.value);
      });

      // ==================== SAVE WORK ORDER ====================
      once('woSave', '#wo-save', context).forEach((btn) => {
        $(btn).on('click', function () {
          const quotationId = window.location.pathname.split('/').pop();

          // Capture IDs from autocomplete selections (if selected)
          const lineItemId = $('#wo-line-item').data('id');
          const unitId = $('#wo-unit').data('id');
          const statusId = $('#wo-status').data('id');

          // Other fields
          const quantity = $('#wo-quantity').val();
          const dueDate = $('#wo-due-date').val();
          const remarks = $('#wo-remarks').val();

          // Validate before submit
          if (!lineItemId || !unitId || !statusId || !quantity || !dueDate) {
            alert('Please fill all required fields.');
            return;
          }

          $.ajax({
            url: `/quotation/${quotationId}/work-order/save`,
            type: 'POST',
            dataType: 'json',
            data: {
              line_item: lineItemId,
              unit: unitId,
              status: statusId,
              quantity: quantity,
              due_date: dueDate,
              remarks: remarks,
            },
            success: function (res) {
              if (res.status === 'success') {
                // ✅ Close modal immediately
                const modalEl = document.getElementById('workOrderModal');
                let modalInstance = bootstrap.Modal.getInstance(modalEl);
                if (!modalInstance) {
                  modalInstance = new bootstrap.Modal(modalEl);
                }
                modalInstance.hide();

                // 🔧 HARD-CLOSE fallback in case fade/backdrop lingers
                setTimeout(() => {
                  $('.modal-backdrop').remove();      // remove stray backdrop
                  $('body').removeClass('modal-open'); // restore scroll
                  $('body').css('padding-right', '');  // reset padding
                }, 400);

                // ✅ Replace table HTML
                $('#workorder-wrapper').html(res.html);

                // ✅ Rebind Drupal behaviors safely
                setTimeout(() => {
                  Drupal.attachBehaviors(document);
                }, 300);
              } else {
                alert(res.message || 'Error while saving work order.');
              }
            },
            error: function () {
              alert('Error: Could not save work order.');
            },
          });
        });
      });

      // ==================== VIEW WORK ORDER ====================
      once('woView', '.view-workorder', context).forEach((el) => {
        $(el).on('click', function () {
          const id = $(this).data('id');
          $.ajax({
            url: `/quotation/work-order/${id}/view`,
            type: 'GET',
            dataType: 'json',
            success: function (res) {
              if (res.status === 'success') {
                const d = res.data;
                $('#view-wo-title').text(d.title);
                $('#view-wo-line-item').text(d.line_item);
                $('#view-wo-unit').text(d.unit);
                $('#view-wo-quantity').text(d.quantity);
                $('#view-wo-due').text(d.expected_due_date);
                $('#view-wo-status').text(d.order_status);
                $('#view-wo-remarks').text(d.remarks || '—');

                const modal = new bootstrap.Modal(document.getElementById('workOrderViewModal'));
                modal.show();
              } else {
                alert('Unable to fetch work order details.');
              }
            },
            error: function () {
              alert('Error fetching work order details.');
            },
          });
        });
      });

      // ==================== EDIT WORK ORDER ====================
      once('woEdit', '.edit-workorder', context).forEach((el) => {
        $(el).on('click', function () {
          const id = $(this).data('id');
          $.ajax({
            url: `/quotation/work-order/${id}/view`,
            type: 'GET',
            dataType: 'json',
            success: function (res) {
              if (res.status === 'success') {
                const d = res.data;
                $('#edit-wo-title').val(d.title);
                $('#edit-wo-line-item').val(d.line_item);
                $('#edit-wo-unit').val(d.unit);
                $('#edit-wo-quantity').val(d.quantity);
                $('#edit-wo-due').val(d.expected_due_date);
                $('#edit-wo-status').val(d.order_status);
                $('#edit-wo-remarks').val(d.remarks);

                $('#save-wo-edit').data('id', id);
                const modal = new bootstrap.Modal(document.getElementById('workOrderEditModal'));
                modal.show();
              } else {
                alert('Unable to load work order details.');
              }
            },
            error: function () {
              alert('Error fetching work order details.');
            },
          });
        });
      });

      // ==================== SAVE EDITED WORK ORDER ====================
      once('woEditSave', '#save-wo-edit', context).forEach((btn) => {
        $(btn).on('click', function () {
          const id = $(this).data('id');
          const status = $('#edit-wo-status').val();
          const remarks = $('#edit-wo-remarks').val();

          $.ajax({
            url: `/quotation/work-order/${id}/update`,
            type: 'POST',
            dataType: 'json',
            data: { status: status, remarks: remarks },
            success: function (res) {
              if (res.status === 'success') {
                $('#workOrderEditModal').modal('hide');
                $('#workorder-wrapper').load(window.location.href + ' #workorder-wrapper > *', function () {
                  Drupal.attachBehaviors(document, Drupal.settings);
                });
              } else {
                alert(res.message || 'Update failed.');
              }
            },
            error: function () {
              alert('Error saving work order update.');
            },
          });
        });
      });

      // ==================== REATTACH FOR INVENTORY ====================
      once('invRebind', '#inventoryTabContent', context).forEach((el) => {
        $(document).ajaxComplete(function (event, xhr, settings) {
          if (
            settings.url.includes('/inventory-log/save') ||
            settings.url.includes('/inventory-log/remove') ||
            settings.url.includes('/work-order/save')
          ) {
            Drupal.attachBehaviors(document, Drupal.settings);
          }
        });
      });

    },
  };
})(jQuery, Drupal, drupalSettings, once);