/**
 * Settings Page JavaScript
 *
 * Inline scripts moved to external JS file for WordPress best practices.
 * This file handles all settings page functionality.
 *
 * External Libraries Used:
 * - jQuery (jQuery Foundation) - https://jquery.com/ | License: MIT | WordPress-bundled library used for DOM manipulation, AJAX, and currency dropdown
 *
 * @package VortemAI
 * @since 1.0.5
 */

(function($) {
    'use strict';

    // Translation strings (will be localized via wp_add_inline_script)
    var strings = window.vortemSettingsStrings || {};

    // Validate products per page on form submission
    $('#vortem-settings-form').on('submit', function(e) {
        var productsPerPage = parseInt($('input[name="vortem_products_per_page"]').val());
        
        if (isNaN(productsPerPage) || productsPerPage < 1) {
            e.preventDefault();
            alert(strings.minError || 'Products per page must be at least 1. Please enter a valid value.');
            $('input[name="vortem_products_per_page"]').focus();
            return false;
        }
        
        if (productsPerPage > 100) {
            e.preventDefault();
            alert(strings.maxError || 'Products per page cannot exceed 100. Please enter a value between 1 and 100.');
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
            $this.attr('title', strings.minTitle || 'Products per page must be at least 1');
        } else if (productsPerPage > 100) {
            $this.addClass('error');
            $this.attr('title', strings.maxTitle || 'Products per page cannot exceed 100');
        } else {
            $this.addClass('valid');
            $this.removeAttr('title');
        }
    });

    // Tab switching functionality (consistent with analytics pattern)
    $('.tab').on('click', function() {
        var tab = $(this).data('tab');
        $('.tab').removeClass('active');
        $(this).addClass('active');
        $('.panel').removeClass('active');
        $('#panel-' + tab).addClass('active');
        
        // Update URL to use separate page parameter (like analytics)
        var url = new URL(window.location);
        var page = tab === 'orders' ? 'vortem-orders' : 'vortem-products';
        url.searchParams.set('page', page);
        url.searchParams.delete('tab'); // Remove tab parameter if it exists
        window.history.pushState({}, '', url);
    });

    // Track the original currency to detect changes
    var originalCurrency = null;
    var $updateButton = $('#vortem-update-currency');
    var $updateButtonWrapper = $('.vortem-currency-button-wrapper');
    var $currencySelect = $('#vortem_currency');
    var $dropdown = $('#vortem-currency-select-dropdown');

    // Function to normalize SVG content
    function normalizeSvg(svgContent) {
        if (!svgContent || typeof svgContent !== 'string') {
            return null;
        }
        
        // Decode HTML entities if needed
        var decoded = svgContent
            .replace(/</g, '<')
            .replace(/>/g, '>')
            .replace(/"/g, '"')
            .replace(/&/g, '&');
        
        // Parse SVG to ensure it has proper attributes
        try {
            var $svg = $('<div>').html(decoded).find('svg').first();
            
            if ($svg.length === 0) {
                return svgContent; // Return original if not valid SVG
            }
            
            // Ensure SVG has width and height
            if (!$svg.attr('width')) {
                $svg.attr('width', '20');
            }
            if (!$svg.attr('height')) {
                $svg.attr('height', '15');
            }
            
            // Ensure SVG has viewBox (important for proper scaling)
            if (!$svg.attr('viewBox')) {
                var width = parseInt($svg.attr('width')) || 20;
                var height = parseInt($svg.attr('height')) || 15;
                $svg.attr('viewBox', '0 0 ' + width + ' ' + height);
            }
            
            return $svg[0].outerHTML;
        } catch (e) {
            VortemLogger.warn('Failed to normalize SVG:', e);
            return svgContent; // Return original if processing fails
        }
    }

    
    // Function to convert Unicode flag to emoji
    function unicodeFlagToEmoji(unicode) {
        if (!unicode) {
            return '';
        }
        
        // Remove U+ prefix if present
        if (unicode.startsWith('U+')) {
            unicode = unicode.replace('U+', '');
        }
        
        // Convert hex to emoji
        try {
            var codePoints = unicode.split(' ').map(function(hex) {
                return parseInt(hex, 16);
            });
            return String.fromCodePoint.apply(String, codePoints);
        } catch (e) {
            return '';
        }
    }

    // Function to select a currency
    function selectCurrency(code, name, flag, flagType, svgContent) {
        // Update hidden input
        $('#vortem_currency').val(code);

        // Update display
        updateCurrencyDisplay(code, name, flag, flagType, svgContent);

        // Enable update button if currency changed
        toggleUpdateButton();
    }

    // Function to update currency display
    function updateCurrencyDisplay(code, name, flag, flagType, svgContent) {
        var $display = $('#vortem-currency-select-display');
        var $flagPlaceholder = $display.find('.vortem-currency-flag-placeholder');
        var $text = $display.find('.vortem-currency-text');

        // Update flag
        $flagPlaceholder.empty();
        if (flagType === 'svg' && svgContent) {
            $flagPlaceholder.html('<div class="vortem-currency-flag-container">' +
                '<div class="vortem-currency-flag vortem-currency-flag-svg">' + svgContent + '</div>' +
                '</div>');
        } else if (flag) {
            $flagPlaceholder.html('<span class="vortem-currency-flag">' + flag + '</span>');
        }

        // Update text
        $text.text(name ? name + ' (' + code + ')' : code);
    }

    // Function to close currency dropdown
    function closeCurrencyDropdown() {
        var $dropdown = $('#vortem-currency-select-dropdown');
        $dropdown.hide();
        $('#vortem-currency-select-display').removeClass('active');
    }

    
    // Function to toggle update button state
    function toggleUpdateButton() {
        var currentCurrency = $currencySelect.val();
        if (currentCurrency !== originalCurrency) {
            $updateButton.show();
            $updateButtonWrapper.addClass('has-update');
        } else {
            $updateButton.hide();
            $updateButtonWrapper.removeClass('has-update');
        }
    }

    // Load currencies from API
    function loadCurrenciesFromAPI() {
        var currentCurrency = null;
        
        if (typeof vortem_admin === 'undefined') {
            return;
        }

        $.ajax({
            url: vortem_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'vortem_get_currency_codes',
                nonce: vortem_admin.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    var currencies = response.data;
                    var $dropdownList = $dropdown.find('.vortem-currency-dropdown-list');
                    
                    // Clear existing content except loading
                    $dropdown.find('.vortem-currency-loading').hide();
                    
                    if (!$dropdownList.length) {
                        $dropdown.append('<div class="vortem-currency-dropdown-list"></div>');
                        $dropdownList = $dropdown.find('.vortem-currency-dropdown-list');
                    }
                    
                    $dropdownList.empty();

                    // Normalize currencies to array format
                    var currencyArray = [];

                    if (Array.isArray(currencies)) {
                        currencies.forEach(function(currency) {
                            var code = currency.currency_code || currency.code || currency.id;
                            var countryCurrency = currency.country_currency || currency.name || currency.currency_name || '';
                            var svgContent = currency.svg_content || '';
                            var countryFlag = currency.country_flag || '';

                            // Normalize SVG content and use it if available, otherwise use emoji
                            var flag = '';
                            var flagType = 'emoji';
                            var normalizedSvg = null;
                            
                            if (svgContent && typeof svgContent === 'string') {
                                // Normalize SVG to ensure proper dimensions
                                normalizedSvg = normalizeSvg(svgContent);
                                if (normalizedSvg && normalizedSvg.trim().match(/<svg[\s>]/i)) {
                                    // Use SVG for display
                                    flag = normalizedSvg;
                                    flagType = 'svg';
                                } else if (countryFlag) {
                                    // Fallback to emoji if SVG is invalid
                                    flag = unicodeFlagToEmoji(countryFlag);
                                    flagType = 'emoji';
                                }
                            } else if (countryFlag) {
                                flag = unicodeFlagToEmoji(countryFlag);
                                flagType = 'emoji';
                            }

                            if (code) {
                                currencyArray.push({
                                    code: code,
                                    name: countryCurrency || code,
                                    flag: flag,
                                    flagType: flagType,
                                    svgContent: normalizedSvg || svgContent
                                });
                            }
                        });
                    } else if (typeof currencies === 'object') {
                        $.each(currencies, function(key, data) {
                            var code = data.currency_code || key;
                            var countryCurrency = data.country_currency || data.name || data.currency_name || '';
                            var svgContent = data.svg_content || '';
                            var countryFlag = data.country_flag || '';

                            // Normalize SVG content and use it if available, otherwise use emoji
                            var flag = '';
                            var flagType = 'emoji';
                            var normalizedSvg = null;
                            
                            if (svgContent && typeof svgContent === 'string') {
                                // Normalize SVG to ensure proper dimensions
                                normalizedSvg = normalizeSvg(svgContent);
                                if (normalizedSvg && normalizedSvg.trim().match(/<svg[\s>]/i)) {
                                    // Use SVG for display
                                    flag = normalizedSvg;
                                    flagType = 'svg';
                                } else if (countryFlag) {
                                    // Fallback to emoji if SVG is invalid
                                    flag = unicodeFlagToEmoji(countryFlag);
                                    flagType = 'emoji';
                                }
                            } else if (countryFlag) {
                                flag = unicodeFlagToEmoji(countryFlag);
                                flagType = 'emoji';
                            }

                            if (code) {
                                currencyArray.push({
                                    code: code,
                                    name: countryCurrency || code,
                                    flag: flag,
                                    flagType: flagType,
                                    svgContent: normalizedSvg || svgContent
                                });
                            }
                        });
                    }

                    // Filter currencies to only include those starting with English letters (A-Z)
                    var filteredCurrencies = currencyArray.filter(function(currency) {
                        var firstChar = currency.name.charAt(0).toUpperCase();
                        return firstChar >= 'A' && firstChar <= 'Z';
                    });

                    // Sort currencies alphabetically by name
                    filteredCurrencies.sort(function(a, b) {
                        return a.name.localeCompare(b.name);
                    });

                    // Group currencies by first letter
                    var groupedCurrencies = {};
                    filteredCurrencies.forEach(function(currency) {
                        var firstLetter = currency.name.charAt(0).toUpperCase();
                        if (!groupedCurrencies[firstLetter]) {
                            groupedCurrencies[firstLetter] = [];
                        }
                        groupedCurrencies[firstLetter].push(currency);
                    });

                    // Create groups for each letter A-Z
                    for (var letter = 'A'; letter <= 'Z'; letter = String.fromCharCode(letter.charCodeAt(0) + 1)) {
                        if (groupedCurrencies[letter] && groupedCurrencies[letter].length > 0) {
                            var $group = $('<div></div>').addClass('vortem-currency-group').attr('data-letter', letter);
                            var $groupHeader = $('<div></div>').addClass('vortem-currency-group-header').text(letter);
                            $group.append($groupHeader);

                            groupedCurrencies[letter].forEach(function(currency) {
                                var $option = $('<div></div>')
                                    .addClass('vortem-currency-option')
                                    .attr('data-currency', currency.code)
                                    .attr('data-name', currency.name);

                                // Handle flag display
                                var flagHtml = '';
                                if (currency.flagType === 'svg' && currency.flag) {
                                    // Use normalized SVG from flag (already normalized)
                                    flagHtml = '<div class="vortem-currency-flag-container">' +
                                        '<div class="vortem-currency-flag vortem-currency-flag-svg">' + currency.flag + '</div>' +
                                        '</div>';
                                } else if (currency.flag) {
                                    flagHtml = '<span class="vortem-currency-flag">' + currency.flag + '</span>';
                                }

                                var currencyName = currency.name ? currency.name + ' (' + currency.code + ')' : currency.code;

                                $option.html(
                                    flagHtml +
                                    '<span class="vortem-currency-name">' + currencyName + '</span>'
                                );

                                $option.on('click', function() {
                                    selectCurrency(currency.code, currency.name, currency.flag, currency.flagType, currency.svgContent);
                                    closeCurrencyDropdown();
                                });

                                $group.append($option);
                            });

                            $dropdownList.append($group);
                        }
                    }

                    // Add search functionality
                    var $searchInput = $dropdownList.find('.vortem-currency-search-input');
                    $searchInput.on('input', function() {
                        var searchTerm = $(this).val().toLowerCase().trim();
                        
                        if (searchTerm === '') {
                            // Show all groups
                            $dropdownList.find('.vortem-currency-group').show();
                            $dropdownList.find('.vortem-currency-option').show();
                        } else {
                            // Filter currencies
                            var hasVisibleItems = false;
                            
                            $dropdownList.find('.vortem-currency-group').each(function() {
                                var $group = $(this);
                                var $options = $group.find('.vortem-currency-option');
                                var hasMatch = false;
                                
                                $options.each(function() {
                                    var $option = $(this);
                                    var currencyCode = $option.data('currency').toLowerCase();
                                    var currencyName = $option.data('name').toLowerCase();
                                    
                                    if (currencyName.indexOf(searchTerm) !== -1 || currencyCode.indexOf(searchTerm) !== -1) {
                                        $option.show();
                                        hasMatch = true;
                                        hasVisibleItems = true;
                                    } else {
                                        $option.hide();
                                    }
                                });
                                
                                if (hasMatch) {
                                    $group.show();
                                } else {
                                    $group.hide();
                                }
                            });
                            
                            // Show "no results" message if needed
                            var $noResults = $dropdownList.find('.vortem-currency-no-results');
                            if (!hasVisibleItems) {
                                if ($noResults.length === 0) {
                                    $dropdownList.append('<div class="vortem-currency-no-results">' + 
                                        (strings.noCurrenciesFound || 'No currencies found') + 
                                        '</div>');
                                } else {
                                    $noResults.show();
                                }
                            } else {
                                $noResults.hide();
                            }
                        }
                    });
                    
                    // Prevent search input from closing dropdown
                    $searchInput.on('click', function(e) {
                        e.stopPropagation();
                    });

                    // Set initial display
                    if (currentCurrency) {
                        // Find the current currency and update display
                        var currentCurrencyData = filteredCurrencies.find(function(currency) {
                            return currency.code === currentCurrency;
                        });
                        if (currentCurrencyData) {
                            updateCurrencyDisplay(currentCurrencyData.code, currentCurrencyData.name, currentCurrencyData.flag, currentCurrencyData.flagType, currentCurrencyData.svgContent);
                        }
                    }

                    // Ensure dropdown is hidden after loading (should only open on user click)
                    $dropdown.hide();
                    $('#vortem-currency-select-display').removeClass('active');

                    // Update button state
                    toggleUpdateButton();
                } else {
                    $dropdown.find('.vortem-currency-loading').text(strings.failedToLoadCurrencies || 'Failed to load currencies');
                    if (typeof VortemLogger !== 'undefined') {
                        VortemLogger.warn('Failed to load currencies from API');
                    }
                }
            },
            error: function(xhr, status, error) {
                $dropdown.find('.vortem-currency-loading').text(strings.errorLoadingCurrencies || 'Error loading currencies');
                if (typeof VortemLogger !== 'undefined') {
                    VortemLogger.error('Error loading currencies from API:', error);
                }
            }
        });
    }

    // Initialize on document ready
    $(document).ready(function() {
        // Store original currency for change detection
        originalCurrency = $currencySelect.val();
        
        // Load currencies from API
        loadCurrenciesFromAPI();
        
        // Toggle dropdown on display click
        $('#vortem-currency-select-display').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var $dropdown = $('#vortem-currency-select-dropdown');
            $dropdown.toggle();
            $(this).toggleClass('active');
        });
        
        // Close dropdown when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#vortem-currency-select-display, #vortem-currency-select-dropdown').length) {
                closeCurrencyDropdown();
            }
        });
        
        // AliExpress Integration
        var $aliexpressStatus = $('#vortem-aliexpress-status-text');
        var $connectBtn = $('#vortem-connect-aliexpress');
        var $disconnectBtn = $('#vortem-disconnect-aliexpress');
        var $modal = $('#vortem-aliexpress-modal');
        var $modalOverlay = $modal.find('.vortem-aliexpress-modal-overlay');
        var $modalClose = $('#vortem-aliexpress-modal-close');
        var $modalCancel = $('#vortem-aliexpress-modal-cancel');
        var $modalOk = $('#vortem-aliexpress-modal-ok');
        var $modalMessage = $('#vortem-aliexpress-modal-message');
        var $modalTitle = $('#vortem-aliexpress-modal-title');
        var $countdown = $('#vortem-aliexpress-modal-countdown');
        
        // Check AliExpress connection status
        function checkAliExpressStatus() {
            if (typeof vortem_admin === 'undefined') {
                return;
            }
            
            $.ajax({
                url: vortem_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'vortem_check_aliexpress_status',
                    nonce: vortem_admin.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        if (response.data.connected) {
                            $aliexpressStatus.text(strings.aliexpressConnected || 'Connected');
                            $connectBtn.hide();
                            $disconnectBtn.show();
                        } else {
                            $aliexpressStatus.text(strings.aliexpressNotConnected || 'Not connected');
                            $connectBtn.show();
                            $disconnectBtn.hide();
                        }
                    } else {
                        $aliexpressStatus.text(strings.aliexpressNotConnected || 'Not connected');
                        $connectBtn.show();
                        $disconnectBtn.hide();
                    }
                },
                error: function() {
                    $aliexpressStatus.text(strings.aliexpressCheckFailed || 'Failed to check status');
                }
            });
        }
        
        // Check status on load
        checkAliExpressStatus();
        
        // Connect button
        $connectBtn.on('click', function() {
            if (typeof vortem_admin === 'undefined') {
                return;
            }
            
            $.ajax({
                url: vortem_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'vortem_connect_aliexpress',
                    nonce: vortem_admin.nonce
                },
                success: function(response) {
                    if (response.success && response.data && response.data.auth_url) {
                        // Show modal with countdown
                        $modalTitle.text(strings.aliexpressConnect || 'Connect to AliExpress');
                        $modalMessage.text(strings.aliexpressRedirectMessage || 'You will be redirected to AliExpress to authorize the connection.');
                        $modalCancel.hide();
                        $modalOk.show();
                        $modal.show();
                        
                        // Start countdown
                        var seconds = 5;
                        $countdown.show().text('(' + seconds + ')');
                        
                        var countdownInterval = setInterval(function() {
                            seconds--;
                            $countdown.text('(' + seconds + ')');
                            
                            if (seconds <= 0) {
                                clearInterval(countdownInterval);
                                $countdown.hide();
                                window.location.href = response.data.auth_url;
                            }
                        }, 1000);
                    } else {
                        $modalTitle.text(strings.aliexpressError || 'Error');
                        $modalMessage.text(response.data && response.data.message ? response.data.message : (strings.aliexpressConnectFailed || 'Failed to connect to AliExpress.'));
                        $modalCancel.hide();
                        $modalOk.show();
                        $modal.show();
                    }
                },
                error: function() {
                    $modalTitle.text(strings.aliexpressError || 'Error');
                    $modalMessage.text(strings.aliexpressConnectFailed || 'Failed to connect to AliExpress.');
                    $modalCancel.hide();
                    $modalOk.show();
                    $modal.show();
                }
            });
        });
        
        // Disconnect button
        $disconnectBtn.on('click', function() {
            if (typeof vortem_admin === 'undefined') {
                return;
            }
            
            $.ajax({
                url: vortem_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'vortem_disconnect_aliexpress',
                    nonce: vortem_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $modalTitle.text(strings.aliexpressDisconnect || 'Disconnect');
                        $modalMessage.text(strings.aliexpressDisconnectSuccess || 'Successfully disconnected from AliExpress.');
                        $modalCancel.hide();
                        $modalOk.show();
                        $modal.show();
                        checkAliExpressStatus();
                    } else {
                        $modalTitle.text(strings.aliexpressError || 'Error');
                        $modalMessage.text(response.data && response.data.message ? response.data.message : (strings.aliexpressDisconnectFailed || 'Failed to disconnect from AliExpress.'));
                        $modalCancel.hide();
                        $modalOk.show();
                        $modal.show();
                    }
                },
                error: function() {
                    $modalTitle.text(strings.aliexpressError || 'Error');
                    $modalMessage.text(strings.aliexpressDisconnectFailed || 'Failed to disconnect from AliExpress.');
                    $modalCancel.hide();
                    $modalOk.show();
                    $modal.show();
                }
            });
        });
        
        // Modal close handlers
        $modalClose.on('click', function() {
            $modal.hide();
        });
        
        $modalOverlay.on('click', function() {
            $modal.hide();
        });
        
        $modalOk.on('click', function() {
            $modal.hide();
        });
        
        $modalCancel.on('click', function() {
            $modal.hide();
        });
    });

})(jQuery);
