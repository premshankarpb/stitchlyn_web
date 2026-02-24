(function ($, Drupal, once) {

  /*************************************************
   * 1️⃣  CONTENT POSITION ADJUST BEHAVIOR
   *    Ensures main content sits below the fixed navbar
   *    (and admin toolbar, if present).
   *************************************************/
  Drupal.behaviors.stitchlynContentAdjust = {
    attach: function (context, settings) {

      function adjustContentPosition() {
        var totalHeight = 0;
        var SPACING = 10; // small gap below navbar

        // 1. Admin toolbar (if present)
        var $toolbar = $('#toolbar-bar');
        if ($toolbar.length && $toolbar.is(':visible')) {
          totalHeight += $toolbar.outerHeight() || 0;
        }

        // 2. Active toolbar tray (horizontal, can be multi-level)
        var $activeTray = $('.toolbar-tray.toolbar-tray-horizontal.is-active');
        if ($activeTray.length && $activeTray.is(':visible')) {
          totalHeight += $activeTray.outerHeight() || 0;
        }

        // 3. Push the fixed navbar down by the toolbar offset
        var $header = $('.site-header, .navbar.fixed-top, .admin-layout .admin-header');
        if (totalHeight > 0) {
          $header.css({ 'top': totalHeight + 'px', 'transition': 'top 0.2s ease' });
        } else {
          $header.css('top', '0');
        }

        // 4. Navbar height
        var $navbar = $('.navbar.fixed-top');
        if ($navbar.length && $navbar.is(':visible')) {
          totalHeight += $navbar.outerHeight() || 0;
        }

        // 5. Apply padding-top to main content
        var $mainContent = $('#main-content');
        if ($mainContent.length) {
          $mainContent.css('padding-top', (totalHeight + SPACING) + 'px');
        }

        // 6. For admin/dashboard pages, also adjust .admin-main and .admin-sidebar
        var $adminMain = $('.admin-layout .admin-main');
        var $adminSidebar = $('.admin-layout .admin-sidebar');
        if ($adminMain.length) {
          $adminMain.css('margin-top', totalHeight + 'px');
        }
        if ($adminSidebar.length) {
          $adminSidebar.css('top', totalHeight + 'px');
        }
      }

      // Run on ready, resize, and toolbar changes
      var observer = new MutationObserver(adjustContentPosition);
      if (document.body) {
        observer.observe(document.body, { attributes: true, childList: true, subtree: false });
      }

      once('contentAdjustReady', 'html', context).forEach(function () {
        $(document).ready(adjustContentPosition);
        $(window).on('resize', adjustContentPosition);
        $(document).on('drupalToolbarOrientationChange toolbar-drawer-change', adjustContentPosition);
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