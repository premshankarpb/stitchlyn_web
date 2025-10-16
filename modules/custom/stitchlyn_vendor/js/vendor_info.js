(function ($, Drupal, drupalSettings, once) {

  Drupal.behaviors.stitchlynVendorBehavior = {
    attach: function (context) {

      // ============== 1) Vendor info card below autocomplete ==============
      $(once('vendor-info', '.vendor-autocomplete', context)).each(function () {
        const $vendorField = $(this);
        const $card = $('<div class="vendor-info mt-2 p-2 border rounded bg-light"></div>').insertAfter($vendorField);

        function loadVendor(uid) {
          if (!uid) return;
          const url = drupalSettings.stitchlyn_vendor.vendorInfoUrl + '?uid=' + uid;
          $.getJSON(url, function (data) {
            if (data && (data.vendor_name || data.name)) {
              const html = `
                <strong>${data.vendor_name || data.name}</strong><br>
                Contact: ${data.contact_person || '-'}<br>
                Phone: ${data.phone_number || data.phone || '-'}<br>
                GST: ${data.gst_number || '-'}<br>
                Billing: ${data.billing_address || '-'}
              `;
              $card.html(html);
            } else {
              $card.html('<em>No vendor info found</em>');
            }
          });
        }

        // When a user is picked from autocomplete, the field value ends with " (UID)".
        $vendorField.on('autocompleteclose', function () {
          const txt = $(this).val();
          const m = txt.match(/\((\d+)\)$/);
          if (m && m[1]) loadVendor(m[1]);
        });

        // Initial render on edit if already selected.
        const initial = $vendorField.val();
        const mi = initial.match(/\((\d+)\)$/);
        if (mi && mi[1]) loadVendor(mi[1]);
      });

      // ============== 2) Autofill rate when item picked ==============
      $(once('item-rate', '.item-autocomplete', context)).on('autocompleteclose', function () {
        const txt = $(this).val();
        const m = txt.match(/\((\d+)\)$/);
        if (!m || !m[1]) return;

        const nid = m[1];
        const $row = $(this).closest('tr');
        const url = drupalSettings.stitchlyn_vendor.itemInfoUrl + '?nid=' + nid;

        $.getJSON(url, function (data) {
          if (data && typeof data.rate !== 'undefined') {
            const $rate = $row.find('input[name$="[rate]"]');
            const $qty  = $row.find('input[name$="[quantity]"]');

            $rate.val(data.rate);

            // Trigger change on both so the server AJAX recalculates totals & summary.
            $rate.trigger('change');
            $qty.trigger('change');
          }
        });
      });

    }
  };

})(jQuery, Drupal, drupalSettings, once);