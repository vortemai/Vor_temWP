/**
 * Settings Page Form Validation JavaScript
 *
 * This file contains the inline JavaScript that was previously in class-vortem-admin.php.
 * Moved to external JS file for WordPress best practices.
 *
 * External Libraries Used:
 * - jQuery (jQuery Foundation) - https://jquery.com/ | License: MIT | WordPress-bundled library used for form validation and DOM manipulation
 *
 * @package VortemAI
 * @since 1.0.5
 */

jQuery(document).ready(function($) {
    // Translation strings from vortem_admin.strings
    var strings = (typeof vortem_admin !== 'undefined' && vortem_admin.strings) ? vortem_admin.strings : {
        products_per_page_min_error: 'Products per page must be at least 1. Please enter a valid value.',
        products_per_page_max_error: 'Products per page cannot exceed 100. Please enter a value between 1 and 100.',
        products_per_page_min_title: 'Products per page must be at least 1',
        products_per_page_max_title: 'Products per page cannot exceed 100'
    };
    
    // Validate products per page on form submission
    $('#vortem-settings-form').on('submit', function(e) {
        var productsPerPage = parseInt($('input[name="vortem_products_per_page"]').val());
        
        if (isNaN(productsPerPage) || productsPerPage < 1) {
            e.preventDefault();
            alert(strings.products_per_page_min_error || 'Products per page must be at least 1. Please enter a valid value.');
            $('input[name="vortem_products_per_page"]').focus();
            return false;
        }
        
        if (productsPerPage > 100) {
            e.preventDefault();
            alert(strings.products_per_page_max_error || 'Products per page cannot exceed 100. Please enter a value between 1 and 100.');
            $('input[name="vortem_products_per_page"]').focus();
            return false;
        }
    });
    
    // Real-time validation on input change
    $('input[name="vortem_products_per_page"]').on('input', function() {
        var productsPerPage = parseInt($(this).val());
        var $this = $(this);
        
        // Remove existing validation classes
        $this.removeClass('error valid');
        
        if (isNaN(productsPerPage) || productsPerPage < 1) {
            $this.addClass('error');
            $this.attr('title', strings.products_per_page_min_title || 'Products per page must be at least 1');
        } else if (productsPerPage > 100) {
            $this.addClass('error');
            $this.attr('title', strings.products_per_page_max_title || 'Products per page cannot exceed 100');
        } else {
            $this.addClass('valid');
            $this.removeAttr('title');
        }
    });

    // Tab switching functionality (consistent with analytics pattern)
    $('.tab').on('click', function() {
        const tab = $(this).data('tab');
        $('.tab').removeClass('active');
        $(this).addClass('active');
        $('.panel').removeClass('active');
        $('#panel-' + tab).addClass('active');
        
        // Update URL to use separate page parameter (like analytics)
        const url = new URL(window.location);
        const page = tab === 'orders' ? 'vortem-orders' : 'vortem-products';
        url.searchParams.set('page', page);
        url.searchParams.delete('tab'); // Remove tab parameter if it exists
        window.history.pushState({}, '', url);
    });

    // Track the original currency to detect changes
    var originalCurrency = null;
    var $updateButton = $('#vortem-update-currency');
    var $updateButtonWrapper = $('.vortem-currency-button-wrapper');
    var $currencySelect = $('#vortem_currency');

    // Load currencies from API
    function loadCurrenciesFromAPI() {
        var currentCurrency = null;

        // Check if vortem_admin is available
        if (typeof vortem_admin === 'undefined') {
            if (typeof VortemLogger !== 'undefined') {
                VortemLogger.error('vortem_admin is not defined');
            }
            return;
        }

        // First, fetch the current currency from API
        $.ajax({
            url: vortem_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'vortem_get_current_currency',
                nonce: vortem_admin.nonce
            },
            success: function(response) {
                if (response.success && response.data && response.data.customer_currency) {
                    currentCurrency = response.data.customer_currency;
                }
                
                // Now fetch the currency list
                fetchCurrencyList(currentCurrency);
            },
            error: function(xhr, status, error) {
                if (typeof VortemLogger !== 'undefined') {
                    VortemLogger.warn('Failed to fetch current currency, using default');
                }
                // Fetch currency list anyway
                fetchCurrencyList(currentCurrency);
            }
        });
    }

    // Fetch currency list from API
    function fetchCurrencyList(currentCurrency) {
        var $dropdown = $('#vortem-currency-select-dropdown');
        
        // Check if vortem_admin is available
        if (typeof vortem_admin === 'undefined') {
            if (typeof VortemLogger !== 'undefined') {
                VortemLogger.error('vortem_admin is not defined');
            }
            return;
        }

        $.ajax({
            url: vortem_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'vortem_get_currencies',
                nonce: vortem_admin.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    var currencies = response.data;
                    
                    // Clear loading state
                    $dropdown.find('.vortem-currency-loading').hide();
                    
                    // Build currency options
                    var $currencyList = $('<div class="vortem-currency-list"></div>');
                    
                    // Get current selected currency from the select
                    var selectedCurrency = $('#vortem_currency').val();
                    
                    // Add each currency to the list
                    $.each(currencies, function(index, currency) {
                        var $option = $('<div class="vortem-currency-option" data-value="' + currency.code + '">' +
                            '<span class="vortem-currency-flag">' + (currency.flag || '') + '</span>' +
                            '<span class="vortem-currency-code">' + currency.code + '</span>' +
                            '<span class="vortem-currency-name">' + currency.name + '</span>' +
                        '</div>');
                        
                        // Mark as selected if it matches
                        if (currency.code === selectedCurrency) {
                            $option.addClass('selected');
                        }
                        
                        $currencyList.append($option);
                    });
                    
                    // Replace loading with currency list
                    $dropdown.find('.vortem-currency-loading').after($currencyList);
                    
                    // Handle currency selection
                    $('.vortem-currency-option').on('click', function() {
                        var selectedValue = $(this).data('value');
                        var selectedName = $(this).find('.vortem-currency-name').text();
                        
                        // Update select value
                        $('#vortem_currency').val(selectedValue);
                        
                        // Update button text
                        var $buttonText = $('#vortem-currency-select-button .vortem-currency-text');
                        $buttonText.text(selectedName);
                        
                        // Show update button
                        $updateButtonWrapper.show();
                        
                        // Close dropdown
                        $('#vortem-currency-select-dropdown').hide();
                    });
                    
                    if (typeof VortemLogger !== 'undefined') {
                        VortemLogger.log('Currencies loaded successfully:', currencies.length);
                    }
                } else {
                    // Show error message
                    var failedText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.failed_to_load_currencies) ? vortem_admin.strings.failed_to_load_currencies : 'Failed to load currencies';
                    $dropdown.find('.vortem-currency-loading').text(failedText);
                    if (typeof VortemLogger !== 'undefined') {
                        VortemLogger.warn('Failed to load currencies from API');
                    }
                }
            },
            error: function(xhr, status, error) {
                // Show error message
                var errorText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.error_loading_currencies) ? vortem_admin.strings.error_loading_currencies : 'Error loading currencies';
                $dropdown.find('.vortem-currency-loading').text(errorText);
                if (typeof VortemLogger !== 'undefined') {
                    VortemLogger.error('Error loading currencies from API:', error);
                }
            }
        });
    }

    // Toggle currency dropdown
    $('#vortem-currency-select-button').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var $dropdown = $('#vortem-currency-select-dropdown');
        
        if ($dropdown.is(':visible')) {
            $dropdown.hide();
        } else {
            $dropdown.show();
            
            // Load currencies if not already loaded
            if ($dropdown.find('.vortem-currency-list').length === 0) {
                loadCurrenciesFromAPI();
            }
        }
    });

    // Close dropdown when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#vortem-currency-select-button, #vortem-currency-select-dropdown').length) {
            $('#vortem-currency-select-dropdown').hide();
        }
    });

    // Handle update currency button click
    $updateButton.on('click', function(e) {
        e.preventDefault();
        
        var newCurrency = $('#vortem_currency').val();
        var currentCurrency = $('#vortem_currency').data('current');
        
        if (newCurrency === currentCurrency) {
            if (typeof VortemLogger !== 'undefined') {
                VortemLogger.log('Currency unchanged, no update needed');
            }
            return;
        }
        
        // Disable button and show loading state
        $updateButton.prop('disabled', true).text('Updating...');
        
        // Make AJAX call to update currency
        $.ajax({
            url: vortem_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'vortem_update_currency',
                currency: newCurrency,
                nonce: vortem_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    if (typeof VortemLogger !== 'undefined') {
                        VortemLogger.log('Currency updated successfully to:', newCurrency);
                    }
                    
                    // Update current currency data attribute
                    $('#vortem_currency').data('current', newCurrency);
                    
                    // Hide update button
                    $updateButtonWrapper.hide();
                    
                    // Show success message
                    if (typeof showNotice === 'function') {
                        showNotice('Currency updated successfully!', 'success');
                    }
                    
                    // Reload page to reflect changes
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    if (typeof VortemLogger !== 'undefined') {
                        VortemLogger.error('Failed to update currency:', response.data);
                    }
                    
                    // Show error message
                    if (typeof showNotice === 'function') {
                        showNotice('Failed to update currency. Please try again.', 'error');
                    }
                }
            },
            error: function(xhr, status, error) {
                if (typeof VortemLogger !== 'undefined') {
                    VortemLogger.error('Error updating currency:', error);
                }
                
                // Show error message
                if (typeof showNotice === 'function') {
                    showNotice('Error updating currency. Please try again.', 'error');
                }
            },
            complete: function() {
                // Re-enable button
                $updateButton.prop('disabled', false).text('Update Currency');
            }
        });
    });

    // Connect to AliExpress button click handler
    $('#vortem-connect-aliexpress').on('click', function() {
        var $button = $(this);
        
        // Disable button and show loading state
        $button.prop('disabled', true).text('Connecting...');
        
        // Make AJAX call to connect to AliExpress
        $.ajax({
            url: vortem_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'vortem_connect_aliexpress',
                nonce: vortem_admin.nonce
            },
            success: function(response) {
                if (response.success && response.data && response.data.auth_url) {
                    // Redirect to AliExpress OAuth page
                    window.location.href = response.data.auth_url;
                } else {
                    if (typeof VortemLogger !== 'undefined') {
                        VortemLogger.error('Failed to connect to AliExpress:', response.data);
                    }
                    
                    // Show error message
                    if (typeof showNotice === 'function') {
                        showNotice('Failed to connect to AliExpress. Please try again.', 'error');
                    }
                    
                    // Re-enable button
                    $button.prop('disabled', false).text('Connect to AliExpress');
                }
            },
            error: function(xhr, status, error) {
                if (typeof VortemLogger !== 'undefined') {
                    VortemLogger.error('Error connecting to AliExpress:', error);
                }
                
                // Show error message
                if (typeof showNotice === 'function') {
                    showNotice('Error connecting to AliExpress. Please try again.', 'error');
                }
                
                // Re-enable button
                $button.prop('disabled', false).text('Connect to AliExpress');
            }
        });
    });

    // Disconnect from AliExpress button click handler
    $('#vortem-disconnect-aliexpress').on('click', function() {
        var $button = $(this);
        
        // Disable button and show loading state
        $button.prop('disabled', true).text('Disconnecting...');
        
        // Make AJAX call to disconnect from AliExpress
        $.ajax({
            url: vortem_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'vortem_disconnect_aliexpress',
                nonce: vortem_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    if (typeof VortemLogger !== 'undefined') {
                        VortemLogger.log('Successfully disconnected from AliExpress');
                    }
                    
                    // Show success message
                    if (typeof showNotice === 'function') {
                        showNotice('Successfully disconnected from AliExpress.', 'success');
                    }
                    
                    // Reload page to reflect changes
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    if (typeof VortemLogger !== 'undefined') {
                        VortemLogger.error('Failed to disconnect from AliExpress:', response.data);
                    }
                    
                    // Show error message
                    if (typeof showNotice === 'function') {
                        showNotice('Failed to disconnect from AliExpress. Please try again.', 'error');
                    }
                    
                    // Re-enable button
                    $button.prop('disabled', false).text('Disconnect');
                }
            },
            error: function(xhr, status, error) {
                if (typeof VortemLogger !== 'undefined') {
                    VortemLogger.error('Error disconnecting from AliExpress:', error);
                }
                
                // Show error message
                if (typeof showNotice === 'function') {
                    showNotice('Error disconnecting from AliExpress. Please try again.', 'error');
                }
                
                // Re-enable button
                $button.prop('disabled', false).text('Disconnect');
            }
        });
    });

    // AliExpress modal close button
    $('#vortem-aliexpress-modal-close').on('click', function() {
        $('#vortem-aliexpress-modal').hide();
    });

    // AliExpress modal OK button
    $('#vortem-aliexpress-modal-ok').on('click', function() {
        $('#vortem-aliexpress-modal').hide();
    });

    // AliExpress modal cancel button
    $('#vortem-aliexpress-modal-cancel').on('click', function() {
        $('#vortem-aliexpress-modal').hide();
    });

    // Close AliExpress modal when clicking overlay
    $('.vortem-aliexpress-modal-overlay').on('click', function() {
        $('#vortem-aliexpress-modal').hide();
    });
});
