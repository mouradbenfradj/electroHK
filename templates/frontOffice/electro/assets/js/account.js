// Account Page JavaScript
$(document).ready(function() {
    // Account Navigation
    $('.account-sidebar .nav-link').on('click', function(e) {
        e.preventDefault();
        
        const $this = $(this);
        const $sidebar = $this.closest('.account-sidebar');
        
        // Remove active class from all links
        $sidebar.find('.nav-link').removeClass('active');
        
        // Add active class to clicked link
        $this.addClass('active');
        
        // Load content based on clicked link
        const targetUrl = $this.attr('href');
        if (targetUrl && targetUrl !== '#') {
            loadAccountContent(targetUrl);
        }
    });
    
    // Load account content via AJAX
    function loadAccountContent(url) {
        $.ajax({
            url: url,
            method: 'GET',
            dataType: 'html',
            beforeSend: function() {
                // Show loading spinner
                $('.account-content').html('<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-3x"></i></div>');
            },
            success: function(response) {
                // Update content
                $('.account-content').html(response);
                
                // Initialize any new content
                initializeAccountContent();
            },
            error: function() {
                $('.account-content').html('<div class="alert alert-danger">Error loading content. Please try again.</div>');
            }
        });
    }
    
    // Initialize account content
    function initializeAccountContent() {
        // Form validation
        $('.account-form').on('submit', function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $submitBtn = $form.find('button[type="submit"]');
            
            // Show loading state
            $submitBtn.html('<i class="fas fa-spinner fa-spin me-2"></i>Saving...');
            $submitBtn.prop('disabled', true);
            
            // Submit form via AJAX
            $.ajax({
                url: $form.attr('action'),
                method: $form.attr('method'),
                data: $form.serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showNotification('Information updated successfully!', 'success');
                        
                        // Reset button
                        $submitBtn.html('Save Changes');
                        $submitBtn.prop('disabled', false);
                    } else {
                        showNotification('Error updating information: ' + response.message, 'error');
                        
                        // Reset button
                        $submitBtn.html('Save Changes');
                        $submitBtn.prop('disabled', false);
                    }
                },
                error: function() {
                    showNotification('Error updating information. Please try again.', 'error');
                    
                    // Reset button
                    $submitBtn.html('Save Changes');
                    $submitBtn.prop('disabled', false);
                }
            });
        });
        
        // Password change validation
        $('#passwordForm').on('submit', function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $newPassword = $form.find('input[name="new_password"]');
            const $confirmPassword = $form.find('input[name="confirm_password"]');
            const $submitBtn = $form.find('button[type="submit"]');
            
            // Reset errors
            $form.find('.is-invalid').removeClass('is-invalid');
            $form.find('.invalid-feedback').remove();
            
            let isValid = true;
            
            // Validate new password
            if ($newPassword.val().length < 8) {
                $newPassword.addClass('is-invalid');
                $newPassword.after('<div class="invalid-feedback">Password must be at least 8 characters</div>');
                isValid = false;
            }
            
            // Validate password confirmation
            if ($newPassword.val() !== $confirmPassword.val()) {
                $confirmPassword.addClass('is-invalid');
                $confirmPassword.after('<div class="invalid-feedback">Passwords do not match</div>');
                isValid = false;
            }
            
            if (isValid) {
                // Show loading state
                $submitBtn.html('<i class="fas fa-spinner fa-spin me-2"></i>Updating...');
                $submitBtn.prop('disabled', true);
                
                // Submit via AJAX
                $.ajax({
                    url: $form.attr('action'),
                    method: $form.attr('method'),
                    data: $form.serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showNotification('Password updated successfully!', 'success');
                            
                            // Reset form
                            $form[0].reset();
                            
                            // Reset button
                            $submitBtn.html('Update Password');
                            $submitBtn.prop('disabled', false);
                        } else {
                            showNotification('Error updating password: ' + response.message, 'error');
                            
                            // Reset button
                            $submitBtn.html('Update Password');
                            $submitBtn.prop('disabled', false);
                        }
                    },
                    error: function() {
                        showNotification('Error updating password. Please try again.', 'error');
                        
                        // Reset button
                        $submitBtn.html('Update Password');
                        $submitBtn.prop('disabled', false);
                    }
                });
            }
        });
    }
    
    // Address management
    $('.address-delete').on('click', function(e) {
        e.preventDefault();
        
        const $this = $(this);
        const addressId = $this.data('address-id');
        
        if (confirm('Are you sure you want to delete this address?')) {
            $.ajax({
                url: '{url path="/address/delete/" + addressId + '"}',
                method: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showNotification('Address deleted successfully!', 'success');
                        
                        // Remove address from DOM
                        $this.closest('.card').fadeOut(function() {
                            $(this).remove();
                        });
                    } else {
                        showNotification('Error deleting address: ' + response.message, 'error');
                    }
                },
                error: function() {
                    showNotification('Error deleting address. Please try again.', 'error');
                }
            });
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
});
