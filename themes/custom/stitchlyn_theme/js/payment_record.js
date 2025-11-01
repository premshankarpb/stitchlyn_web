(function ($, Drupal, drupalSettings, once) {

  Drupal.behaviors.paymentRecord = {
    attach: function (context) {

      // ---------------------------
      // ADD payment BUTTON → OPEN MODAL
      // ---------------------------
      once('payAdd', '#add-payment', context).forEach((el) => {
        $(el).on('click', function () {
          const modal = document.getElementById('paymentModal');
          if (modal) {
            const modalInstance = new bootstrap.Modal(modal);
            modalInstance.show();
          }
        });
      });
     } // end attach
  };

})(jQuery, Drupal, drupalSettings, once);

(function ($, Drupal) {
  $(document).on('shown.bs.tab', function (e) {
    console.log('Reattaching behaviors after tab change');
    Drupal.attachBehaviors(document, Drupal.settings);
  });
})(jQuery, Drupal);