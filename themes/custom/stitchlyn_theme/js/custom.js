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

/**
 * GLOBAL FIX → Allow jQuery UI Autocomplete to work inside Bootstrap Modals.
 */
jQuery(document).on('focusin', function (e) {
  if (jQuery(e.target).closest(".ui-autocomplete").length) {
    e.stopImmediatePropagation();
  }
});