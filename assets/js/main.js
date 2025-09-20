/**
 * Main JavaScript Functions
 * Hospital Management System
 */

$(document).ready(function() {
    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();
    
    // Initialize popovers
    $('[data-toggle="popover"]').popover();
    
    // Auto-hide alerts after 5 seconds (except error alerts)
    setTimeout(function() {
        $('.alert:not(.alert-danger)').fadeOut('slow');
    }, 5000);
    
    // Confirm delete operations
    $('.btn-delete').on('click', function(e) {
        if (!confirm('Are you sure you want to delete this record? This action cannot be undone.')) {
            e.preventDefault();
        }
    });
    
    // Form validation
    $('form').on('submit', function() {
        var isValid = true;
        
        // Check required fields
        $(this).find('[required]').each(function() {
            if ($(this).val() === '') {
                $(this).addClass('is-invalid');
                isValid = false;
            } else {
                $(this).removeClass('is-invalid');
            }
        });
        
        // Check email format
        $(this).find('input[type="email"]').each(function() {
            var email = $(this).val();
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email !== '' && !emailRegex.test(email)) {
                $(this).addClass('is-invalid');
                isValid = false;
            } else {
                $(this).removeClass('is-invalid');
            }
        });
        
        // Check phone number format
        $(this).find('input[type="tel"], input[name*="phone"]').each(function() {
            var phone = $(this).val();
            var phoneRegex = /^[0-9]{10}$/;
            
            if (phone !== '' && !phoneRegex.test(phone)) {
                $(this).addClass('is-invalid');
                isValid = false;
            } else {
                $(this).removeClass('is-invalid');
            }
        });
        
        return isValid;
    });
    
    // Search functionality
    $('#searchInput').on('keyup', function() {
        var value = $(this).val().toLowerCase();
        $('#dataTable tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });
    
    // Number input validation
    $('input[type="number"]').on('input', function() {
        var value = parseFloat($(this).val());
        var min = parseFloat($(this).attr('min'));
        var max = parseFloat($(this).attr('max'));
        
        if (min !== undefined && value < min) {
            $(this).val(min);
        }
        
        if (max !== undefined && value > max) {
            $(this).val(max);
        }
    });
    
    // Date input validation
    $('input[type="date"]').on('change', function() {
        var selectedDate = new Date($(this).val());
        var today = new Date();
        
        // If this is a future date field, validate accordingly
        if ($(this).hasClass('future-date') && selectedDate < today) {
            alert('Please select a future date.');
            $(this).val('');
        }
        
        // If this is a past date field, validate accordingly
        if ($(this).hasClass('past-date') && selectedDate > today) {
            alert('Please select a past or current date.');
            $(this).val('');
        }
    });
});

// Utility functions
function formatCurrency(amount) {
    return '₹' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

function formatDate(dateString) {
    var date = new Date(dateString);
    return date.toLocaleDateString('en-IN');
}

function showAlert(message, type = 'info') {
    var alertClass = 'alert-' + type;
    var iconClass = type === 'success' ? 'fa-check-circle' : 
                   type === 'danger' ? 'fa-exclamation-circle' : 
                   type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle';
    
    var alertHtml = '<div class="alert ' + alertClass + ' alert-dismissible fade show" role="alert">' +
                   '<i class="fas ' + iconClass + '"></i> ' + message +
                   '<button type="button" class="close" data-dismiss="alert">' +
                   '<span>&times;</span>' +
                   '</button>' +
                   '</div>';
    
    $('#alertContainer').html(alertHtml);
    
    // Auto-hide after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);
}