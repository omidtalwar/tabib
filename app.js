(function ($) {
  $(document).ready(function () {
    AOS.init({ duration: 700, once: true, easing: 'ease-out-cubic' });

    $('#menuBtn').on('click', function () {
      $('#mobileNav').stop(true, true).slideToggle(170);
    });

    $('#mobileNav a').on('click', function () {
      $('#mobileNav').slideUp(130);
    });

    $('a[href^="#"]').on('click', function (e) {
      const target = $(this.getAttribute('href'));
      if (!target.length) return;
      e.preventDefault();
      $('html, body').animate({ scrollTop: target.offset().top - 16 }, 450);
    });
  });
})(jQuery);
