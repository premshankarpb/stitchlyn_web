(function ($, Drupal, drupalSettings, once) {
  'use strict';

  Drupal.behaviors.stitchlynVendorBehavior = {
    attach: function (context) {

      // ---------------------------------------------
      // Vendor snapshot below vendor autocomplete
      // ---------------------------------------------
      once('vendor-info', '.vendor-autocomplete', context).forEach((el) => {
        const $vendorField = $(el);
        const $info = $('<div class="vendor-info mt-2 p-2 border rounded bg-light"></div>')
          .insertAfter($vendorField);

        function renderVendor(data) {
          const name = data.vendor_name || '—';
          const contact = data.contact_person || '—';
          const phone = data.phone_number || '—';
          const gst = data.gst_number || '—';
          const billing = data.billing_address || '—';

          // Display phone with India flag format
          let phoneHtml = '';
          if (phone && phone.trim() !== '' && phone.trim() !== '-') {
            // Extract only digits
            const cleaned = phone.replace(/[^\d]/g, '');
            // Trim country code if present (assume Indian numbers)
            const displayNum = cleaned.startsWith('91') && cleaned.length > 10 ? cleaned.slice(-12) : cleaned;

            phoneHtml = `<div class="vendor-line">📞 🇮🇳 (${displayNum})</div>`;
          }

          $info.html(`
            <div class="vendor-card">
              <div class="vendor-line vendor-name"><strong>${name}</strong></div>
              <div class="vendor-line">Contact: ${contact}</div>
              <div class="vendor-line">${phone}</div>
              <div class="vendor-line">GST: ${gst}</div>
              <div class="vendor-line">Billing: ${billing}</div>
            </div>
          `);
        }

        function loadVendor(uid) {
          if (!uid) return;
          $.getJSON(drupalSettings.stitchlyn_vendor.vendorInfoUrl + '?uid=' + uid)
            .done(function (data) {
              if (data && !data.error) {
                renderVendor(data);
              } else {
                $info.html('<em>No vendor information available</em>');
              }
            })
            .fail(function () {
              $info.html('<em>Unable to load vendor details</em>');
            });
        }

        // When vendor selected.
        $vendorField.on('autocompleteclose', function () {
          const txt = $(this).val();
          const match = txt.match(/\((\d+)\)$/);
          if (match && match[1]) loadVendor(match[1]);
        });

        // Preload if already selected.
        const initial = $vendorField.val();
        const initMatch = initial && initial.match(/\((\d+)\)$/);
        if (initMatch && initMatch[1]) loadVendor(initMatch[1]);
      });
    }
  };

})(jQuery, Drupal, drupalSettings, once);