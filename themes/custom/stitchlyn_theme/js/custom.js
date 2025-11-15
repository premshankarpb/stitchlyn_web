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

  /*************************************************
   * 2️⃣  FIXED SIDEBAR TOGGLE BEHAVIOR
   *************************************************/
  Drupal.behaviors.stitchlynSidebar = {
    attach: function (context, settings) {

      once('sidebarToggle', '.sidebar-parent', context).forEach((el) => {
        $(el).on('click', function (e) {
          e.preventDefault();
          const $this = $(this);
          const $submenu = $this.next('.sidebar-sublist');

          $submenu.slideToggle(200);
          $this.toggleClass('open');
          $this.find('.sidebar-arrow').toggleClass('rotate');
        });
      });

    }
  };

})(jQuery, Drupal, once);