// Custom JS for electrohkt carousel
document.addEventListener('DOMContentLoaded', function() {
  var swiper = document.querySelector('.swiper');
  if (swiper) {
    new Swiper(swiper, {
      loop: false,
      autoplay: {
        delay: 4000,
        disableOnInteraction: false,
      },
      slidesPerView: 1,
      //centeredSlides: false,
      spaceBetween: 0,
      pagination: {
        el: '.swiper-pagination',
        clickable: true,
      },
      navigation: {
        nextEl: '.swiper-button-next',
        prevEl: '.swiper-button-prev',
      },
    });
  }
});
