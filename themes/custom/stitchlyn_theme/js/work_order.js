(function ($, Drupal, drupalSettings, once) {

  Drupal.behaviors.workOrder = {
    attach: function (context) {

      // ===================== OPEN ADD WORK ORDER MODAL =====================
      $(document).on('click', '#add-workorder', function () {

        // Reset form
        $('#wo-line-item').val('').data('id', '');
        $('#wo-unit').val('');
        $('#wo-quantity').val('');
        $('#wo-due-date').val('');
        $('#wo-date-of-completion').val('');
        $('#wo-assignee').val('');
        $('#wo-status').val('');
        $('#wo-remarks').val('');

        $('#workOrderModalLabel').text('Add Work Order');
        $('#wo-save').text('Save Work Order').data('id', '');

        const modal = new bootstrap.Modal(document.getElementById('workOrderModal'));
        modal.show();
      });

      // ===================== AUTOCOMPLETE: LINE ITEM (ON MODAL SHOW) =====================
      $(document).on('shown.bs.modal', '#workOrderModal', function () {

        const el = $('#wo-line-item');

        // Prevent multiple initializations
        if (el.data('autocomplete-initialized')) {
          return;
        }

        const path = el.data('autocomplete-path');

        if (!$.fn.autocomplete) {
          console.warn("jQuery UI autocomplete missing");
          return;
        }

        el.autocomplete({
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
            el.val(ui.item.label);
          },
          select: function (event, ui) {
            event.preventDefault();
            el.val(ui.item.label);
            el.data('id', ui.item.value);
          }
        });

        el.data('autocomplete-initialized', true);
      });

      // Capture selected autocomplete ID
      $(document).on('autocompleteselect', '#wo-line-item', function (event, ui) {
        $(this).data('id', ui.item.value);
      });


      // ===================== SAVE WORK ORDER =====================
      once('woSave', '#wo-save', context).forEach((btn) => {
        $(btn).on('click', function () {

          const quotationId = window.location.pathname.split('/').pop();

          const lineItemId = $('#wo-line-item').data('id');
          const unitId = $('#wo-unit').val();
          const statusId = $('#wo-status').val();
          const quantity = $('#wo-quantity').val();
          const assignee = $('#wo-assignee').val();
          const dateOfCompletion = $('#wo-date-of-completion').val();
          const dueDate = $('#wo-due-date').val();
          const remarks = $('#wo-remarks').val();

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
              date_of_completion: dateOfCompletion,
              assignee: assignee,
              remarks: remarks,
              assignee: assignee,
              date_of_completion: dateOfCompletion,
            },
            success: function (res) {
              if (res.status === 'success') {

                const modalEl = document.getElementById('workOrderModal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                modal.hide();

                modalEl.addEventListener('hidden.bs.modal', function () {
                  $('#workorder-wrapper').html(res.html);
                  $('.modal-backdrop').hide();
                  Drupal.attachBehaviors(document, drupalSettings);
                }, { once: true });

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


      // ===================== VIEW WORK ORDER =====================
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
                $('#view-wo-date-of-completion').text(d.date_of_completion);
                $('#view-wo-assignee').text(d.assignee);
                $('#view-wo-status').text(d.order_status);
                $('#view-wo-remarks').html(d.remarks || '—');
                 // ---- Work order logs ----
                const logs = d.work_order_logs || [];
                let logsHtml = '';

                if (Array.isArray(logs) && logs.length > 0) {
                  logs.forEach(function (log) {
                    // log.created is a Unix timestamp (seconds)
                    const dt = new Date(log.created * 1000);

                    const dd   = String(dt.getDate()).padStart(2, '0');
                    const mm   = String(dt.getMonth() + 1).padStart(2, '0'); // months 0–11
                    const yyyy = dt.getFullYear();
                    const hh   = String(dt.getHours()).padStart(2, '0');
                    const min  = String(dt.getMinutes()).padStart(2, '0');

                    const formatted = `${dd}-${mm}-${yyyy}, ${hh}:${min}`;

                    // log.log_data already has the exact HTML from body → inject as HTML
                    logsHtml += `
                      <div class="wo-log-entry mb-2">
                        <div class="wo-log-meta"><strong>${formatted}</strong></div>
                        <div class="wo-log-body">${log.log_data}</div>
                      </div>
                    `;
                  });
                } else {
                  logsHtml = '—';
                }
                $('#view-wo-logs').html(logsHtml);
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


      // ===================== EDIT WORK ORDER =====================
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
                $('#edit-wo-date-of-completion').val(d.date_of_completion);
                $('#edit-wo-assignee').val(d.assignee_id);
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


      // ===================== SAVE EDITED WORK ORDER =====================
      once('woEditSave', '#save-wo-edit', context).forEach((btn) => {
        $(btn).on('click', function () {

          const id = $(this).data('id');
          const status = $('#edit-wo-status').val();
          const remarks = $('#edit-wo-remarks').val();
          const dateOfCompletion = $('#edit-wo-date-of-completion').val();
          const assignee = $('#edit-wo-assignee').val();

          $.ajax({
            url: `/quotation/work-order/${id}/update`,
            type: 'POST',
            dataType: 'json',
            data: { status: status, remarks: remarks,date_of_completion: dateOfCompletion, assignee: assignee },
            success: function (res) {

              if (res.status === 'success') {

                const modalEl = document.getElementById('workOrderEditModal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                modal.hide();

                modalEl.addEventListener('hidden.bs.modal', function () {
                  $('#workorder-wrapper').html(res.html);
                  Drupal.attachBehaviors(document, drupalSettings);
                }, { once: true });

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


      // ==================== REATTACH AFTER AJAX ====================
      once('invRebind', '#inventoryTabContent', context).forEach(() => {
        $(document).ajaxComplete(function (event, xhr, settings) {
          const url = settings?.url || '';
          if (
            url.includes('/inventory-log/save') ||
            url.includes('/inventory-log/remove') ||
            url.includes('/work-order/save')
          ) {
            Drupal.attachBehaviors(document, drupalSettings);
          }
        });
      });

    },
  };

})(jQuery, Drupal, drupalSettings, once);