// Brand Page JavaScript
$(document).ready(function() {
    // Brand Filter
    $('.brand-filter').on('input', function() {
        const searchTerm = $(this).val().toLowerCase();
        $('.brands-list .card').each(function() {
            const $card = $(this);
            const brandName = $card.find('h5').text().toLowerCase();
            
            if (brandName.includes(searchTerm)) {
                $card.show();
            } else {
                $card.hide();
            }
        });
    });
    
    // Sort brands
    $('.brand-sort').on('change', function() {
        const sortBy = $(this).val();
        const $brandsList = $('.brands-list');
        const $cards = $brandsList.find('.card');
        
        if (sortBy === 'name') {
            $cards.sort(function(a, b) {
                const nameA = $(a).find('h5').text().toLowerCase();
                const nameB = $(b).find('h5').text().toLowerCase();
                return nameA < nameB ? -1 : 1;
            });
        } else if (sortBy === 'products') {
            $cards.sort(function(a, b) {
                const countA = parseInt($(a).find('p').text().match(/\\d+/)[0]) || 0;
                const countB = parseInt($(b).find('p').text().match(/\\d+/)[0]) || 0;
                return countB - countA;
            });
        }
        
        $cards.detach().appendTo($brandsList);
    });
    
    // Product Quick View
    $('.btn-action[data-bs-target="#quickViewModal"]').on('click', function(e) {
        e.preventDefault();
        const $this = $(this);
        const $card = $this.closest('.card');
        const productTitle = $card.find('h2').text();
        const productImage = $card.find('img').attr('src');
        const productPrice = $card.find('.text-dark').text();
        const productDescription = $card.find('.text-muted').text();
        
        // Update modal content
        $('#quickViewModal .modal-title').text(productTitle);
        $('#quickViewModal .modal-body img').attr('src', productImage);
        $('#quickViewModal .modal-body .product-title').text(productTitle);
        $('#quickViewModal .modal-body .product-price').text(productPrice);
        $('#quickViewModal .modal-body .product-description').text(productDescription);
        
        // Show modal
        $('#quickViewModal').modal('show');
    });
    
    // Add to Cart
    $('.btn-primary:contains("Add")').on('click', function(e) {
        e.preventDefault();
        const $this = $(this);
        const $card = $this.closest('.card');
        const productId = $card.find('a').attr('href').split('/').pop();
        
        // Add loading state
        $this.html('<i class="fas fa-spinner fa-spin"></i> Adding...');
        $this.prop('disabled', true);
        
        // AJAX add to cart
        $.ajax({
            url: '{url path="/cart/add"}',
            method: 'POST',
            data: {
                product_id: productId,
                quantity: 1
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Update cart count
                    updateCartCount();
                    
                    // Show success message
                    showNotification('Product added to cart!', 'success');
                    
                    // Reset button
                    $this.html('<i class="feather-icon icon-plus"></i> Add');
                    $this.prop('disabled', false);
                } else {
                    // Show error message
                    showNotification('Error adding product to cart: ' + response.message, 'error');
                    
                    // Reset button
                    $this.html('<i class="feather-icon icon-plus"></i> Add');
                    $this.prop('disabled', false);
                }
            },
            error: function() {
                showNotification('Error adding product to cart', 'error');
                
                // Reset button
                $this.html('<i class="feather-icon icon-plus"></i> Add');
                $this.prop('disabled', false);
            }
        });
    });
    
    // Update cart count
    function updateCartCount() {
        $.ajax({
            url: '{url path="/cart/count"}',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                $('.cart-count').text(response.count || 0);
            }
        });
    }
    
    // Show notification
    function showNotification(message, type) {
        const notification = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close">&times;</button>' +
            message +
            '</div>';
        
        $('body').prepend(notification);
        
        // Auto remove after 5 seconds
        setTimeout(function() {
            $('.alert').fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
});
