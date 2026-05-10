(function ($, Drupal) {

  Drupal.behaviors.intlPhone = {
    attach: function (context, settings) {

      $('.js-intl-phone', context).once('intl-phone').each(function () {

        intlTelInput(this, {
          initialCountry: "in",
          separateDialCode: true,
          preferredCountries: ["in", "us", "gb"],
        });

      });

    }
  };

})(jQuery, Drupal);