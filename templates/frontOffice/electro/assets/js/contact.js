// Contact Page JavaScript
$(document).ready(function() {
    // Form Validation
    $('#form-contact').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $submitBtn = $form.find('button[type="submit"]');
        
        // Reset previous errors
        $form.find('.is-invalid').removeClass('is-invalid');
        $form.find('.invalid-feedback').remove();
        
        // Validate fields
        let isValid = true;
        
        // Name validation
        const $nameField = $form.find('input[name="name"]');
        if ($nameField.val().trim() === '') {
            $nameField.addClass('is-invalid');
            $nameField.after('<div class="invalid-feedback">Name is required</div>');
            isValid = false;
        }
        
        // Email validation
        const $emailField = $form.find('input[name="email"]');
        const emailRegex = /^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/;
        if (!emailRegex.test($emailField.val())) {
            $emailField.addClass('is-invalid');
            $emailField.after('<div class="invalid-feedback">Please enter a valid email address</div>');
            isValid = false;
        }
        
        // Subject validation
        const $subjectField = $form.find('input[name="subject"]');
        if ($subjectField.val().trim() === '') {
            $subjectField.addClass('is-invalid');
            $subjectField.after('<div class="invalid-feedback">Subject is required</div>');
            isValid = false;
        }
        
        // Message validation
        const $messageField = $form.find('textarea[name="message"]');
        if ($messageField.val().trim() === '') {
            $messageField.addClass('is-invalid');
            $messageField.after('<div class="invalid-feedback">Message is required</div>');
            isValid = false;
        }
        
        if (isValid) {
            // Show loading state
            $submitBtn.html('<i class="fas fa-spinner fa-spin me-2"></i>Sending...');
            $submitBtn.prop('disabled', true);
            
            // Submit form via AJAX
            $.ajax({
                url: $form.attr('action'),
                method: $form.attr('method'),
                data: $form.serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        showNotification('Message sent successfully!', 'success');
                        
                        // Reset form
                        $form[0].reset();
                        
                        // Reset button
                        $submitBtn.html('<i class="feather-icon icon-send me-2"></i>Send Message');
                        $submitBtn.prop('disabled', false);
                    } else {
                        // Show error message
                        showNotification('Error sending message: ' + response.message, 'error');
                        
                        // Reset button
                        $submitBtn.html('<i class="feather-icon icon-send me-2"></i>Send Message');
                        $submitBtn.prop('disabled', false);
                    }
                },
                error: function() {
                    showNotification('Error sending message. Please try again.', 'error');
                    
                    // Reset button
                    $submitBtn.html('<i class="feather-icon icon-send me-2"></i>Send Message');
                    $submitBtn.prop('disabled', false);
                }
            });
        }
    });
    
    // Real-time validation
    $('#form-contact input, #form-contact textarea').on('blur', function() {
        const $this = $(this);
        const $formGroup = $this.closest('.mb-3');
        
        // Remove previous error
        $this.removeClass('is-invalid');
        $formGroup.find('.invalid-feedback').remove();
        
        // Validate based on field type
        if ($this.attr('name') === 'email') {
            const emailRegex = /^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/;
            if (!emailRegex.test($this.val())) {
                $this.addClass('is-invalid');
                $this.after('<div class="invalid-feedback">Please enter a valid email address</div>');
            }
        } else if ($this.val().trim() === '') {
            $this.addClass('is-invalid');
            $this.after('<div class="invalid-feedback">This field is required</div>');
        }
    });
    
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
    
    // Initialize map (if Google Maps API is available)
    if (typeof google !== 'undefined' && $('#map').length) {
        const mapElement = document.getElementById('map');
        const address = $('#map').data('address');
        
        const geocoder = new google.maps.Geocoder();
        const map = new google.maps.Map(mapElement, {
            zoom: 15,
            center: {lat: -34.397, lng: 150.644}
        });
        
        geocoder.geocode({ 'address': address }, function(results, status) {
            if (status === 'OK') {
                map.setCenter(results[0].geometry.location);
                new google.maps.Marker({
                    map: map,
                    position: results[0].geometry.location
                });
            }
        });
    }
});
