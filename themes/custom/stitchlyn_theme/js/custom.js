(function ($, Drupal, once) {

  /*************************************************
   * 1️⃣  FIXED TOOLBAR ADJUST BEHAVIOR
   *************************************************/
  Drupal.behaviors.stitchlynToolbarAdjust = {
    attach: function (context, settings) {

      function adjustHeaderForToolbar() {
        const $toolbar = $('#toolbar-administration');
        const $header = $('.site-header');
        const $navbar = $('.navbar.fixed-top');
        const $body = $('body');

        if ($toolbar.length && $toolbar.is(':visible')) {
          const toolbarHeight = $toolbar.outerHeight();

          // Adjust UI
          $navbar.css('top', '3%');
          $body.css('padding-top', '5%');

        } else {
          // Reset
          $header.css('padding-top', '');
          $navbar.css('top', '0');
          $body.css('padding-top', '5%');
        }
      }

      // Run only ONCE on ready
      once('toolbarAdjustReady', 'html', context).forEach(() => {
        $(document).ready(adjustHeaderForToolbar);
        $(window).on('resize', adjustHeaderForToolbar);
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