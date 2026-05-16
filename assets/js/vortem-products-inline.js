/**
 * Products Page Inline JavaScript
 *
 * This file contains the inline JavaScript that was previously in class-vortem-admin.php.
 * Moved to external JS file for WordPress best practices.
 *
 * External Libraries Used:
 * - jQuery (jQuery Foundation) - https://jquery.com/ | License: MIT | WordPress-bundled library used for DOM manipulation and AJAX
 * - Lucide Icons 1.7.0 (Lucide Contributors) - https://lucide.dev/ | License: ISC | Bundled locally in assets/vendor/lucide/ | Used for category menu arrow icons (lucide.createIcons())
 *
 * @package VortemAI
 * @since 1.0.5
 */

jQuery(document).ready(function($) {
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
        if (typeof vortem_admin !== 'undefined' && vortem_admin.nonce) {
        }
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
    
    function showLoading(message, submessage) {
        message = message || defaultProcessingText;
        submessage = submessage || defaultSubmessageText;
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

    // Import Products
    $('#import-products').on('click', function() {
        var button = $(this);
        var importingText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.importing) ? vortem_admin.strings.importing : 'Importing...';
        button.prop('disabled', true).text(importingText);
        
        $.ajax({
            url: vortem_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'vortem_import_products',
                nonce: vortem_admin.nonce_import
            },
            success: function(response) {
                if (response.success) {
                    var successText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.products_imported_successfully) ? vortem_admin.strings.products_imported_successfully : 'Products imported successfully!';
                    vortemShowNotice(successText, 'success');
                    location.reload();
                } else {
                    // Extract error message from response
                    var errorMsg = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.unknown_error_occurred) ? vortem_admin.strings.unknown_error_occurred : 'Unknown error occurred';
                    if (response.data) {
                        if (typeof response.data === 'string') {
                            errorMsg = response.data;
                        } else if (response.data.message) {
                            errorMsg = response.data.message;
                        } else {
                            errorMsg = JSON.stringify(response.data);
                        }
                    }
                    var importFailedText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.import_failed) ? vortem_admin.strings.import_failed : 'Import failed: ';
                    vortemShowNotice(importFailedText + errorMsg, 'error');
                }
            },
            error: function() {
                var importFailedText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.import_failed_please_try_again) ? vortem_admin.strings.import_failed_please_try_again : 'Import failed. Please try again.';
                vortemShowNotice(importFailedText, 'error');
            },
            complete: function() {
                var importToWooText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.import_to_woocommerce) ? vortem_admin.strings.import_to_woocommerce : 'Import to WooCommerce';
                button.prop('disabled', false).text(importToWooText);
            }
        });
    });

    // Import Single Product
    $('.import-single').on('click', function() {
        var sku = $(this).data('sku');
        var button = $(this);
        var importingText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.importing) ? vortem_admin.strings.importing : 'Importing...';
        button.prop('disabled', true).text(importingText);
        
        $.ajax({
            url: vortem_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'vortem_import_single',
                sku: sku,
                nonce: vortem_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update button to show "Already Imported" and disable it
                    var alreadyImportedText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.already_imported) ? vortem_admin.strings.already_imported : 'Already Imported';
                    button.text(alreadyImportedText)
                          .prop('disabled', true)
                          .css({
                              'background': '#28a745',
                              'color': 'white',
                              'cursor': 'not-allowed'
                          });
                    
                    // Show success message
                    var successText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.product_imported_successfully) ? vortem_admin.strings.product_imported_successfully : 'Product imported successfully!';
                    vortemShowNotice(successText, 'success');
                    
                    // No page reload - user can continue working
                } else {
                    // Extract error message from response
                    var errorMsg = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.unknown_error_occurred) ? vortem_admin.strings.unknown_error_occurred : 'Unknown error occurred';
                    if (response.data) {
                        if (typeof response.data === 'string') {
                            errorMsg = response.data;
                        } else if (response.data.message) {
                            errorMsg = response.data.message;
                        } else {
                            errorMsg = JSON.stringify(response.data);
                        }
                    }
                    var importFailedText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.import_failed) ? vortem_admin.strings.import_failed : 'Import failed: ';
                    vortemShowNotice(importFailedText + errorMsg, 'error');
                }
            },
            error: function() {
                var importFailedText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.import_failed_please_try_again) ? vortem_admin.strings.import_failed_please_try_again : 'Import failed. Please try again.';
                vortemShowNotice(importFailedText, 'error');
            },
            complete: function() {
                // Only reset button if import failed (button is not disabled)
                if (!button.prop('disabled')) {
                    var importText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.import) ? vortem_admin.strings.import : 'Import';
                    button.prop('disabled', false).text(importText);
                }
            }
        });
    });

    // Bulk Actions
    $('#doaction').on('click', function(e) {
        e.preventDefault();
        var action = $('#bulk-action-selector-top').val();
        var selectedProducts = $('.product-checkbox:checked').map(function() {
            return $(this).val();
        }).get();
        
        var pleaseSelectActionText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.please_select_a_bulk_action) ? vortem_admin.strings.please_select_a_bulk_action : 'Please select a bulk action.';
        if (action === '-1') {
            vortemShowNotice(pleaseSelectActionText, 'warning');
            return;
        }
        
        var pleaseSelectProductText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.please_select_at_least_one_product) ? vortem_admin.strings.please_select_at_least_one_product : 'Please select at least one product.';
        if (selectedProducts.length === 0) {
            vortemShowNotice(pleaseSelectProductText, 'warning');
            return;
        }
        
        var confirmMessage = '';
        var areYouSureImportText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.are_you_sure_import_selected) ? vortem_admin.strings.are_you_sure_import_selected : 'Are you sure you want to import the selected products to WooCommerce?';
        var areYouSureDeleteText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.are_you_sure_delete_selected) ? vortem_admin.strings.are_you_sure_delete_selected : 'Are you sure you want to permanently delete the selected products? This will remove them from the database and WordPress, including all images. This action cannot be undone.';
        var areYouSureRestoreText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.are_you_sure_restore_selected) ? vortem_admin.strings.are_you_sure_restore_selected : 'Are you sure you want to restore the selected products from trash?';
        var areYouSurePermanentlyDeleteText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.are_you_sure_permanently_delete_selected) ? vortem_admin.strings.are_you_sure_permanently_delete_selected : 'Are you sure you want to permanently delete the selected products? This action cannot be undone.';
        
        switch(action) {
            case 'import':
                confirmMessage = areYouSureImportText;
                break;
            case 'trash':
                confirmMessage = areYouSureDeleteText;
                break;
            case 'restore':
                confirmMessage = areYouSureRestoreText;
                break;
            case 'delete':
                confirmMessage = areYouSurePermanentlyDeleteText;
                break;
        }
        
        if (confirm(confirmMessage)) {
            var button = $(this);
            var processingText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.processing) ? vortem_admin.strings.processing : 'Processing...';
            button.prop('disabled', true).val(processingText);
            
            $.ajax({
                url: vortem_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'vortem_bulk_action',
                    bulk_action: action,
                    product_ids: selectedProducts,
                    nonce: vortem_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var bulkSuccessText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.bulk_action_completed_successfully) ? vortem_admin.strings.bulk_action_completed_successfully : 'Bulk action completed successfully!';
                        vortemShowNotice(response.data.message || bulkSuccessText, 'success');
                        location.reload();
                    } else {
                        var errorMsg = typeof response.data === 'string' ? response.data : (response.data.message || JSON.stringify(response.data));
                        var bulkFailedText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.bulk_action_failed) ? vortem_admin.strings.bulk_action_failed : 'Bulk action failed: ';
                        vortemShowNotice(bulkFailedText + errorMsg, 'error');
                    }
                },
                error: function() {
                    var bulkFailedText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.bulk_action_failed_please_try_again) ? vortem_admin.strings.bulk_action_failed_please_try_again : 'Bulk action failed. Please try again.';
                    vortemShowNotice(bulkFailedText, 'error');
                },
                complete: function() {
                    var applyText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.apply) ? vortem_admin.strings.apply : 'Apply';
                    button.prop('disabled', false).val(applyText);
                }
            });
        }
    });

    // Select All Checkbox
    $('#cb-select-all-1').on('change', function() {
        $('.product-checkbox').prop('checked', $(this).prop('checked'));
    });

    // Individual Checkbox Change
    $('.product-checkbox').on('change', function() {
        var totalCheckboxes = $('.product-checkbox').length;
        var checkedCheckboxes = $('.product-checkbox:checked').length;
        $('#cb-select-all-1').prop('checked', totalCheckboxes === checkedCheckboxes);
    });

    // Trash Product
    $('.trash-product').on('click', function() {
        var sku = $(this).data('sku');
        var button = $(this);
        var areYouSureText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.are_you_sure_permanently_delete_this) ? vortem_admin.strings.are_you_sure_permanently_delete_this : 'Are you sure you want to permanently delete this product? This will remove it from the database and WordPress, including all images. This action cannot be undone.';
        var deletingText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.deleting) ? vortem_admin.strings.deleting : 'Deleting...';
        var trashText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.trash) ? vortem_admin.strings.trash : 'Trash';
        var failedToTrashText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.failed_to_trash_product) ? vortem_admin.strings.failed_to_trash_product : 'Failed to trash product: ';
        var failedToTrashTryAgainText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.failed_to_trash_product_please_try_again) ? vortem_admin.strings.failed_to_trash_product_please_try_again : 'Failed to trash product. Please try again.';
        
        if (confirm(areYouSureText)) {
            button.prop('disabled', true).text(deletingText);
            
            $.ajax({
                url: vortem_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'vortem_trash_product',
                    sku: sku,
                    nonce: vortem_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        var errorMsg = typeof response.data === 'string' ? response.data : (response.data.message || JSON.stringify(response.data));
                        vortemShowNotice(failedToTrashText + errorMsg, 'error');
                        button.prop('disabled', false).text(trashText);
                    }
                },
                error: function() {
                    vortemShowNotice(failedToTrashTryAgainText, 'error');
                    button.prop('disabled', false).text(trashText);
                }
            });
        }
    });

    // Restore Product
    $('.restore-product').on('click', function() {
        var sku = $(this).data('sku');
        var button = $(this);
        var areYouSureText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.are_you_sure_restore_this) ? vortem_admin.strings.are_you_sure_restore_this : 'Are you sure you want to restore this product from trash?';
        var restoringText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.restoring) ? vortem_admin.strings.restoring : 'Restoring...';
        var restoreText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.restore) ? vortem_admin.strings.restore : 'Restore';
        var failedToRestoreText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.failed_to_restore_product) ? vortem_admin.strings.failed_to_restore_product : 'Failed to restore product: ';
        var failedToRestoreTryAgainText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.failed_to_restore_product_please_try_again) ? vortem_admin.strings.failed_to_restore_product_please_try_again : 'Failed to restore product. Please try again.';
        
        if (confirm(areYouSureText)) {
            button.prop('disabled', true).text(restoringText);
            
            $.ajax({
                url: vortem_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'vortem_restore_product',
                    sku: sku,
                    nonce: vortem_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        var errorMsg = typeof response.data === 'string' ? response.data : (response.data.message || JSON.stringify(response.data));
                        vortemShowNotice(failedToRestoreText + errorMsg, 'error');
                        button.prop('disabled', false).text(restoreText);
                    }
                },
                error: function() {
                    vortemShowNotice(failedToRestoreTryAgainText, 'error');
                    button.prop('disabled', false).text(restoreText);
                }
            });
        }
    });

    // Edit Product
    $('.edit-product').on('click', function() {
        var sku = $(this).data('sku');
        var button = $(this);
        
        var checkingStatusText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.checking_product_status) ? vortem_admin.strings.checking_product_status : 'Checking product status...';
        var pleaseWaitCheckText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.please_wait_check) ? vortem_admin.strings.please_wait_check : 'Please wait while we check if the product exists';
        showLoading(checkingStatusText, pleaseWaitCheckText);
        
        // Make AJAX call to get product details and find WooCommerce ID
        $.ajax({
            url: vortem_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'vortem_get_product_json',
                sku: sku,
                nonce: vortem_admin.nonce_details
            },
            success: function(response) {
                if (response.success && response.data.woo_product_exists) {
                    hideLoading();
                    // Redirect to WooCommerce product edit page
                    var editUrl = vortem_admin.site_url + '/wp-admin/post.php?post=' + response.data.woo_product_id + '&action=edit';
                    window.open(editUrl, '_blank');
                } else {
                    // Import as draft first, then redirect
                    var importingDraftText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.importing_product_draft) ? vortem_admin.strings.importing_product_draft : 'Importing product as draft...';
                    var pleaseWaitImportText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.please_wait_import) ? vortem_admin.strings.please_wait_import : 'Please wait while we import your product';
                    showLoading(importingDraftText, pleaseWaitImportText);
                    
                    $.ajax({
                        url: vortem_admin.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'vortem_import_as_draft',
                            sku: sku,
                            nonce: vortem_admin.nonce
                        },
                        success: function(importResponse) {
                            if (importResponse.success) {
                                hideLoading();
                                // Don't reload page, just show success message and update UI
                                showMessage('Product imported successfully!', 'success');
                            } else {
                                hideLoading();
                                var failedText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.failed_to_import_product_as_draft) ? vortem_admin.strings.failed_to_import_product_as_draft : 'Failed to import product as draft: ';
                                vortemShowNotice(failedText + importResponse.data, 'error');
                            }
                        },
                        error: function() {
                            hideLoading();
                            var failedText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.failed_to_import_product_as_draft_please_try_again) ? vortem_admin.strings.failed_to_import_product_as_draft_please_try_again : 'Failed to import product as draft. Please try again.';
                            vortemShowNotice(failedText, 'error');
                        }
                    });
                }
            },
            error: function() {
                hideLoading();
                var failedText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.failed_to_get_product_details) ? vortem_admin.strings.failed_to_get_product_details : 'Failed to get product details.';
                vortemShowNotice(failedText, 'error');
            }
        });
    });

    // View Product Details
    $('.view-details').on('click', function() {
        var sku = $(this).data('sku');
        $('#product-details-modal').show();
        var loadingText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.loading) ? vortem_admin.strings.loading : 'Loading...';
        $('#product-details-content').html('<p>' + loadingText + '</p>');
        
        $.ajax({
            url: vortem_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'vortem_get_product_details',
                sku: sku,
                nonce: vortem_admin.nonce_details
            },
            success: function(response) {
                if (response.success) {
                    $('#product-details-content').html(response.data);
                } else {
                    var failedText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.failed_to_load_product_details) ? vortem_admin.strings.failed_to_load_product_details : 'Failed to load product details.';
                    $('#product-details-content').html('<p>' + failedText + '</p>');
                }
            }
        });
    });

    // Close Modal
    $('.close').on('click', function() {
        $('#product-details-modal').hide();
    });

    // Store filter state globally (must be declared before any handlers that use it)
    var filterState = {
        showImportedOnly: false,
        currentPage: 1,
        selectedCategory: '',
        categoriesPage: 1,
        categoriesLimit: 50
    };
    
    // The category-dropdown logic (URL hydration, fetchCategories, click
    // handlers, and accordion behaviour) lives in the PHP-generated inline
    // block in admin/class-vortem-admin.php — it's appended to this same
    // script handle via wp_add_inline_script(). Defining it here as well used
    // to cause every category to render twice and the click handlers to
    // double-fire, which masked itself as the "categories won't open" bug.

    // Apply Price Filter Button - Temporarily disabled
    /*
    $('#apply-price-filter').on('click', function() {
        var minPrice = $('#min-price').val();
        var maxPrice = $('#max-price').val();
        
        // Validate price inputs
        var minPriceErrorText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.minimum_price_greater_than_maximum) ? vortem_admin.strings.minimum_price_greater_than_maximum : 'Minimum price cannot be greater than maximum price';
        if (minPrice && maxPrice && parseFloat(minPrice) > parseFloat(maxPrice)) {
            alert(minPriceErrorText);
            return;
        }
        
        // Reset to page 1 and fetch products with price filter
        if (typeof fetchProductsWithFilter === 'function') {
            fetchProductsWithFilter(1, filterState.showImportedOnly);
        }
    });
    
    // Allow Enter key to trigger Apply button
    $('#min-price, #max-price').on('keypress', function(e) {
        if (e.which === 13) { // Enter key
            $('#apply-price-filter').click();
        }
    });
    */
    
    // Category filter change handler (for backward compatibility)
    $('#category-filter').on('change', function() {
        var selectedCategory = $(this).val();
        filterState.selectedCategory = selectedCategory;
        
        // Show/hide price filter based on category selection
        // Price filter temporarily disabled
        /*
        if (selectedCategory) {
            $('#price-filter-container').css('display', 'flex');
        } else {
            $('#price-filter-container').css('display', 'none');
            $('#min-price').val('');
            $('#max-price').val('');
        }
        */
        
        // Reset to page 1 when category changes
        if (typeof fetchProductsWithFilter === 'function') {
            fetchProductsWithFilter(1, filterState.showImportedOnly);
        }
    });
    
    // Refresh Products Button
    $('#refresh-products-grid').on('click', function() {
        if (typeof VortemLogger !== 'undefined') {
            VortemLogger.log('Refresh products grid clicked');
        }
        var button = $(this);
        
        // Add loading class and disable button
        button.addClass('loading').prop('disabled', true);
        
        // Reset filter state when refreshing
        filterState.showImportedOnly = false;
        filterState.selectedCategory = '';
        
        // Reset category filter dropdown
        $('#category-filter').val('');
        
        // Refresh categories for all products view.
        // The canonical fetchCategories() now lives in the PHP-generated
        // inline block; its #refresh-products-grid handler runs alongside this
        // one and performs the actual refresh.
        
        // Fetch products with reset filter state and callback to remove loading
        if (typeof fetchProductsWithFilter === 'function') {
            fetchProductsWithFilter(1, false, function() {
                button.removeClass('loading').prop('disabled', false);
            });
        }
    });
    
    // Helper function to get current page number from pagination controls
    function getCurrentPageNumber() {
        var activePageBtn = $('.vortem-pagination-btn.button-primary');
        if (activePageBtn.length) {
            var page = parseInt(activePageBtn.data('page'));
            if (page && page > 0) {
                return page;
            }
        }
        return 1; // Default to page 1 if cannot determine
    }

    // Handle Delete button click (dynamically created buttons)
    $(document).on('click', '.delete-product-btn', function() {
        var button = $(this);
        var productId = button.data('product-id');
        var apiId = button.data('api-id'); // Get _id from data attribute if available
        
        var productIdNotFoundText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.product_id_not_found) ? vortem_admin.strings.product_id_not_found : 'Product ID not found';
        if (!productId) {
            vortemShowNotice(productIdNotFoundText, 'error');
            return;
        }
        
        var deleteMessage = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.delete_confirmation) ? vortem_admin.strings.delete_confirmation : 'Are you sure you want to delete this product?';
        
        // Show modal instead of confirm
        showDeleteConfirmationModal(deleteMessage, function() {
            if (typeof VortemLogger !== 'undefined') {
                VortemLogger.log('Delete button clicked for product:', productId);
            }
            if (apiId) {
                if (typeof VortemLogger !== 'undefined') {
                    VortemLogger.log('API ID (_id) found:', apiId);
                }
            }
            
            var deletingText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.deleting) ? vortem_admin.strings.deleting : 'Deleting...';
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
                        var successText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.product_deleted_successfully) ? vortem_admin.strings.product_deleted_successfully : 'Product deleted successfully';
                        vortemShowNotice(successText, 'success');
                        
                        // Hide delete button and show import button
                        button.removeClass('deleting').hide();
                        var importBtn = button.siblings('.import-product-btn');
                        if (importBtn.length) {
                            // Reset button state - remove importing class and set correct text
                            var importText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.import) ? vortem_admin.strings.import : 'Import';
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
                        var deleteText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.delete) ? vortem_admin.strings.delete : 'Delete';
                        button.prop('disabled', false).html(deleteText);
                        var errorMsg = typeof response.data === 'string' ? response.data : (response.data.message || JSON.stringify(response.data));
                        var deleteFailedText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.delete_failed) ? vortem_admin.strings.delete_failed : 'Delete failed: ';
                        vortemShowNotice(deleteFailedText + errorMsg, 'error');
                    }
                },
                error: function() {
                    var deleteText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.delete) ? vortem_admin.strings.delete : 'Delete';
                    button.prop('disabled', false).html(deleteText);
                    var deleteFailedText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.delete_failed_please_try_again) ? vortem_admin.strings.delete_failed_please_try_again : 'Delete failed. Please try again.';
                    vortemShowNotice(deleteFailedText, 'error');
                }
            });
        });
    });

    $(window).on('click', function(event) {
        if (event.target.id === 'product-details-modal') {
            $('#product-details-modal').hide();
        }
    });
});
