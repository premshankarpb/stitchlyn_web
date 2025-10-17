(function ($, Drupal, drupalSettings, once) {
  Drupal.behaviors.stitchlynVendorBehavior = {
    attach: function (context) {
      /** -------------------------------------------------------
       * Vendor snapshot under vendor autocomplete
       * ------------------------------------------------------- */
      $(once('vendor-info', '.vendor-autocomplete', context)).each(function () {
        const $vendorField = $(this);
        const $info = $('<div class="vendor-info mt-2 p-2 border rounded bg-light"></div>')
          .insertAfter($vendorField);

        function loadVendor(uid) {
          if (!uid) return;
          $.getJSON(drupalSettings.stitchlyn_vendor.vendorInfoUrl + '?uid=' + uid, function (data) {
            if (data && (data.vendor_name || data.name)) {
              $info.html(
                `<strong>${data.vendor_name || data.name}</strong><br>
                 Contact: ${data.contact_person || '-'}<br>
                 Phone: ${data.phone_number || data.phone || '-'}<br>
                 GST: ${data.gst_number || '-'}<br>
                 Billing: ${data.billing_address || '-'}`
              );
            } else {
              $info.html('<em>No vendor info found</em>');
            }
          });
        }

        // on select
        $vendorField.on('autocompleteclose', function () {
          const txt = $(this).val();
          const m = txt.match(/\((\d+)\)$/);
          if (m && m[1]) loadVendor(m[1]);
        });

        // preload on edit
        const initial = $vendorField.val();
        const mi = initial && initial.match(/\((\d+)\)$/);
        if (mi && mi[1]) loadVendor(mi[1]);
      });

      /** -------------------------------------------------------
       * Autofill rate when item is chosen (one row only)
       * ------------------------------------------------------- */
      $(once('item-autocomplete', '.item-autocomplete', context))
        .on('autocompleteclose', function () {
          const txt = $(this).val();
          const m = txt.match(/\((\d+)\)$/);
          if (!m) return;
          const nid = m[1];
          const $row = $(this).closest('tr');

          $.getJSON(drupalSettings.stitchlyn_vendor.itemInfoUrl + '?nid=' + nid, function (data) {
            if (data && typeof data.rate !== 'undefined') {
              const $rate = $row.find('input[name$="[rate]"]');
              // prevent infinite loop by tagging once
              if (!$rate.data('autofilling')) {
                $rate.data('autofilling', true);
                $rate.val(data.rate);
                // trigger a single recalculation from Drupal
                $rate.trigger('change');
                // allow future manual edits to trigger change as usual
                setTimeout(() => $rate.removeData('autofilling'), 0);
              }
            }
          });
        });

      /** -------------------------------------------------------
       * Client preview: row total while typing, then let Drupal refresh
       * ------------------------------------------------------- */
      $(once('row-preview',
        'table input[name$="[rate]"], table input[name$="[quantity]"]', context
      )).each(function () {
        const $input = $(this);
        // only trigger when leaving the field (blur) or pressing Enter (change)
        $input.on('change blur', function () {
          const $row = $input.closest('tr');
          const rate = parseFloat($row.find('input[name$="[rate]"]').val()) || 0;
          const qty  = parseFloat($row.find('input[name$="[quantity]"]').val()) || 0;
          $row.find('input[name$="[total]"]').val((rate * qty).toFixed(2));
          // trigger Drupal AJAX (same as before)
          $input.trigger('change');
        });
      });
    }
  };
})(jQuery, Drupal, drupalSettings, once);