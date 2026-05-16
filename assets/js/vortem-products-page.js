/**
 * Products Page JavaScript
 *
 * Inline scripts moved to external JS file for WordPress best practices.
 * This file handles all products/orders page functionality.
 *
 * External Libraries Used:
 * - jQuery (jQuery Foundation) - https://jquery.com/ | License: MIT | WordPress-bundled library used for DOM manipulation and AJAX
 *
 * @package VortemAI
 * @since 1.0.5
 */

(function($) {
    'use strict';

    // Define vortemShowNotice function at the very beginning so it's available everywhere
    window.vortemShowNotice = function(message, type) {
        type = type || 'info';
        var noticeClass = 'notice notice-' + type + ' is-dismissible vortem-plugin-notice';
        var noticeHtml = '<div class="' + noticeClass + '" style="margin: 20px 0; padding: 15px;"><p>' + message + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>';
        
        // Try to find vortem-page-content or wrap, otherwise use body
        var container = $('.vortem-page-content').first();
        if (container.length === 0) {
            container = $('.wrap').first();
        }
        if (container.length === 0) {
            container = $('body');
        }
        
        // Prepend notice to container
        container.prepend(noticeHtml);
        
        // Make dismissible
        $(document).trigger('wp-updates-notice-added');
        
        // Auto-dismiss after 5 seconds for success messages
        if (type === 'success') {
            setTimeout(function() {
                $('.notice.is-dismissible').first().fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };
    
    // Function to format number - always use English numerals
    function formatNumberForLanguage(num) {
        if (num === null || num === undefined || num === '') {
            return num;
        }
        return String(num);
    }
    
    // Fetch auth status from API via WordPress AJAX (to avoid CORS)
    (function() {
        // Only fetch if we have vortem_admin object and nonce
    })();
    
    // Function to update imported products count via AJAX
    (function() {
        function updateImportedProductsCount() {
            if (typeof vortem_admin !== 'undefined' && vortem_admin.nonce) {
                $.ajax({
                    url: vortem_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'vortem_get_imported_products_count',
                        nonce: vortem_admin.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data && response.data.count !== undefined) {
                            var importedProductsEl = document.getElementById('imported-products');
                            var topProductsImportedEl = document.getElementById('top-products-imported-count');
                            if (importedProductsEl) {
                                var count = parseInt(response.data.count) || 0;
                                importedProductsEl.textContent = formatNumberForLanguage(count.toLocaleString());
                            }
                            if (topProductsImportedEl) {
                                var count = parseInt(response.data.count) || 0;
                                topProductsImportedEl.textContent = formatNumberForLanguage(count.toLocaleString());
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        if (typeof VortemLogger !== 'undefined') {
                            VortemLogger.error('Error fetching imported products count:', error);
                        }
                    }
                });
            }
        }
        
        // Update imported products count on page load
        updateImportedProductsCount();
        
        // Set up periodic updates every 30 seconds for all stats
        setInterval(function() {
            // Update auth status
            
            // Update imported products count
            updateImportedProductsCount();
        }, 30000); // Update every 30 seconds
    })();
    
    // Loading overlay functions
    var defaultProcessingText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.processing) ? vortem_admin.strings.processing : 'Processing...';
    var defaultSubmessageText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.please_wait_import) ? vortem_admin.strings.please_wait_import : 'Please wait while we import your product';
    
    function showLoading(message, defaultProcessingText, submessage, defaultSubmessageText) {
        $('.vortem-loading-text').text(message);
        $('.vortem-loading-subtext').text(submessage);
        $('#vortem-loading-overlay').show();
        $('body').css('overflow', 'hidden'); // Prevent scrolling
    }

    function hideLoading() {
        $('#vortem-loading-overlay').hide();
        $('body').css('overflow', 'auto'); // Restore scrolling
    }

    // Show delete confirmation modal
    function showDeleteConfirmationModal(message, callback) {
        var modal = $('#delete-product-modal');
        var modalMessage = $('#delete-product-modal-message');
        
        if (modal.length === 0) {
            // Fallback to confirm if modal doesn't exist
            if (confirm(message)) {
                callback();
            }
            return;
        }

        modalMessage.text(message);
        modal.show();

        // Remove previous event handlers
        $('#confirm-delete-product-btn, #cancel-delete-product-btn, .vortem-modal-overlay').off('click');

        // Handle confirm button
        $('#confirm-delete-product-btn').on('click', function() {
            modal.hide();
            callback();
        });

        // Handle cancel button and overlay
        $('#cancel-delete-product-btn, .vortem-modal-overlay').on('click', function() {
            modal.hide();
        });
    }

    // Force vortem actions to always be visible
    $('.vortem-actions').css({
        'opacity': '1',
        'visibility': 'visible',
        'display': 'flex'
    });

    // Initialize when document is ready
    $(document).ready(function() {
        // Attach pagination event handlers
        attachPaginationHandlers();

        // Handle Delete button click (dynamically created buttons)
        $(document).on('click', '.delete-product-btn', function() {
            var button = $(this);
            var productId = button.data('product-id');
            var apiId = button.data('api-id'); // Get _id from data attribute if available
            
            if (!productId) {
                vortemShowNotice(vortem_admin.strings.product_id_not_found || 'Product ID not found', 'error');
                return;
            }
            
            var deleteMessage = vortem_admin.strings.delete_confirmation || 'Are you sure you want to delete this product?';
            
            // Show modal instead of confirm
            showDeleteConfirmationModal(deleteMessage, function() {
                var deletingText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.deleting) ? vortem_admin.strings.deleting : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 4px; animation: spin 1s linear infinite;"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 15V3M12 15L8 11M12 15L16 11M2 17L2 19C2 20.1046 2.89543 21 4 21L20 21C21.1046 22 20.1046 22 19V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Deleting...';
                button.prop('disabled', true).html(deletingText);
                
                $.ajax({
                    url: vortem_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'vortem_delete_single_product',
                        product_id: productId,
                        api_id: apiId || '',
                        nonce: vortem_admin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            vortemShowNotice(vortem_admin.strings.product_deleted_successfully || 'Product deleted successfully', 'success');
                            
                            // Hide delete button and show import button
                            button.removeClass('deleting').hide();
                            var importBtn = button.siblings('.import-product-btn');
                            if (importBtn.length) {
                                // Reset button state - remove importing class and set correct text
                                var importText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.import) ? vortem_admin.strings.import : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 4px;"><path d="M12 15V3M12 15L8 11M12 15L16 11M2 17L2 19C2 20.1046 2.89543 21 4 21L20 21C21.1046 22 20.1046 22 19V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Import';
                                importBtn.removeClass('importing').prop('disabled', false).html(importText);
                                importBtn.show();
                            }
                            
                            // Remove preview button
                            var productCard = button.closest('.vortem-product-card');
                            if (productCard.length) {
                                var previewButton = productCard.find('.product-preview-button');
                                if (previewButton.length) {
                                    previewButton.remove();
                                }
                                
                                // Update status badge to "NEW"
                                var statusBadge = productCard.find('.product-status-badge');
                                var newText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.new) ? vortem_admin.strings.new : 'NEW';
                                if (statusBadge.length) {
                                    statusBadge.removeClass('status-added').addClass('status-new').text(newText).css({
                                        background: 'rgb(0, 115, 170)',
                                        color: 'white',
                                        display: 'block'
                                    });
                                }
                            }
                        } else {
                            var deleteText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.delete) ? vortem_admin.strings.delete : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 4px;"><path d="M3 6H5H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 6V4C8 3.46957 8.21071 2.96086 8.58579 2.58579 8.96086 2.21071 9.46957 2 10 2H14C14.5304 2 15.0391 2.21071 15.4142 2.58579 15.7893 2.96086 16 3.46957 16 4V6M19 6V20C19 20.5304 18.7893 21.0391 18.4142 21.4142(18.0391 21.7893 17.5304 22 17 22H7C6.46957 22 5.96086 21.7893 5.58579 21.4142(5.21071 21.0391 5 20.5304 5 20V6H19Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 11V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 11V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Delete';
                            button.prop('disabled', false).html(deleteText);
                            var errorMsg = typeof response.data === 'string' ? response.data : (response.data.message || JSON.stringify(response.data));
                            vortemShowNotice((vortem_admin.strings.delete_failed || 'Delete failed: ') + errorMsg, 'error');
                        }
                    },
                    error: function() {
                        var deleteText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.delete) ? vortem_admin.strings.delete : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 4px;"><path d="M3 6H5H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 6V4C8 3.46957 8.21071 2.96086 8.58579 2.58579 8.96086 2.21071 9.46957 2 10 2H14C14.5304 2 15.0391 2.21071 15.4142 2.58579 15.7893 2.96086 16 3.46957 16 4V6M19 6V20C19 20.5304 18.7893 21.0391 18.4142 21.4142(18.0391 21.7893 17.5304 22 17 22H7C6.46957 22 5.96086 21.7893 5.58579 21.4142(5.21071 21.0391 5 20.5304 5 20V6H19Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 11V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 11V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Delete';
                        button.prop('disabled', false).html(deleteText);
                        vortemShowNotice(vortem_admin.strings.delete_failed_try_again || 'Delete failed. Please try again.', 'error');
                    }
                });
            });
        });

        // View Product Details
        $('.view-details').on('click', function() {
            var sku = $(this).data('sku');
            $('#product-details-modal').show();
            $('#product-details-content').html('<p>' + (vortem_admin.strings.loading || 'Loading...') + '</p>');
            
            $.ajax({
                url: vortem_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'vortem_get_product_json',
                    sku: sku,
                    nonce: vortem_admin.nonce_details
                },
                success: function(response) {
                    if (response.success) {
                        $('#product-details-content').html(response.data.html);
                    } else {
                        $('#product-details-content').html('<p>' + (response.data || vortem_admin.strings.error_loading_details || 'Error loading details') + '</p>');
                    }
                },
                error: function() {
                    $('#product-details-content').html('<p>' + (vortem_admin.strings.error_loading_details || 'Error loading details') + '</p>');
                }
            });
        });

        // Close modal on backdrop click
        $(window).on('click', function(event) {
            if (event.target.id === 'product-details-modal') {
                $('#product-details-modal').hide();
            }
        });
    });

    // Attach pagination event handlers (delegated)
    function attachPaginationHandlers() {
        $(document).off('click', '.vortem-pagination-btn');
        $(document).on('click', '.vortem-pagination-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var button = $(this);
            if (button.prop('disabled')) {
                return;
            }
            
            var page = parseInt(button.data('page'));
            if (!page || page < 1) {
                return;
            }
            
            if (typeof VortemLogger !== 'undefined') {
                VortemLogger.log('Pagination: Loading page', page);
            }
            
            // Note: fetchProductsWithFilter would need to be defined elsewhere or passed as parameter
            // This is a placeholder - actual implementation depends on filter state
            if (typeof fetchProductsWithFilter === 'function') {
                fetchProductsWithFilter(page, filterState.showImportedOnly);
            }
        });
    }

})(jQuery);
