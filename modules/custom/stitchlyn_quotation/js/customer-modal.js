(function ($, Drupal) {

  Drupal.behaviors.customerMessageFade = {
    attach: function (context, settings) {

      const msg = $('#customer-create-message .customer-created-success');

      if (msg.length) {

        setTimeout(function () {
          msg.fadeOut();
        }, 3000);

      }

    }
  };

})(jQuery, Drupal);