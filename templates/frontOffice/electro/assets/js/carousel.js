/**
 * Electro Carousel Module - Swiper Initialization
 */
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Swiper for carousel with proper data attributes
    var carouselSwiper = document.querySelector('#electro-swiper-carousel');
    
    if (carouselSwiper && typeof Swiper !== 'undefined') {
        new Swiper('#electro-swiper-carousel', {
            slidesPerView: 1,
            spaceBetween: 0,
            effect: 'fade',
            fadeEffect: {
                crossFade: true
            },
            speed: 400,
            autoplay: {
                delay: 5000,
                disableOnInteraction: false
            },
            loop: true,
            pagination: {
                el: '#electro-swiper-carousel .swiper-pagination',
                clickable: true
            },
            navigation: {
                nextEl: '#electro-swiper-carousel .swiper-button-next',
                prevEl: '#electro-swiper-carousel .swiper-button-prev'
            }
        });
    }
});

