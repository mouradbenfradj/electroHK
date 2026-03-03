// Category Page JavaScript
$(document).ready(function() {
    // Price Range Slider
    if ($('#priceRange').length) {
        noUiSlider('#priceRange', {
            range: {
                min: 0,
                max: 1000,
                step: 10,
                values: [0, 50, 100, 250, 500, 1000]
            },
            slide: function(event, ui) {
                $('#priceMin').val(ui.values[0]);
                $('#priceMax').val(ui.values[1]);
            }
        });
    }
    
    // Filter Checkboxes
    $('.rating-filter .form-check-input').on('change', function() {
        const $form = $(this).closest('form');
        const checkedFilters = [];
        
        $('.rating-filter .form-check-input:checked').each(function() {
            checkedFilters.push($(this).val());
        });
        
        // Update product display based on filters
        $('.products-grid .card').each(function() {
            const $card = $(this);
            const productRating = $card.data('rating') || 0;
            
            // Show/hide based on rating filter
            if (checkedFilters.length === 0 || checkedFilters.includes(productRating.toString())) {
                $card.show();
            } else {
                $card.hide();
            }
        });
    });
    
    // Sort functionality
    $('.sort-select').on('change', function() {
        const sortValue = $(this).val();
        const $productsGrid = $('.products-grid');
        
        // Sort products based on selection
        if (sortValue === 'alpha') {
            const $sortedProducts = $productsGrid.find('.card').sort(function(a, b) {
                const $titleA = $(a).find('h2').text().toLowerCase();
                const $titleB = $(b).find('h2').text().toLowerCase();
                return $titleA < $titleB ? -1 : 1;
            });
            
            $productsGrid.find('.card').hide().appendTo($productsGrid);
            $sortedProducts.show();
        } else if (sortValue === 'alpha_reverse') {
            const $sortedProducts = $productsGrid.find('.card').sort(function(a, b) {
                const $titleA = $(a).find('h2').text().toLowerCase();
                const $titleB = $(b).find('h2').text().toLowerCase();
                return $titleA > $titleB ? 1 : -1;
            });
            
            $productsGrid.find('.card').hide().appendTo($productsGrid);
            $sortedProducts.show();
        } else if (sortValue === 'min_price') {
            const $sortedProducts = $productsGrid.find('.card').sort(function(a, b) {
                const $priceA = parseFloat($(a).find('.text-dark').first().text().replace(/[^0-9.]/g, ''));
                const $priceB = parseFloat($(b).find('.text-dark').first().text().replace(/[^0-9.]/g, ''));
                return $priceA < $priceB ? -1 : 1;
            });
            
            $productsGrid.find('.card').hide().appendTo($productsGrid);
            $sortedProducts.show();
        } else if (sortValue === 'max_price') {
            const $sortedProducts = $productsGrid.find('.card').sort(function(a, b) {
                const $priceA = parseFloat($(a).find('.text-dark').first().text().replace(/[^0-9.]/g, ''));
                const $priceB = parseFloat($(b).find('.text-dark').first().text().replace(/[^0-9.]/g, ''));
                return $priceA > $priceB ? 1 : -1;
            });
            
            $productsGrid.find('.card').hide().appendTo($productsGrid);
            $sortedProducts.show();
        }
    });
    
    // Show/Hide products per page
    $('.show-select').on('change', function() {
        const limit = parseInt($(this).val());
        $('.products-grid .card').hide();
        $('.products-grid .card').slice(0, limit).show();
    });
});
