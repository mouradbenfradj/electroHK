// Custom JS for electrohkt template - Main carousel 1 slide + Categories swiper

document.addEventListener('DOMContentLoaded', function() {
  // Main carousel - strictly 1 slide even on mobile
  if (document.getElementById('main-carousel')) {
    const mainSwiper = new Swiper('#main-carousel', {
      slidesPerView: 1,
      spaceBetween: 0,
      loop: true,
      autoplay: {
        delay: 5000,
        disableOnInteraction: false,
      },
      effect: 'fade',
      fadeEffect: {
        crossFade: true
      },
      slidesPerView: 1,
      centeredSlides: true,
      autoHeight: false,
      watchSlidesProgress: true,
      breakpoints: {
        320: {
          slidesPerView: 1,
        },
        768: {
          slidesPerView: 1,
        }
      },
      pagination: {
        el: '.swiper-pagination',
        clickable: true,
      },
      navigation: {
        nextEl: '.swiper-button-next',
        prevEl: '.swiper-button-prev',
      }
    });
  }

  // Categories hidden swiper
  if (document.getElementById('categories-swiper')) {
    const categoriesSwiper = new Swiper('#categories-swiper', {
      direction: 'horizontal',
      loop: true,
      autoplay: {
        delay: 3000,
        disableOnInteraction: false,
      },
      slidesPerView: 'auto',
      spaceBetween: 8,
      centeredSlides: false,
      pagination: {
        el: '.swiper-pagination',
        clickable: true,
      },
      breakpoints: {
        768: {
          slidesPerView: 4,
          spaceBetween: 12,
        },
        1024: {
          slidesPerView: 6,
          spaceBetween: 16,
        },
        1280: {
          slidesPerView: 9,
        }
      }
    });
  }
});
