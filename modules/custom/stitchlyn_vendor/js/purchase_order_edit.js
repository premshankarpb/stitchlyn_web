(function ($, Drupal, once) {
  'use strict';

  /** -------------------------------
   *  Utility helpers
   *  -----------------------------*/
  function getNid() {
    return $('#stitchlyn-po-edit-form').data('po-nid');
  }

  function toNumber(value) {
    const num = parseFloat(value);
    return isNaN(num) ? 0 : num;
  }

  function recalcTotal() {
    const rate = parseFloat($('[name="rate"]').val() || 0);
    const qty = parseFloat($('[name="quantity"]').val() || 0);
    $('[name="total"]').val((rate * qty).toFixed(2));
  }

  function attachRecalcHandlers() {
    $('[name="rate"], [name="quantity"]')
      .off('input.recalc change.recalc keyup.recalc')
      .on('input.recalc change.recalc keyup.recalc', recalcTotal);
  }

  function updateSummaryFields(summary) {
    if (!summary) return;
    $('[name="field_subtotal_amount"]').val(parseFloat(summary.subtotal).toFixed(2));
    $('[name="field_tax_amount"]').val(parseFloat(summary.tax).toFixed(2));
    $('[name="field_total_amount"]').val(parseFloat(summary.total).toFixed(2));
  }

  function loadItems() {
    const nid = getNid();
    if (!nid) return;
    $.get(`/dashboard/purchase-order/${nid}/items`, (res) => {
      if (res?.status === 'success') {
        $('#po-items-wrapper').html(res.html);
        updateSummaryFields(res.summary);
      }
    });
  }

  function showMessage(message, type = 'success') {
    const $msg = $(`<div class="po-toast po-toast-${type}">${message}</div>`)
      .appendTo('body')
      .hide()
      .fadeIn(200);
    setTimeout(() => $msg.fadeOut(300, () => $msg.remove()), 2000);
  }

  /** -------------------------------
   *  Modal handling
   *  -----------------------------*/
  function openModal(mode, data = {}) {
    const $modal = $('#po-item-modal');
    const $form = $('#po-item-modal');
    if (!$modal.length) return;

    // Reset form and enable inputs
    $form.find('input, textarea').each(function () {
      $(this).val('').prop('disabled', false);
    });
    $('#po-inventory-nid').val('');
    $('#po-item-save').show();

    // Set modal title
    const title =
      mode === 'edit' ? 'Edit Item' :
      mode === 'view' ? 'View Item' :
      'Add Item';
    $('#po-item-modal-title').text(title);

    // Fill data if available
    if (data && Object.keys(data).length > 0) {
      $form.find('[name="item_id"]').val(data.id || data.item_id || '');
      $form.find('[name="item_reference"]').val(data.title || data.item_reference || '');
      $form.find('[name="rate"]').val(data.rate || '');
      $form.find('[name="quantity"]').val(data.quantity || '');
      $form.find('[name="total"]').val(
        (toNumber(data.rate) * toNumber(data.quantity)).toFixed(2)
      );
      $form.find('[name="remarks"]').val(data.remarks || '');
    }

    // Handle mode-specific behavior
    if (mode === 'view') {
      $form.find('input, textarea').prop('disabled', true);
      $('#po-item-save').hide();
    } else if (mode === 'edit') {
      // Disable only inventory field in edit mode
      $form.find('.inventory-autocomplete').prop('disabled', true);
      attachRecalcHandlers();
      $('#po-item-save').show();
    } else {
      attachRecalcHandlers();
      $('#po-item-save').show();
    }

    // Open Bootstrap modal safely
    try {
      if (typeof $modal.modal === 'function') {
        $modal.modal({ backdrop: 'static', keyboard: true }).modal('show');
      } else {
        // fallback if bootstrap not loaded
        $modal.show();
      }
    } catch (err) {
      console.warn('Bootstrap modal not available, fallback to .show()');
      $modal.show();
    }
  }

  function closeModal() {
    const $modal = $('#po-item-modal');
    const $form = $('#po-item-modal');

    // Reset all fields and re-enable inputs
    $form.find('input, textarea').val('').prop('disabled', false);
    $('#po-inventory-nid').val('');
    $('#po-item-save').show();

    // Safely close the modal (Bootstrap or fallback)
    try {
      if (typeof $modal.modal === 'function') {
        $modal.modal('hide');
      } else {
        $modal.hide();
      }
    } catch (err) {
      $modal.hide();
    }

    // Clean up any remaining overlay or scroll lock
    $('.modal-backdrop').remove();
    $('body').removeClass('modal-open').css('overflow', '');

    // Reload items table after closing (refresh data view)
    loadItems();
  }

  function postItem(url, payload, cb) {
    $.ajax({
      url: url,
      method: 'POST',
      data: payload,
      success: function (res) {
        if (res.status === 'success') {
          $('#po-items-wrapper').html(res.html);
          updateSummaryFields(res.summary);
          if (cb) cb();
          showMessage('Operation successful!', 'success');
        } else {
          alert(res.message || 'Operation failed');
        }
      },
      error: function () {
        alert('Request failed');
      },
    });
  }

  /** -------------------------------
   *  Drupal behavior
   *  -----------------------------*/
  Drupal.behaviors.stitchlynPoEdit = {
    attach: function (context) {

      // ✅ Hide modal safely on page load
      once('po-hide-modal', context).forEach(() => {
        const $modal = $('#po-item-modal');
        if ($modal.length) {
          try {
            if (typeof $modal.modal === 'function') {
              $modal.modal('hide');
            } else {
              $modal.hide();
            }
          } catch (e) {
            $modal.hide();
          }
        }
      });

      // Load purchase order items
      once('po-init', context).forEach(() => loadItems());

      // Add item button
      once('po-add', '.po-add-item, .po-add-item-float', context).forEach((el) => {
        $(el).on('click', (e) => {
          e.preventDefault();
          openModal('add');
        });
      });

      // Cancel modal
      once('po-cancel', '#po-item-cancel', context).forEach((el) => {
        $(el).on('click', (e) => {
          e.preventDefault();
          closeModal();
        });
      });

      // Inventory autocomplete
      once('inventory-autocomplete', '.inventory-autocomplete', context).forEach((el) => {
        const $el = $(el);
        $el.autocomplete({
          minLength: 2,
          appendTo: '#po-item-modal',
          source: function (request, response) {
            $.getJSON('/inventory-item/autocomplete', { q: request.term }, function (data) {
              response(
                $.map(data, function (item) {
                  return {
                    label: item.label,
                    value: item.label,
                    nid: item.nid,
                    rate: item.rate,
                  };
                })
              );
            });
          },
          select: function (event, ui) {
            $('#po-inventory-nid').val(ui.item.nid);
            $('[name="rate"]').val(ui.item.rate);
            recalcTotal();
          },
        }).autocomplete('instance')._renderItem = function (ul, item) {
          return $('<li>')
            .append(`<div><strong>${item.label}</strong><br><small>Rate: ₹${item.rate}</small></div>`)
            .appendTo(ul);
        };
      });

      // Save or update item
      once('po-save', '#po-item-save', context).forEach((el) => {
        $(el).on('click', function (e) {
          e.preventDefault();
          const nid = getNid();
          const itemId = $('[name="item_id"]').val();
          const payload = {
            inventory_nid: $('#po-inventory-nid').val(),
            item_reference: $('[name="item_reference"]').val(),
            rate: $('[name="rate"]').val(),
            quantity: $('[name="quantity"]').val(),
            remarks: $('[name="remarks"]').val(),
          };
          const url = itemId
            ? `/dashboard/purchase-order/${nid}/item/${itemId}/update`
            : `/dashboard/purchase-order/${nid}/item/add`;
          postItem(url, payload, closeModal);
        });
      });

      /** -----------------------------
       *  Prevent duplicate handlers
       *  -----------------------------*/
      once('po-item-handlers', 'body', context).forEach(() => {

        // --- View Item ---
        $(document)
          .off('click.poView', '.po-item-view')
          .on('click.poView', '.po-item-view', function (e) {
            e.preventDefault();
            const itemId = $(this).data('id');
            const nid = $('[data-po-nid]').attr('data-po-nid');
            $.ajax({
              url: `/dashboard/purchase-order/${nid}/item/${itemId}/json?_format=json`,
              type: 'GET',
              dataType: 'json',
              success: function (data) {
                openModal('view', data);
              },
              error: function () {
                alert('Unable to load item details.');
              },
            });
          });

        // --- Edit Item ---
        $(document)
          .off('click.poEdit', '.po-item-edit')
          .on('click.poEdit', '.po-item-edit', function (e) {
            e.preventDefault();
            const itemId = $(this).data('id');
            const nid = $('[data-po-nid]').attr('data-po-nid');
            $.ajax({
              url: `/dashboard/purchase-order/${nid}/item/${itemId}/json?_format=json`,
              type: 'GET',
              dataType: 'json',
              success: function (data) {
                openModal('edit', data);
              },
              error: function () {
                alert('Unable to load item details.');
              },
            });
          });
      });

      // Remove item
      $('#po-items-wrapper')
        .off('click.poDelete')
        .on('click.poDelete', '.po-item-remove', function (e) {
          e.preventDefault();
          const nid = getNid();
          const id = $(this).data('id');
          if (confirm('Remove this item?')) {
            postItem(`/dashboard/purchase-order/${nid}/item/${id}/delete`, {}, null);
            showMessage('Item deleted successfully!', 'success');
          }
        });
    },
  };
})(jQuery, Drupal, once);