(function ($, Drupal, once) {

  /*************************************************
   * 1️⃣  FIXED TOOLBAR ADJUST BEHAVIOR
   *************************************************/
  Drupal.behaviors.stitchlynToolbarAdjust = {
    attach: function (context, settings) {

      function adjustHeaderForToolbar() {
        const $toolbar = $('#toolbar-bar'); // The main administration toolbar
        // Check for ANY active horizontal tray (Admin or User)
        const $activeTray = $('.toolbar-tray.toolbar-tray-horizontal.is-active');
        
        const $header = $('.site-header, .navbar.fixed-top, .admin-layout .admin-header'); 
        const $sidebar = $('.admin-layout .admin-sidebar'); // Also push sidebar down if needed
        const $main = $('.admin-layout .admin-main');
        
        let totalOffset = 0;

        // 1. Base Toolbar Height
        if ($toolbar.length && $toolbar.is(':visible')) {
          totalOffset += $toolbar.outerHeight() || 0;
        }

        // 2. Active Tray Height (User tray or Admin tray)
        if ($activeTray.length && $activeTray.is(':visible')) {
          totalOffset += $activeTray.outerHeight() || 0;
        }

        // Apply Offset
        if (totalOffset > 0) {
          // Move Top Header
          $header.css({
            'top': totalOffset + 'px',
            'transition': 'top 0.2s ease'
          });
          
          // Move Sidebar (if it's fixed to top:0)
          if ($sidebar.length) {
             $sidebar.css('top', totalOffset + 'px');
          }
          
          // Adjust Main Content margin if needed (prevent cut-off)
           // if ($main.length) {
           //   $main.css('margin-top', (totalOffset + 70) + 'px'); // 70px is original header height
           // }
          
        } else {
          // Reset
          $header.css('top', '0');
          if ($sidebar.length) $sidebar.css('top', '70px'); // Default header height
// if ($main.length) $main.css('margin-top', '70px');
        }
      }

      // Run on ready, resize, scroll, and mutation
      const observer = new MutationObserver(adjustHeaderForToolbar);
      if (document.body) {
         // Watch for class changes on body (toolbar-horizontal etc make changes to body classes)
        observer.observe(document.body, { attributes: true, childList: true, subtree: false });
      }

      once('toolbarAdjustReady', 'html', context).forEach(() => {
        $(document).ready(adjustHeaderForToolbar);
        $(window).on('resize', adjustHeaderForToolbar);
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