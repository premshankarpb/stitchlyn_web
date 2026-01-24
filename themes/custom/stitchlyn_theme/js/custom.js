(function ($, Drupal, once) {

  /*************************************************
   * 1️⃣  FIXED TOOLBAR ADJUST BEHAVIOR
   *************************************************/
  Drupal.behaviors.stitchlynToolbarAdjust = {
    attach: function (context, settings) {

      function adjustHeaderForToolbar() {
        const $toolbar = $('#toolbar-bar'); // The main administration toolbar
        const $tray = $('#toolbar-item-administration-tray.toolbar-tray-horizontal'); // Horizontal secondary toolbar (if any)
        const $header = $('.site-header, .navbar.fixed-top, .admin-layout .admin-header'); // Target all potential headers
        const $body = $('body');

        let totalOffset = 0;

        // Check if main toolbar is present and visible
        if ($toolbar.length && $toolbar.is(':visible')) {
          totalOffset += $toolbar.outerHeight() || 0;
        }

        // Check if secondary tray is visible and horizontal
        if ($tray.length && $tray.is(':visible')) {
          totalOffset += $tray.outerHeight() || 0;
        }

        // Apply Offset
        if (totalOffset > 0) {
          $header.css({
            'top': totalOffset + 'px',
            'transition': 'top 0.2s ease' // Smooth adjustment
          });
          
          // Optionally push body down if header is fixed
          // $body.css('padding-top', (totalOffset + 80) + 'px'); 
        } else {
          // Reset if no toolbar
          $header.css('top', '0');
        }
      }

      // Run on ready, resize, and scroll (in case of dynamic toolbar changes)
      // Also observe mutation to catch Drupal toolbar loading late
      const observer = new MutationObserver(adjustHeaderForToolbar);
      if (document.body) {
        observer.observe(document.body, { childList: true, subtree: false });
      }

      once('toolbarAdjustReady', 'html', context).forEach(() => {
        $(document).ready(adjustHeaderForToolbar);
        $(window).on('resize', adjustHeaderForToolbar);
        // Drupal's own toolbar event
        $(document).on('drupalToolbarOrientationChange toolbar-drawer-change', adjustHeaderForToolbar);
      });

    },
  };

})(jQuery, Drupal, once);
  
  // Manager Dashboard Logic
  (function ($, Drupal) {
    Drupal.behaviors.managerDashboard = {
      attach: function (context) {
        
        // --- 1. Live Clock ---
        function updateTime() {
          const now = new Date();
          const timeString = now.toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit' });
          const dateString = now.toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' });
  
          $('#clock-time').text(timeString);
          $('#clock-date').text(dateString);
        }
  
        // Initialize clock if element exists
        if ($('#clock-time').length) {
          updateTime();
          setInterval(updateTime, 1000); // Update every second
        }
  
        // --- 2. (Optional) Simulate Live Data Updates ---
        // This is just visual flair for the "Senior Dev" touch to show the UI is "alive"
        // In a real app, this would be a WebSocket or polling AJAX call.
        if ($('.dash-card').length) {
          console.log("Dashboard Loaded: Ready for realtime updates.");
        }
      }
    };
  })(jQuery, Drupal);

/**
 * GLOBAL FIX → Allow jQuery UI Autocomplete to work inside Bootstrap Modals.
 */
jQuery(document).on('focusin', function (e) {
  if (jQuery(e.target).closest(".ui-autocomplete").length) {
    e.stopImmediatePropagation();
  }
});