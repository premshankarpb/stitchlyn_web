(function ($, Drupal, once) {

  /*************************************************
   * 1️⃣  FIXED TOOLBAR + NAVBAR CONTENT OFFSET
   *************************************************/
  Drupal.behaviors.stitchlynToolbarAdjust = {
    attach: function (context, settings) {

      function adjustContentPosition() {
        var $navbar = $('.navbar.fixed-top');
        var $toolbar = $('#toolbar-bar');
        var $activeTray = $('.toolbar-tray.toolbar-tray-horizontal.is-active');

        var $sidebar = $('.admin-layout .admin-sidebar');
        var $adminLayout = $('.admin-layout');
        var $mainContent = $('#main-content');

        // 1. Calculate admin toolbar total height (bar + any active trays)
        var toolbarOffset = 0;
        if ($toolbar.length && $toolbar.is(':visible')) {
          toolbarOffset += $toolbar.outerHeight() || 0;
        }
        if ($activeTray.length && $activeTray.is(':visible')) {
          toolbarOffset += $activeTray.outerHeight() || 0;
        }

        // 2. Push the fixed navbar below the admin toolbar
        if (toolbarOffset > 0) {
          $navbar.css({ 'top': toolbarOffset + 'px', 'transition': 'top 0.2s ease' });
        } else {
          $navbar.css('top', '0');
        }

        // 3. Get navbar height (after it's been positioned)
        var navbarHeight = $navbar.outerHeight() || 0;

        // 4. Total offset = toolbar + navbar + small gap
        var totalOffset = toolbarOffset + navbarHeight + 10;

        // 5. Apply offset to the correct content container
        if ($adminLayout.length) {
          // Admin pages: adjust the fixed sidebar and scrollable main area
          var $adminMain = $('.admin-layout .admin-main');
          if ($sidebar.length) {
            $sidebar.css('top', totalOffset + 'px');
            $sidebar.css('bottom', '0');
          }
          if ($adminMain.length) {
            $adminMain.css('margin-top', totalOffset + 'px');
            $adminMain.css('height', 'calc(100vh - ' + totalOffset + 'px)');
          }
        } else if ($mainContent.length) {
          $mainContent.css('padding-top', totalOffset + 'px');
        }
      }

      // Watch for toolbar DOM/class changes (tray open/close, orientation)
      var observer = new MutationObserver(adjustContentPosition);
      if (document.body) {
        observer.observe(document.body, { attributes: true, childList: true, subtree: false });
      }

      once('toolbarAdjustReady', 'html', context).forEach(function () {
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