(function ($, Drupal, once) {
  Drupal.behaviors.customSliderBehavior = {
    attach: function (context, settings) {

      // Slick sliders
      $(".slider", context).once('slick-init').slick({
        dots: false,
        infinite: true,
        speed: 300,
        fade: true,
        cssEase: "linear",
        autoplay: true,
        autoplaySpeed: 3500,
        arrows: false,
      });

      $(".hero-slider", context).once('hero-slick-init').slick({
        slidesToShow: 1,
        slidesToScroll: 1,
        arrows: false,
        dots: true,
        autoplay: true,
        autoplaySpeed: 4000,
        fade: true,
        speed: 900,
        pauseOnHover: false,
        pauseOnFocus: false,
        cssEase: "linear",
        adaptiveHeight: false,
      });

      // AOS init
      if (typeof AOS !== 'undefined') {
        AOS.init({
          duration: 800,
          once: true,
        });
      }

      // Product slider & thumbnails
      var $mainSlider = $(".product-slider", context).once('main-slick-init');
      var $thumbSlider = $(".product-thumbs-slider", context).once('thumb-slick-init');

      if ($mainSlider.length && $thumbSlider.length) {
        $mainSlider.slick({
          slidesToShow: 1,
          slidesToScroll: 1,
          arrows: true,
          dots: false,
          fade: false,
          asNavFor: ".product-thumbs-slider",
          adaptiveHeight: true,
          prevArrow:
            '<button type="button" class="slick-prev"><i class="fas fa-chevron-left"></i></button>',
          nextArrow:
            '<button type="button" class="slick-next"><i class="fas fa-chevron-right"></i></button>',
        });

        $thumbSlider.slick({
          slidesToShow: 5,
          slidesToScroll: 1,
          asNavFor: ".product-slider",
          focusOnSelect: true,
          arrows: false,
          dots: false,
          centerMode: true,
          variableWidth: false,
          infinite: true,
        });

        // Magnific Popup for product images
        $mainSlider.magnificPopup({
          delegate: "a.gallery-popup",
          type: "image",
          gallery: { enabled: true },
          image: { titleSrc: "title" },
        });
      }

      // Animate on scroll
      const $animateElements = $(".animate-fade-up", context).once('fade-up-init').css({
        opacity: 0,
        transform: "translateY(30px)",
        transition: "all 0.8s ease",
      });

      function animateOnScroll() {
        $animateElements.each(function () {
          const $el = $(this);
          const windowBottom = $(window).scrollTop() + $(window).height();
          const elTop = $el.offset().top;
          if (windowBottom > elTop) {
            $el.css({
              opacity: 1,
              transform: "translateY(0)",
            });
          }
        });
      }

      $(window).on("scroll resize", animateOnScroll);
      animateOnScroll();

      // Smooth scrolling
      $('a[href^="#"]', context).once('smooth-scroll').on("click", function (e) {
        const target = $($(this).attr("href"));
        if (target.length) {
          e.preventDefault();
          $("html, body").animate(
            { scrollTop: target.offset().top },
            600
          );
        }
      });

      // Navbar scroll effect
      $(window).on("scroll", function () {
        const $navbar = $(".navbar");
        if ($(window).scrollTop() > 50) {
          $navbar.addClass("scrolled");
        } else {
          $navbar.removeClass("scrolled");
        }
      });

    }
  };
})(jQuery, Drupal);
