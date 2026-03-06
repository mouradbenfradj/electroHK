// Electro Theme - Main JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Swiper for hero slider
    if (document.querySelector('.hero-slider')) {
        new Swiper('.hero-slider', {
            loop: true,
            autoplay: {
                delay: 5000,
                disableOnInteraction: false
            },
            pagination: {
                el: '.hero-slider .swiper-pagination',
                clickable: true
            },
            navigation: {
                nextEl: '.hero-slider .swiper-button-next',
                prevEl: '.hero-slider .swiper-button-prev'
            },
            effect: 'fade',
            speed: 1000
        });
    }

    // Initialize Slick for category slider
    if (document.querySelector('.category-slider')) {
        $('.category-slider').slick({
            infinite: true,
            slidesToShow: 6,
            slidesToScroll: 1,
            prevArrow: '<span class="slick-prev slick-arrow"><i class="feather-icon icon-chevron-left"></i></span>',
            nextArrow: '<span class="slick-next slick-arrow"><i class="feather-icon icon-chevron-right"></i></span>',
            responsive: [
                {
                    breakpoint: 1024,
                    settings: {
                        slidesToShow: 4
                    }
                },
                {
                    breakpoint: 768,
                    settings: {
                        slidesToShow: 2
                    }
                },
                {
                    breakpoint: 480,
                    settings: {
                        slidesToShow: 1
                    }
                }
            ]
        });
    }

    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl, {
            trigger: 'hover focus'
        });
    });

    // Product card actions
    document.addEventListener('click', function(e) {
        if (e.target.closest('.btn-action')) {
            e.preventDefault();
            const action = e.target.closest('.btn-action');
            
            if (action.getAttribute('data-bs-target') === '#quickViewModal') {
                // Quick view functionality
                const productCard = action.closest('.card-product');
                const productTitle = productCard.querySelector('h2').textContent;
                const productImage = productCard.querySelector('img').src;
                const productPrice = productCard.querySelector('.text-dark').textContent;
                
                // Update modal content
                const modal = document.querySelector('#quickViewModal');
                if (modal) {
                    modal.querySelector('.modal-title').textContent = productTitle;
                    modal.querySelector('.modal-body img').src = productImage;
                    modal.querySelector('.product-title').textContent = productTitle;
                    modal.querySelector('.product-price').textContent = productPrice;
                    
                    new bootstrap.Modal(modal).show();
                }
            }
        }
    });

    // Quantity spinners
    document.addEventListener('click', function(e) {
        const button = e.target.closest('.button-minus, .button-plus');
        if (button) {
            e.preventDefault();
            const inputGroup = button.closest('.input-spinner');
            const input = inputGroup.querySelector('.quantity-field');
            const currentValue = parseInt(input.value) || 0;
            const minValue = parseInt(input.getAttribute('min')) || 1;
            const maxValue = parseInt(input.getAttribute('max')) || 10;
            
            let newValue = currentValue;
            if (button.classList.contains('button-minus')) {
                newValue = Math.max(currentValue - 1, minValue);
            } else if (button.classList.contains('button-plus')) {
                newValue = Math.min(currentValue + 1, maxValue);
            }
            
            input.value = newValue;
        }
    });

    // Form validation
    var forms = document.querySelectorAll('.needs-validation');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
                
                // Add validation styles
                form.classList.add('was-validated');
                
                // Show custom validation messages
                const inputs = form.querySelectorAll('input[required], textarea[required]');
                inputs.forEach(function(input) {
                    if (!input.value.trim()) {
                        input.classList.add('is-invalid');
                        
                        // Add or update error message
                        let errorDiv = input.parentNode.querySelector('.invalid-feedback');
                        if (!errorDiv) {
                            errorDiv = document.createElement('div');
                            errorDiv.className = 'invalid-feedback';
                            errorDiv.textContent = 'This field is required';
                            input.parentNode.appendChild(errorDiv);
                        }
                    } else {
                        input.classList.remove('is-invalid');
                        const errorDiv = input.parentNode.querySelector('.invalid-feedback');
                        if (errorDiv) {
                            errorDiv.remove();
                        }
                    }
                });
            }
        });
    });

    // Newsletter form
    const newsletterForm = document.querySelector('form[action*="newsletter"]');
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = newsletterForm.querySelector('input[type="email"]').value;
            
            if (email && validateEmail(email)) {
                // Show loading state
                const submitBtn = newsletterForm.querySelector('button[type="submit"]');
                const originalText = submitBtn.textContent;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Subscribing...';
                submitBtn.disabled = true;
                
                // Simulate API call
                setTimeout(function() {
                    submitBtn.textContent = 'Subscribed!';
                    submitBtn.classList.remove('btn-primary');
                    submitBtn.classList.add('btn-success');
                    
                    setTimeout(function() {
                        submitBtn.textContent = originalText;
                        submitBtn.disabled = false;
                        submitBtn.classList.remove('btn-success');
                        submitBtn.classList.add('btn-primary');
                        newsletterForm.reset();
                    }, 2000);
                }, 1500);
            }
        });
    }

    // Email validation helper
    function validateEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(anchor.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Lazy loading for images
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver(function(entries, observer) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    imageObserver.unobserve(img);
                }
            });
        });

        document.querySelectorAll('img[data-src]').forEach(function(img) {
            img.classList.add('lazy');
            imageObserver.observe(img);
        });
    }

    // Cart functionality
    function updateCartCount() {
        fetch('{url path="/cart/count"}', {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function(response) {
            if (response.ok) {
                return response.json();
            }
            throw new Error('Network response was not ok');
        })
        .then(function(data) {
            const cartCountElements = document.querySelectorAll('.cart-count');
            cartCountElements.forEach(function(element) {
                element.textContent = data.count || 0;
            });
        })
        .catch(function(error) {
            console.error('Error updating cart count:', error);
        });
    }

    // Initialize cart count on page load
    updateCartCount();

    // Search functionality
    const searchInput = document.querySelector('input[type="search"]');
    if (searchInput) {
        let searchTimeout;
        
        searchInput.addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                const query = e.target.value.trim();
                if (query.length > 2) {
                    // Perform search
                    performSearch(query);
                }
            }, 300);
        });
    }

    function performSearch(query) {
        fetch('{url path="/search"}?q=' + encodeURIComponent(query), {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function(response) {
            if (response.ok) {
                return response.json();
            }
            throw new Error('Search failed');
        })
        .then(function(data) {
            // Handle search results
            console.log('Search results:', data);
        })
        .catch(function(error) {
            console.error('Search error:', error);
        });
    }

    // Notification system
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show`;
        notification.setAttribute('role', 'alert');
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        document.body.appendChild(notification);
        
        // Auto remove after 5 seconds
        setTimeout(function() {
            notification.remove();
        }, 5000);
    }

    // Initialize everything when page is ready
    console.log('Electro theme initialized');
});
