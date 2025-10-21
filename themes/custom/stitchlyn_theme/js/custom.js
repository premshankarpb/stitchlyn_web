(function ($, Drupal) {
  Drupal.behaviors.stitchlynToolbarAdjust = {
    attach: function (context, settings) {
      // Function to adjust header padding when toolbar is visible
      function adjustHeaderForToolbar() {
        const $toolbar = $('#toolbar-administration');
        const $header = $('.site-header');
        const $navbar = $('.navbar.fixed-top');
        const $body = $('body');

        if ($toolbar.length && $toolbar.is(':visible')) {
          const toolbarHeight = $toolbar.outerHeight();
        //   $header.css('padding-top', '3%'); // add a bit extra
          $navbar.css('top', '3%'); // push nav below toolbar
          $body.css('padding-top', '5%'); // add a bit extra
        } else {
          $header.css('padding-top', '');
          $navbar.css('top', '0'); // reset when toolbar hidden
          $body.css('padding-top', '5%'); // add a bit extra
        }
      }

      // Run initially and also on window resize
      $(document).ready(adjustHeaderForToolbar);
      $(window).on('resize', adjustHeaderForToolbar);
    },
  };
})(jQuery, Drupal);
