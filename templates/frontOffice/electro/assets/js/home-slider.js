/**
 * Home Page JavaScript
 * Handles product sliders, cart interactions, and other home page functionality
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Initialize Tiny Slider for products if available
    if (typeof tns !== 'undefined') {
        try {
            const productsSlider = tns({
                container: '.products-slider',
                items: 1,
                slideBy: 'page',
                autoplay: true,
                autoplayTimeout: 5000,
                autoplayButtonOutput: false,
                mouseDrag: true,
                gutter: 20,
                nav: false,
                controls: true,
                controlsContainer: '#prev-products, #next-products',
                responsive: {
                    576: {
                        items: 2,
                        gutter: 20
                    },
                    768: {
                        items: 3,
                        gutter: 25
                    },
                    992: {
                        items: 4,
                        gutter: 30
                    },
                    1200: {
                        items: 4,
                        gutter: 30
                    }
                }
            });
        } catch (error) {
            console.log('Product slider not initialized - element not found');
        }
    }

    // Initialize Hero Slider if exists
    if (typeof tns !== 'undefined') {
        try {
            const heroSlider = tns({
                container: '.slider',
                items: 1,
                slideBy: 'page',
                autoplay: true,
                autoplayTimeout: 7000,
                autoplayButtonOutput: false,
                mouseDrag: true,
                nav: true,
                controls: false,
                mode: 'gallery',
                speed: 1000
            });
        } catch (error) {
            console.log('Hero slider not initialized - element not found');
        }
    }

    // Cart functionality
    initializeCartFunctionality();
    
    // Wishlist functionality
    initializeWishlistFunctionality();
    
    // Tooltips
    initializeTooltips();
    
    // Form validation
    initializeFormValidation();
});

/**
 * Cart functionality
 */
function initializeCartFunctionality() {
    // Add to cart forms
    const addToCartForms = document.querySelectorAll('form[name="thelia.cart.add"]');
    
    addToCartForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitButton = form.querySelector('button[type="submit"]');
            const originalText = submitButton.innerHTML;
            
            // Show loading state
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>{intl l="Adding"}...';
            
            // Submit form normally for now (in real implementation, use AJAX)
            setTimeout(() => {
                submitButton.disabled = false;
                submitButton.innerHTML = originalText;
                
                // Show success message
                showNotification('Product added to cart!', 'success');
                
                // Update cart count
                updateCartCount();
            }, 1000);
        });
    });
    
    // Cart update forms
    const updateCartForms = document.querySelectorAll('form[name="thelia.cart.update"]');
    
    updateCartForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(form);
            // In real implementation, send AJAX request to update cart
            
            showNotification('Cart updated!', 'info');
            updateCartCount();
        });
    });
    
    // Cart delete forms
    const deleteCartForms = document.querySelectorAll('form[name="thelia.cart.delete"]');
    
    deleteCartForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (confirm('Are you sure you want to remove this item?')) {
                const cartItem = form.closest('li');
                cartItem.style.opacity = '0.5';
                
                setTimeout(() => {
                    cartItem.remove();
                    showNotification('Item removed from cart', 'warning');
                    updateCartCount();
                }, 300);
            }
        });
    });
}

/**
 * Wishlist functionality
 */
function initializeWishlistFunctionality() {
    const wishlistButtons = document.querySelectorAll('[data-wishlist]');
    
    wishlistButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const isInWishlist = this.classList.contains('in-wishlist');
            
            if (isInWishlist) {
                this.classList.remove('in-wishlist');
                showNotification('Removed from wishlist', 'info');
            } else {
                this.classList.add('in-wishlist');
                showNotification('Added to wishlist!', 'success');
            }
        });
    });
}

/**
 * Initialize tooltips
 */
function initializeTooltips() {
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
}

/**
 * Form validation
 */
function initializeFormValidation() {
    // Bootstrap form validation
    const forms = document.querySelectorAll('.needs-validation');
    
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
}

/**
 * Update cart count
 */
function updateCartCount() {
    const cartCountElement = document.getElementById('cartCount');
    if (cartCountElement) {
        // In real implementation, get actual cart count from API
        const currentCount = parseInt(cartCountElement.textContent.trim()) || 0;
        cartCountElement.textContent = currentCount + 1;
    }
}

/**
 * Show notification
 */
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
    notification.style.zIndex = '9999';
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 3000);
}

/**
 * Language switcher
 */
function changeLanguage(language) {
    // In real implementation, handle language change
    console.log('Changing language to:', language);
    document.getElementById('selectedLanguage').textContent = language;
    
    // Show notification
    showNotification(`Language changed to ${language}`, 'info');
}

/**
 * Search functionality
 */
function initializeSearch() {
    const searchForm = document.querySelector('form[action="{navigate to=\\"search\\"}"]');
    
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            const searchInput = this.querySelector('input[name="q"]');
            
            if (!searchInput.value.trim()) {
                e.preventDefault();
                searchInput.focus();
                showNotification('Please enter a search term', 'warning');
            }
        });
    }
}

// Initialize search when DOM is ready
document.addEventListener('DOMContentLoaded', initializeSearch);

/**
 * Quantity controls
 */
function initializeQuantityControls() {
    const quantityInputs = document.querySelectorAll('input[name="quantity"]');
    
    quantityInputs.forEach(input => {
        const minusBtn = input.parentElement.querySelector('.button-minus');
        const plusBtn = input.parentElement.querySelector('.button-plus');
        
        if (minusBtn) {
            minusBtn.addEventListener('click', function() {
                const currentValue = parseInt(input.value) || 1;
                const minValue = parseInt(input.min) || 1;
                input.value = Math.max(minValue, currentValue - 1);
            });
        }
        
        if (plusBtn) {
            plusBtn.addEventListener('click', function() {
                const currentValue = parseInt(input.value) || 1;
                const maxValue = parseInt(input.max) || 99;
                input.value = Math.min(maxValue, currentValue + 1);
            });
        }
    });
}

document.addEventListener('DOMContentLoaded', initializeQuantityControls);