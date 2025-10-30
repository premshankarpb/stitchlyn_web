(function ($, Drupal, once) {
  'use strict';

  function getNid() {
    return $('#stitchlyn-po-edit-form').data('po-nid');
  }

  function loadItems() {
    const nid = getNid();
    if (!nid) return;
    $.get('/dashboard/purchase-order/' + nid + '/items', function (res) {
      if (res?.status === 'success') {
        $('#po-items-wrapper').html(res.html);
        updateSummaryFields(res.summary);
      }
    });
  }

  function updateSummaryFields(summary) {
    if (!summary) return;
    $('[name="field_subtotal_amount"]').val(parseFloat(summary.subtotal).toFixed(2));
    $('[name="field_tax_amount"]').val(parseFloat(summary.tax).toFixed(2));
    $('[name="field_total_amount"]').val(parseFloat(summary.total).toFixed(2));
  }

  function recalcTotal() {
    const rate = parseFloat($('[name="rate"]').val() || 0);
    const qty = parseFloat($('[name="quantity"]').val() || 0);
    $('[name="total"]').val((rate * qty).toFixed(2));
  }

  function attachRecalcHandlers() {
    $('[name="rate"], [name="quantity"]').off('change.recalc input.recalc keyup.recalc')
      .on('change.recalc input.recalc keyup.recalc', recalcTotal);
  }

  function toNumber(value) {
    const num = parseFloat(value);
    return isNaN(num) ? 0 : num;
  }

  window.recalcTotal = recalcTotal;
  window.closeModal = closeModal;
  window.openModal = openModal;

  function openModal(mode, data) {
    const $modal = $('#po-item-modal');
    const $form = $('#po-item-form');
    if (!$modal.length) return;

    closeModal();
    $('#po-item-modal-title').text(
      mode === 'edit' ? 'Edit Item' : mode === 'view' ? 'View Item' : 'Add Item'
    );

    // Reset form first
    // $form[0]?.reset();
    $form.find('input, textarea').val('');
    $('#po-inventory-nid').val('');

    // Populate if edit/view
    if (data) {
      $form.find('[name="item_id"]').val(data.item_id || '');
      $form.find('[name="item_reference"]').val(data.item_reference || '');
      $form.find('[name="rate"]').val(data.rate || '');
      $form.find('[name="quantity"]').val(data.quantity || '');
      recalcTotal();
      $form.find('[name="remarks"]').val(data.remarks || '');
    }

    // Disable inputs if view mode
    if (mode === 'view') {
      // make read-only & hide save
      $form.find('input, textarea').prop('disabled', true);
      $('#po-item-cancel').prop('disabled', false);
      $('#po-item-save').hide();
    } else {
      attachRecalcHandlers();
    }

    $modal.fadeIn(200);
  }

  function closeModal() {
    const $modal = $('#po-item-modal');
    const $form = $('#po-item-modal');

    // $form[0]?.reset();
    $form.find('input, textarea').val('');
    $('#po-inventory-nid').val('');
    $form.find('input, textarea, button').attr('disabled', false);
    $modal.fadeOut(150);
  }

  function showMessage(message, type = 'success') {
    const $msg = $(`<div class="po-toast po-toast-${type}">${message}</div>`)
      .appendTo('body')
      .hide()
      .fadeIn(200);
    setTimeout(() => $msg.fadeOut(300, () => $msg.remove()), 2000);
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
      }
    });
  }

  function fetchItem(id, onOk) {
    const nid = getNid();
    $.getJSON(`/dashboard/purchase-order/${nid}/item/${id}/json`, function (res) {
      if (res && res.status === 'success' && res.item) return onOk(res.item);
      // Fallback: read row data if JSON not implemented
      const $row = $(`#po-items-wrapper button[data-id="${id}"]`).closest('tr');
      if ($row.length) {
        return onOk({
          id,
          label: $row.find('td').eq(0).text().trim(),
          rate:  toNumber($row.find('td').eq(1).text()),
          quantity: toNumber($row.find('td').eq(2).text()),
          remarks: ''
        });
      }
      alert('Unable to load item details.');
    });
  }


  Drupal.behaviors.stitchlynPoEdit = {
    attach: function (context) {
      once('po-hide-modal', context).forEach(() => $('#po-item-modal').hide());
      once('po-init', context).forEach(() => loadItems());

      // Add Item
      once('po-add', '.po-add-item, .po-add-item-float', context).forEach(el => {
        $(el).on('click', e => {
          e.preventDefault();
          openModal('add');
        });
      });

      // Cancel modal
      once('po-cancel', '#po-item-cancel', context).forEach(el => {
        $(el).on('click', e => {
          e.preventDefault();
          closeModal();
        });
      });

      // Overlay close
      once('po-overlay', '#po-item-modal', context).forEach(el => {
        $(el).on('click', e => {
          if ($(e.target).is('#po-item-modal')) closeModal();
        });
      });

      // Inventory autocomplete
      once('inventory-autocomplete', '.inventory-autocomplete', context).forEach(el => {
        const $el = $(el);
        $el.autocomplete({
          minLength: 2,
          appendTo: "#po-item-modal",
          source: function (request, response) {
            $.getJSON('/inventory-item/autocomplete', { q: request.term }, function (data) {
              response($.map(data, function (item) {
                return { label: item.label, value: item.label, nid: item.nid, rate: item.rate };
              }));
            });
          },
          select: function (event, ui) {
            $('#po-inventory-nid').val(ui.item.nid);
            $('[name="rate"]').val(ui.item.rate);
            recalcTotal();
          }
        }).autocomplete("instance")._renderItem = function (ul, item) {
          return $("<li>")
            .append(`<div><strong>${item.label}</strong><br><small>Rate: ₹${item.rate}</small></div>`)
            .appendTo(ul);
        };
      });

      // Save or Update item
      once('po-save', '#po-item-save', context).forEach(el => {
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

      // --- View Item ---
      $(document).on('click', '.po-item-view', function () {
        const itemId = $(this).data('id');
        const nid = $('[data-po-nid]').attr('data-po-nid');

        $.ajax({
          url: `/dashboard/purchase-order/${nid}/item/${itemId}/json?_format=json`,
          type: 'GET',
          dataType: 'json',
          success: function (data) {
            // Open modal and fill data (read-only)
            $('#po-item-modal .modal-title').text('View Item');
            $('#po-item-form [name="inventory_item"]').val(data.title).prop('disabled', true);
            $('#po-item-form [name="rate"]').val(toNumber(data.rate)).prop('disabled', true);
            $('#po-item-form [name="quantity"]').val(toNumber(data.quantity)).prop('disabled', true);
            $('#po-item-form [name="total"]').val(toNumber(data.total)).prop('disabled', true);
            $('#po-item-form [name="remarks"]').val(data.remarks).prop('disabled', true);
            $('#po-item-save').hide();
            $('#po-item-modal').modal('show');
          },
          error: function (xhr) {
            alert('Unable to load item details.');
          }
        });
      });


      // --- Edit Item ---
      $(document).on('click', '.po-item-edit', function () {
        const itemId = $(this).data('id');
        const nid = $('[data-po-nid]').attr('data-po-nid');

        $.ajax({
          url: `/dashboard/purchase-order/${nid}/item/${itemId}/json?_format=json`,
          type: 'GET',
          dataType: 'json',
          success: function (data) {
            // Open modal and pre-fill form for editing
            $('#po-item-modal .modal-title').text('Edit Item');
            $('#po-item-form [name="item_id"]').val(data.id);
            $('#po-item-form [name="inventory_item"]').val(data.title).prop('disabled', false);
            $('#po-item-form [name="rate"]').val(toNumber(data.rate)).prop('disabled', false);
            $('#po-item-form [name="quantity"]').val(toNumber(data.quantity)).prop('disabled', false);
            $('#po-item-form [name="total"]').val(toNumber(data.total)).prop('disabled', false);
            $('#po-item-form [name="remarks"]').val(data.remarks).prop('disabled', false);
            $('#po-item-save').show();
            $('#po-item-modal').modal('show');
          },
          error: function (xhr) {
            alert('Unable to load item details.');
          }
        });
      });

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
    }
  };
})(jQuery, Drupal, once);