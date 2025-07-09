$(document).ready(function () {
  $(".slider").slick({
    dots: false,
    infinite: true,
    speed: 300,
    fade: true,
    cssEase: "linear",
    autoplay: true,
    autoplaySpeed: 3500,
    arrows: false,
  });

  $(".hero-slider").slick({
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


  // Product Detail Main & Thumbnail Slick Sliders with Magnific Popup
  var $mainSlider = $(".product-slider");
  var $thumbSlider = $(".product-thumbs-slider");

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
    // Magnific Popup for slider images
    $mainSlider.magnificPopup({
      delegate: "a.gallery-popup",
      type: "image",
      gallery: { enabled: true },
      image: { titleSrc: "title" },
    });
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

// Smooth scrolling for navigation links
$('a[href^="#"]').on("click", function (e) {
  e.preventDefault();
  const target = $($(this).attr("href"));
  if (target.length) {
    $("html, body").animate(
      {
        scrollTop: target.offset().top,
      },
      600
    );
  }
});

// Animation on scroll
const $animateElements = $(".animate-fade-up");
$animateElements.css({
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
$(document).ready(animateOnScroll);

  // AOS.init({
  //   duration: 800,
  //   once: true,
  // });

  setupTShirtQuoteFunctionality();
// ----------------------------------------------------------------------------------------------

function setupTShirtQuoteFunctionality() {
  // --- JavaScript for T-Shirt Quote Functionality ---

  // DOM elements
  const sizeSelect = document.getElementById("tshirtSize");
  const countSelect = document.getElementById("tshirtCount");
  const colorSelect = document.getElementById("tshirtColor");
  const quoteModal = document.getElementById("quoteModal");
  const addItemBtn = document.getElementById("addItemBtn");

  // Modal specific DOM elements
  const quoteAccordion = document.getElementById("quoteAccordion");
  const quoteTotalEl = document.getElementById("quoteTotal");
  const emptyQuoteMsg = document.getElementById("emptyQuoteMsg");

  // Main page summary DOM elements
  const finalQuoteSummaryDiv = document.getElementById("finalQuoteSummary");
  const finalAccordion = document.getElementById("finalQuoteAccordion");
  const finalTotalEl = document.getElementById("finalQuoteTotal");

  // Base price per shirt
  const BASE_PRICE_PER_SHIRT = 15.0;

  // Array to store quote items while modal is open
  let quoteItems = [];

  /**
   * Renders the quote accordion inside the modal.
   */
  function renderQuoteAccordion() {
    quoteAccordion.innerHTML = "";
    quoteTotalEl.innerHTML = "";

    let totalQuotePrice = 0;

    if (quoteItems.length === 0) {
      emptyQuoteMsg.style.display = "block";
      quoteTotalEl.style.display = "none";
      return;
    }

    emptyQuoteMsg.style.display = "none";
    quoteTotalEl.style.display = "block";

    quoteItems.forEach((item, index) => {
      const itemPrice = calculatePrice(item.quantity);
      totalQuotePrice += itemPrice;

      const accordionItemId = `item-${index}`;
      const collapseId = `collapse-${index}`;

      const accordionItem = `
                      <div class="accordion-item">
                          <h2 class="accordion-header" id="${accordionItemId}-header">
                              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}" aria-expanded="false" aria-controls="${collapseId}">
                                  <div class="d-flex w-100 justify-content-between align-items-center pe-3">
                                      <span>Item #${index + 1}: ${
        item.quantity
      } x ${item.size} ${item.color}</span>
                                      <strong class="text-success">$${itemPrice.toFixed(
                                        2
                                      )}</strong>
                                  </div>
                              </button>
                          </h2>
                          <div id="${collapseId}" class="accordion-collapse collapse" aria-labelledby="${accordionItemId}-header" data-bs-parent="#quoteAccordion">
                              <div class="accordion-body">
                                  <ul class="list-group list-group-flush">
                                      <li class="list-group-item d-flex justify-content-between align-items-center">Size: <strong>${
                                        item.size
                                      }</strong></li>
                                      <li class="list-group-item d-flex justify-content-between align-items-center">Color: <strong>${
                                        item.color
                                      }</strong></li>
                                      <li class="list-group-item d-flex justify-content-between align-items-center">Quantity: <strong>${
                                        item.quantity
                                      }</strong></li>
                                      <li class="list-group-item d-flex justify-content-between align-items-center">Estimated Price: <strong class="text-success">$${itemPrice.toFixed(
                                        2
                                      )}</strong></li>
                                  </ul>
                                  <div class="d-flex justify-content-end mt-3">
                                     <button class="btn btn-danger btn-sm" onclick="removeItem(${index})">Remove Item</button>
                                  </div>
                              </div>
                          </div>
                      </div>
                  `;
      quoteAccordion.innerHTML += accordionItem;
    });

    quoteTotalEl.innerHTML = `Total Estimated Price: <span class="text-primary fs-4">$${totalQuotePrice.toFixed(
      2
    )}</span>`;
  }

  /**
   * Renders the final quote summary on the main page form.
   */
  function renderFinalQuoteSummary() {
    finalAccordion.innerHTML = "";
    finalTotalEl.innerHTML = "";
    let totalQuotePrice = 0;

    if (quoteItems.length === 0) {
      finalQuoteSummaryDiv.style.display = "none";
      return;
    }

    finalQuoteSummaryDiv.style.display = "block";

    quoteItems.forEach((item, index) => {
      const itemPrice = calculatePrice(item.quantity);
      totalQuotePrice += itemPrice;

      const accordionItemId = `final-item-${index}`;
      const collapseId = `final-collapse-${index}`;

      // Note: The "Remove" button is omitted here as this is the final summary.
      const accordionItem = `
                      <div class="accordion-item">
                          <h2 class="accordion-header" id="${accordionItemId}-header">
                              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}" aria-expanded="false" aria-controls="${collapseId}">
                                  <div class="d-flex w-100 justify-content-between align-items-center pe-3">
                                      <span>Item #${index + 1}: ${
        item.quantity
      } x ${item.size} ${item.color}</span>
                                      <strong class="text-success">$${itemPrice.toFixed(
                                        2
                                      )}</strong>
                                  </div>
                              </button>
                          </h2>
                          <div id="${collapseId}" class="accordion-collapse collapse" aria-labelledby="${accordionItemId}-header" data-bs-parent="#finalQuoteAccordion">
                              <div class="accordion-body">
                                 <ul class="list-group list-group-flush">
                                      <li class="list-group-item">Size: <strong>${
                                        item.size
                                      }</strong></li>
                                      <li class="list-group-item">Color: <strong>${
                                        item.color
                                      }</strong></li>
                                      <li class="list-group-item">Quantity: <strong>${
                                        item.quantity
                                      }</strong></li>
                                  </ul>
                              </div>
                          </div>
                      </div>
                  `;
      finalAccordion.innerHTML += accordionItem;
    });

    finalTotalEl.innerHTML = `Total Estimated Price: <span class="text-primary fs-4">$${totalQuotePrice.toFixed(
      2
    )}</span>`;
  }

  /**
   * Calculates price for a single item based on quantity with discounts.
   */
  function calculatePrice(quantity) {
    let price = BASE_PRICE_PER_SHIRT * quantity;
    if (quantity >= 50) {
      price *= 0.9;
    } // 10% discount
    else if (quantity >= 25) {
      price *= 0.95;
    } // 5% discount
    return price;
  }

  /**
   * Adds the currently selected item to the quote array and re-renders the modal accordion.
   */
  function addItemToQuote() {
    const newItem = {
      size: sizeSelect.value,
      quantity: parseInt(countSelect.value, 10),
      color: colorSelect.value,
    };
    quoteItems.push(newItem);
    renderQuoteAccordion();
    resetDropdowns();
  }

  /**
   * Removes an item from the quote array (in modal) and re-renders the accordion.
   */
  window.removeItem = function(index) {
    quoteItems.splice(index, 1);
    renderQuoteAccordion();
  };

  /**
   * Resets the dropdowns in the modal to their default selections.
   */
  function resetDropdowns() {
    sizeSelect.value = "Medium";
    countSelect.value = "10";
    colorSelect.value = "White";
  }

  // --- Event Listeners ---

  if (addItemBtn) {
    addItemBtn.addEventListener("click", addItemToQuote);
  }

  // Render the modal accordion when it's first opened.
  quoteModal.addEventListener("shown.bs.modal", renderQuoteAccordion);

  // When the modal is closed, transfer the data to the main page.
  quoteModal.addEventListener("hidden.bs.modal", function () {
    renderFinalQuoteSummary(); // Render the summary on the main page.
    quoteItems = []; // Clear the array for the next time the modal is opened.
    resetDropdowns(); // Reset dropdowns for next use.
    renderQuoteAccordion(); // Clears the modal's view and shows the "empty" message.
  });
}

// Call the function to initialize the T-Shirt Quote functionality

