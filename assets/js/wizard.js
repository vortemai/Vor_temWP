/**
 * Vortem Setup Wizard JavaScript
 *
 * External Libraries Used:
 * - jQuery (jQuery Foundation) - https://jquery.com/ | License: MIT | WordPress-bundled library used for DOM manipulation, AJAX, and event handling
 */
(function($) {
    'use strict';

    var VortemWizard = {
        // Track authentication status
        isAuthenticated: false,
        
        /**
         * Format loading text for RTL languages
         * Moves dots (...) from end to beginning in RTL
         */
        formatLoadingText: function(text) {
            if (!text) return text;
            
            // Check if page is RTL
            var isRTL = $('html').attr('dir') === 'rtl' || $('body').hasClass('rtl') || $('.vortem-wizard-card').hasClass('vortem-wizard-rtl');
            
            if (!isRTL) {
                return text;
            }
            
            // Extract dots from end (one or more dots)
            var dotsMatch = text.match(/\.+$/);
            if (dotsMatch) {
                var dots = dotsMatch[0];
                var textWithoutDots = text.replace(/\.+$/, '').trim();
                return dots + ' ' + textWithoutDots;
            }
            
            return text;
        },
        
        init: function() {
            VortemLogger.log('VortemWizard init called');
            VortemLogger.log('vortemWizard object:', vortemWizard);
            this.bindEvents();
            this.initStep();
        },

        bindEvents: function() {
            VortemLogger.log('Binding events...'); // Debug log
            // Navigation buttons
            $(document).on('click', '.wizard-next', this.nextStep);
            $(document).on('click', '.wizard-prev', this.prevStep);
            $(document).on('click', '.wizard-complete', this.completeWizard);
            // Complete and go to dashboard button (step 4)
            $(document).on('click', '#complete-and-go-to-dashboard', this.completeAndGoToDashboard);
            
            // Update modal position on window resize and scroll
            var self = this;
            $(window).on('resize scroll', function() {
                var $modal = $('#restart-wizard-modal');
                if ($modal.is(':visible')) {
                    self.positionModalRelativeToCard($modal);
                }
            });

            // Terms acceptance - listen to both change and click events
            $(document).on('change', '#accept-terms', this.toggleTermsAcceptance);
            $(document).on('change', '#accept-data-processing', this.toggleTermsAcceptance);
            $(document).on('click', '.terms-checkbox-link', function(e) {
                e.stopPropagation(); // Don't toggle checkbox when clicking the Terms link
            });
            $(document).on('click', '.terms-checkbox-label', function(e) {
                // If clicking on the label, the checkbox will toggle automatically
                // Use setTimeout to ensure the checkbox state has updated
                setTimeout(function() {
                    VortemWizard.toggleTermsAcceptance();
                }, 0);
            });
            
            // Restart wizard
            var self = this;
            $(document).on('click', '#restart-wizard', function(e) {
                e.preventDefault();
                e.stopPropagation();
                VortemLogger.log('Restart wizard button clicked');
                self.restartWizard.call(self, e);
            });
            $(document).on('click', '#restart-wizard-confirm', this.confirmRestartWizard);
            $(document).on('click', '#restart-wizard-cancel', this.cancelRestartWizard);
            $(document).on('click', '.vortem-wizard-modal-overlay', this.cancelRestartWizard);

            // Currency dropdown events
            $(document).on('click', '#wizard-currency-select-display', this.toggleCurrencyDropdown);

            // Close dropdowns when clicking outside
            $(document).on('click', function(e) {
                var $currencySelect = $('#wizard-custom-currency-select');

                if (!$currencySelect.is(e.target) && $currencySelect.has(e.target).length === 0) {
                    VortemWizard.closeCurrencyDropdown();
                }
            });

            VortemLogger.log('Events bound'); // Debug log
        },

        toggleCurrencyDropdown: function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $dropdown = $('#wizard-currency-select-dropdown');
            var $display = $('#wizard-currency-select-display');

            if ($dropdown.hasClass('show')) {
                VortemWizard.closeCurrencyDropdown();
            } else {
                $dropdown.addClass('show');
                $display.addClass('active');
            }
        },

        initStep: function() {
            var currentStep = this.getCurrentStep();
            
            // Initialize step-specific functionality
            switch(currentStep) {
                case 1: // Welcome step
                    this.initWelcomeStep();
                    break;
                case 2: // Configuration step
                    this.initConfigurationStep();
                    break;
                case 3: // Terms step
                    this.initTermsStep();
                    break;
                case 4: // Complete step
                    this.initCompleteStep();
                    break;
            }
        },

        initWelcomeStep: function() {
            // Welcome step - no special initialization needed
        },

        initConfigurationStep: function() {
            this.showCurrencyBoxLoading();
            this.loadCurrencies();
            // Auto-authenticate when configuration step loads (needed for Terms step)
            if (typeof vortemWizard !== 'undefined' && vortemWizard.hasSessionToken) {
                VortemLogger.log('VortemWizard: Session token already exists');
                this.isAuthenticated = true;
            } else {
                this.authenticate();
            }
        },

        showCurrencyBoxLoading: function() {
            var $display = $('#wizard-currency-select-display');
            if (!$display.length) { return; }
            var raw = (vortemWizard.strings && vortemWizard.strings.loading) ? vortemWizard.strings.loading : 'Loading';
            var loadingText = raw.replace(/\.+$/, '');
            var dotsHtml = '<span class="vortem-currency-loading-dots"><span>.</span><span>.</span><span>.</span></span>';
            $display.addClass('loading initialized');
            $display.find('.vortem-currency-text').html(
                '<div class="currency-line-1">' + loadingText + dotsHtml + '</div><div class="currency-line-2"></div>'
            );
        },

        preInitCurrencyDisplay: function() {
            VortemLogger.log('Pre-initializing currency display...');
            
            var $currencyDisplay = $('#wizard-currency-select-display');
            if ($currencyDisplay.length) {
                // Show loading state as initialized to prevent flash
                var $text = $currencyDisplay.find('.vortem-currency-text');
                if (!$text.find('.currency-line-1').length) {
                    $text.html('<div class="currency-line-1"></div><div class="currency-line-2"></div>');
                }
                $text.find('.currency-line-1').text('Select Currency');
                $text.find('.currency-line-2').text('');
                $currencyDisplay.addClass('initialized');
            }
        },

        loadCurrencies: function() {
            VortemLogger.log('Loading currencies from API...');
            
            var $currencySelect = $('#wizard-currency-select');
            var $dropdown = $('#wizard-currency-select-dropdown');
            var $display = $('#wizard-currency-select-display');
            var currentCurrency = $currencySelect.data('current') || $currencySelect.val() || 'USD';
            
            // Show loading state in dropdown and in display box (with animated dots)
            $dropdown.find('.vortem-currency-loading').show();
            $display.addClass('loading initialized');
            var rawLabel = (vortemWizard.strings && vortemWizard.strings.loading) ? vortemWizard.strings.loading : 'Loading';
            var loadingLabel = rawLabel.replace(/\.+$/, '');
            var dotsHtml = '<span class="vortem-currency-loading-dots"><span>.</span><span>.</span><span>.</span></span>';
            var $text = $display.find('.vortem-currency-text');
            $text.html('<div class="currency-line-1">' + loadingLabel + dotsHtml + '</div><div class="currency-line-2"></div>');
            
            $.ajax({
                url: vortemWizard.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vortem_wizard_get_currencies',
                    nonce: vortemWizard.nonce
                },
                success: function(response) {
                    VortemLogger.log('Currencies loaded:', response);
                    
                    if (response.success && response.data) {
                        var currencies = response.data;
                        var $dropdownList = $dropdown.find('.vortem-currency-dropdown-list');

                        // Clear existing content except loading
                        $dropdown.find('.vortem-currency-loading').css('display', 'none');
                        if (!$dropdownList.length) {
                            $dropdown.append('<div class="vortem-currency-dropdown-list"></div>');
                            $dropdownList = $dropdown.find('.vortem-currency-dropdown-list');
                        }
                        $dropdownList.empty();

                        // Add search box (prepend to show at top)
                        var $searchBox = $('<div class="vortem-currency-search-wrapper">' +
                            '<span class="dashicons dashicons-search"></span>' +
                            '<input type="text" class="vortem-currency-search-input" placeholder="' + 
                            (vortemWizard.strings.search_currency || 'Search...') + 
                            '">' +
                            '</div>');
                        $dropdownList.prepend($searchBox);

                        // Function to convert Unicode flag format to emoji
                        function unicodeFlagToEmoji(unicodeFlag) {
                            if (!unicodeFlag || typeof unicodeFlag !== 'string') {
                                return '';
                            }
                            // Parse format like "U+1F1E7 U+1F1F2" to emoji
                            var codePoints = unicodeFlag.match(/U\+([0-9A-Fa-f]+)/g);
                            if (!codePoints || codePoints.length < 2) {
                                return '';
                            }
                            try {
                                var firstCode = parseInt(codePoints[0].substring(2), 16);
                                var secondCode = parseInt(codePoints[1].substring(2), 16);
                                return String.fromCodePoint(firstCode, secondCode);
                            } catch (e) {
                                return '';
                            }
                        }

                        // Function to normalize SVG content - ensure it has proper dimensions
                        function normalizeSvg(svgContent) {
                            if (!svgContent || typeof svgContent !== 'string') {
                                return svgContent;
                            }
                            
                            // Decode HTML entities if needed
                            var decoded = svgContent;
                            if (svgContent.indexOf('&lt;') !== -1 || svgContent.indexOf('&gt;') !== -1) {
                                var textarea = document.createElement('textarea');
                                textarea.innerHTML = svgContent;
                                decoded = textarea.value;
                            }
                            
                            // Check if it's a valid SVG
                            if (!decoded.trim().match(/<svg[\s>]/i)) {
                                return svgContent; // Return original if not valid SVG
                            }
                            
                            // Parse SVG to ensure it has proper attributes
                            try {
                                var $temp = $('<div>').html(decoded);
                                var $svg = $temp.find('svg').first();
                                
                                if ($svg.length === 0) {
                                    // Try to find SVG as root element
                                    $temp = $('<div>').html(decoded);
                                    $svg = $temp.children('svg').first();
                                }
                                
                                if ($svg.length > 0) {
                                    // Ensure SVG has width and height
                                    if (!$svg.attr('width')) {
                                        $svg.attr('width', '20');
                                    }
                                    if (!$svg.attr('height')) {
                                        $svg.attr('height', '15');
                                    }
                                    
                                    // Ensure SVG has viewBox (important for proper scaling)
                                    if (!$svg.attr('viewBox')) {
                                        var width = $svg.attr('width') || '20';
                                        var height = $svg.attr('height') || '15';
                                        // Remove 'px' if present
                                        width = width.replace('px', '').trim();
                                        height = height.replace('px', '').trim();
                                        $svg.attr('viewBox', '0 0 ' + width + ' ' + height);
                                    }
                                    
                                    // Ensure preserveAspectRatio for consistent display
                                    if (!$svg.attr('preserveAspectRatio')) {
                                        $svg.attr('preserveAspectRatio', 'xMidYMid meet');
                                    }
                                    
                                    return $svg[0].outerHTML;
                                }
                            } catch (e) {
                                VortemLogger.warn('Failed to normalize SVG:', e);
                            }
                            
                            return svgContent; // Return original if processing fails
                        }

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

                                    var currencyName = currency.name
                                        ? currency.name + ' (<span class="vortem-currency-code">' + currency.code + '</span>)'
                                        : '<span class="vortem-currency-code">' + currency.code + '</span>';
                                    var isSelected = currency.code === currentCurrency;

                                    $option.html(
                                        flagHtml +
                                        '<span class="vortem-currency-name">' + currencyName + '</span>' +
                                        (isSelected ? '<span class="dashicons dashicons-yes"></span>' : '')
                                    );
                                    
                                    if (isSelected) {
                                        $option.addClass('selected');
                                    }

                                    $option.on('click', function() {
                                        // Remove selected class from all options
                                        $dropdownList.find('.vortem-currency-option').removeClass('selected').find('.dashicons-yes').remove();
                                        
                                        // Add selected class to clicked option
                                        $(this).addClass('selected').append('<span class="dashicons dashicons-yes"></span>');
                                        
                                        VortemWizard.selectCurrency(currency.code, currency.name, currency.flag, currency.flagType, currency.svgContent);
                                        VortemWizard.closeCurrencyDropdown();
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
                                            (vortemWizard.strings.no_results || 'No currencies found') + 
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

                        // Set current currency if available
                        var selectedCurrency = null;
                        
                        if (currentCurrency) {
                            // Try to find saved currency in the list
                            selectedCurrency = currencyArray.find(function(currency) {
                                return currency.code === currentCurrency;
                            });
                        }
                        
                        // If no saved currency found or no saved currency exists, default to USD
                        if (!selectedCurrency) {
                            selectedCurrency = currencyArray.find(function(currency) {
                                return currency.code === 'USD';
                            });
                        }
                        
                        // If USD not found, use first currency in the list
                        if (!selectedCurrency && currencyArray.length > 0) {
                            selectedCurrency = currencyArray[0];
                        }
                        
                        // Update display with selected currency
                        $display.removeClass('loading');
                        if (selectedCurrency) {
                            // Always update the hidden input value to ensure it's set correctly
                            $('#wizard-currency-select').val(selectedCurrency.code);
                            
                            // Update display
                            VortemWizard.updateCurrencyDisplay(
                                selectedCurrency.code,
                                selectedCurrency.name,
                                selectedCurrency.flag,
                                selectedCurrency.flagType,
                                selectedCurrency.svgContent
                            );
                            
                            // Only call switchCurrencyValue if currency is different from saved one
                            // This saves the currency to database via AJAX
                            var shouldSwitch = !currentCurrency || currentCurrency !== selectedCurrency.code;
                            if (shouldSwitch) {
                                VortemWizard.switchCurrencyValue(selectedCurrency.code);
                            }
                        } else {
                            // Fallback: show Select Currency
                            var $text = $display.find('.vortem-currency-text');
                            $text.html('<div class="currency-line-1"></div><div class="currency-line-2"></div>');
                            $text.find('.currency-line-1').text(vortemWizard.strings.selectCurrency || 'Select Currency');
                            $text.find('.currency-line-2').text('');
                            $display.removeClass('loading').addClass('initialized');
                        }
                    } else {
                        VortemLogger.error('Failed to load currencies:', response);
                        $dropdown.find('.vortem-currency-loading').text('Failed to load currencies');
                        var $text = $display.find('.vortem-currency-text');
                        $text.html('<div class="currency-line-1"></div><div class="currency-line-2"></div>');
                        $text.find('.currency-line-1').text('Failed to load currencies');
                        $text.find('.currency-line-2').text('');
                        $display.removeClass('loading').addClass('initialized');
                    }
                },
                error: function(xhr, status, error) {
                    VortemLogger.error('Error loading currencies:', xhr, status, error);
                    $dropdown.find('.vortem-currency-loading').text('Error loading currencies');
                    var $text = $display.find('.vortem-currency-text');
                    $text.html('<div class="currency-line-1"></div><div class="currency-line-2"></div>');
                    $text.find('.currency-line-1').text('Error loading currencies');
                    $text.find('.currency-line-2').text('');
                    $display.removeClass('loading').addClass('initialized');
                }
            });
        },

        selectCurrency: function(code, name, flag, flagType, svgContent) {
            // Update hidden input
            $('#wizard-currency-select').val(code);

            // Update display
            this.updateCurrencyDisplay(code, name, flag, flagType, svgContent);

            // Trigger currency switch
            this.switchCurrencyValue(code);
        },

        updateCurrencyDisplay: function(code, name, flag, flagType, svgContent) {
            var $display = $('#wizard-currency-select-display');
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

            if (!$text.find('.currency-line-1').length) {
                $text.html('<div class="currency-line-1"></div><div class="currency-line-2"></div>');
            }
            
            var $line1 = $text.find('.currency-line-1');
            var $line2 = $text.find('.currency-line-2');
            
            if (code) {
                // Two-line pill: bold name on line 1, code on line 2.
                $line1.text(name && name.length ? name : code);
                $line2.text(name && name.length && name !== code ? code : '');
            } else {
                $line1.text(vortemWizard.strings.selectCurrency || 'Select Currency');
                $line2.text('');
            }
            
            // Mark as initialized to show with fade-in effect
            $display.addClass('initialized');
        },

        closeCurrencyDropdown: function() {
            var $dropdown = $('#wizard-currency-select-dropdown');
            var $display = $('#wizard-currency-select-display');
            $dropdown.removeClass('show');
            $display.removeClass('active');
        },

        initCompleteStep: function() {
            // Complete step initialization
            // Check if session token exists
            if (typeof vortemWizard !== 'undefined' && vortemWizard.hasSessionToken) {
                VortemLogger.log('Session token exists, setup can be completed');
            }
        },

        restartWizard: function(e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            VortemLogger.log('Restart wizard function called');
            
            // Show confirmation modal
            var $modal = $('#restart-wizard-modal');
            
            if (!$modal.length) {
                VortemLogger.error('Restart wizard modal not found in DOM');
                alert('Modal not found. Please refresh the page.');
                return;
            }
            
            VortemLogger.log('Modal found, positioning relative to card');
            this.positionModalRelativeToCard($modal);
            
            VortemLogger.log('Showing modal with fadeIn');
            $modal.css('display', 'block').hide().fadeIn(200);
        },

        positionModalRelativeToCard: function($modal) {
            var $card = $('.vortem-wizard-card');
            
            if ($card.length && $modal.length) {
                // Get card position and dimensions
                var cardOffset = $card.offset();
                var cardWidth = $card.outerWidth();
                var cardHeight = $card.outerHeight();
                
                // Calculate center position relative to card
                var modalTop = cardOffset.top + (cardHeight / 2);
                var modalLeft = cardOffset.left + (cardWidth / 2);
                
                // Set modal content position
                var $modalContent = $modal.find('.vortem-wizard-modal-content');
                $modalContent.css({
                    'top': modalTop + 'px',
                    'left': modalLeft + 'px',
                    'transform': 'translate(-50%, -50%)'
                });
            }
        },

        confirmRestartWizard: function() {
            var $modal = $('#restart-wizard-modal');
            var $confirmButton = $('#restart-wizard-confirm');
            
            var loadingText = VortemWizard.formatLoadingText(vortemWizard.strings.loading || 'Loading...');
            $confirmButton.prop('disabled', true).text(loadingText);
            
            $.ajax({
                url: vortemWizard.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vortem_wizard_restart',
                    nonce: vortemWizard.nonce
                },
                success: function(response) {
                    if (response.success) {
                        VortemLogger.log('Wizard restarted successfully');
                        // Redirect to step 1
                        window.location.href = response.data.redirect_url || 
                            vortemWizard.ajaxUrl.replace('admin-ajax.php', 'admin.php') + '?page=vortem-setup-wizard&step=1';
                    } else {
                        alert(response.data.message || 'Failed to restart wizard');
                        $confirmButton.prop('disabled', false).text((vortemWizard.strings && vortemWizard.strings.Restart) ? vortemWizard.strings.Restart : 'Restart');
                    }
                },
                error: function(xhr, status, error) {
                    VortemLogger.error('Error restarting wizard:', xhr, status, error);
                    alert('An error occurred while restarting the wizard. Please try again.');
                    $confirmButton.prop('disabled', false).text((vortemWizard.strings && vortemWizard.strings.Restart) ? vortemWizard.strings.Restart : 'Restart');
                }
            });
        },

        cancelRestartWizard: function() {
            $('#restart-wizard-modal').fadeOut(200);
        },

        initTermsStep: function() {
            // Check if session token exists first
            if (typeof vortemWizard === 'undefined' || !vortemWizard.hasSessionToken) {
                VortemLogger.error('Session token not found in terms step. Redirecting to features step.');
                // Redirect to features step to complete authentication
                window.location.href = vortemWizard.ajaxUrl.replace('admin-ajax.php', 'admin.php') + '?page=vortem-setup-wizard&step=3';
                return;
            }
            
            VortemLogger.log('Session token exists in terms step');
            
            // Ensure checkbox is unchecked by default
            $('#accept-terms').prop('checked', false);
            $('#accept-data-processing').prop('checked', false);
            
            // Initialize button state - disabled by default until both checkboxes are checked
            var $nextButton = $('.wizard-next');
            var $completeButton = $('.wizard-complete');
            
            // Disable Next button by default (step 3)
            if ($nextButton.length > 0) {
                $nextButton.prop('disabled', true).addClass('disabled');
            }
            
            // Disable Complete button by default (step 4)
            if ($completeButton.length > 0) {
                $completeButton.prop('disabled', true).addClass('disabled');
            }
            
            // Update button state based on current checkbox state
            this.updateTermsStepButtonState();
        },


        getCurrentStep: function() {
            var urlParams = new URLSearchParams(window.location.search);
            return parseInt(urlParams.get('step')) || 1;
        },

        nextStep: function(e) {
            e.preventDefault();
            
            VortemLogger.log('Next step clicked'); // Debug log
            
            var currentStep = VortemWizard.getCurrentStep();
            var $button = $(this);
            
            VortemLogger.log('Current step:', currentStep); // Debug log
            
            // Validate current step before proceeding
            if (!VortemWizard.validateCurrentStep(currentStep)) {
                VortemLogger.log('Step validation failed'); // Debug log
                var errorMessage = 'Please complete the current step before proceeding.';
                if (currentStep === 3) {
                    errorMessage = 'Please wait for authentication to complete.';
                } else if (currentStep === 4) {
                    errorMessage = 'Please accept the terms and conditions.';
                }
                alert(errorMessage);
                return;
            }

            VortemLogger.log('Sending AJAX request...'); // Debug log
            $button.prop('disabled', true);
            var loadingText = VortemWizard.formatLoadingText(vortemWizard.strings.loading);
            var $buttonText = $button.find('span').first();
            if ($buttonText.length) {
                $buttonText.text(loadingText);
            } else {
                $button.text(loadingText);
            }

            $.ajax({
                url: vortemWizard.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vortem_wizard_next',
                    current_step: currentStep,
                    terms_accepted: (currentStep === 3 && $('#accept-terms').is(':checked') && $('#accept-data-processing').is(':checked')) ? 'true' : 'false',
                    nonce: vortemWizard.nonce
                },
                success: function(response) {
                    VortemLogger.log('AJAX Success:', response); // Debug log
                    if (response.success) {
                        window.location.href = response.data.redirect_url;
                    } else {
                        $button.prop('disabled', false);
                        var $buttonText = $button.find('span').first();
                        if ($buttonText.length) {
                            $buttonText.text(vortemWizard.strings.next);
                        } else {
                            $button.text(vortemWizard.strings.next);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    VortemLogger.log('AJAX Error:', xhr, status, error); // Debug log
                    $button.prop('disabled', false);
                    var $buttonText = $button.find('span').first();
                    if ($buttonText.length) {
                        $buttonText.text(vortemWizard.strings.next);
                    } else {
                        $button.text(vortemWizard.strings.next);
                    }
                }
            });
        },

        prevStep: function(e) {
            e.preventDefault();
            
            var currentStep = VortemWizard.getCurrentStep();
            var $button = $(this);
            
            $button.prop('disabled', true);
            var loadingText = VortemWizard.formatLoadingText(vortemWizard.strings.loading || 'Loading...');
            var $buttonText = $button.find('span').last();
            if ($buttonText.length) {
                $buttonText.text(loadingText);
            } else {
                $button.text(loadingText);
            }

            $.ajax({
                url: vortemWizard.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vortem_wizard_prev',
                    current_step: currentStep,
                    nonce: vortemWizard.nonce
                },
                success: function(response) {
                    if (response.success) {
                        window.location.href = response.data.redirect_url;
                    } else {
                        $button.prop('disabled', false);
                        var $buttonText = $button.find('span').last();
                        if ($buttonText.length) {
                            $buttonText.text(vortemWizard.strings.back || vortemWizard.strings.previous || 'Back');
                        } else {
                            $button.text(vortemWizard.strings.back || vortemWizard.strings.previous || 'Back');
                        }
                    }
                },
                error: function() {
                    $button.prop('disabled', false);
                    var $buttonText = $button.find('span').last();
                    if ($buttonText.length) {
                        $buttonText.text(vortemWizard.strings.back || vortemWizard.strings.previous || 'Back');
                    } else {
                        $button.text(vortemWizard.strings.back || vortemWizard.strings.previous || 'Back');
                    }
                }
            });
        },


        completeWizard: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var currentStep = VortemWizard.getCurrentStep();
            
            VortemLogger.log('Complete wizard clicked - current step:', currentStep);
            
            // If we're on step 3 (Terms), go to step 4 (Complete) instead of completing wizard
            if (currentStep === 3) {
                // Check if terms are accepted
                var termsAccepted = $('#accept-terms').is(':checked') && $('#accept-data-processing').is(':checked');
                if (!termsAccepted) {
                    VortemLogger.error('Terms not accepted. Cannot proceed.');
                    alert('Please accept the terms and conditions before proceeding.');
                    return;
                }
                
                // Navigate to step 4 (Complete) - update current URL
                VortemLogger.log('Moving from step 3 to step 4');
                var currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('step', '4');
                VortemLogger.log('Redirecting to:', currentUrl.toString());
                window.location.href = currentUrl.toString();
                return;
            }
            
            // If we're on step 4 (Complete), actually complete the wizard
            $button.prop('disabled', true);
            var originalText = $button.find('span').first().text();
            var loadingText = VortemWizard.formatLoadingText(vortemWizard.strings.loading);
            $button.find('span').first().text(loadingText);

            // Check if session token exists
            if (typeof vortemWizard === 'undefined' || !vortemWizard.hasSessionToken) {
                VortemLogger.error('Session token not found. Cannot complete setup.');
                alert('Please complete authentication first.');
                $button.prop('disabled', false);
                $button.find('span').first().text(originalText);
                return;
            }

            // On step 4, checkbox is not in DOM - use server value (saved when moving from step 3)
            var termsAccepted = (typeof vortemWizard !== 'undefined' && vortemWizard.termsAccepted !== undefined) ? vortemWizard.termsAccepted : true;
            if (!termsAccepted) {
                VortemLogger.error('Terms not accepted. Cannot complete setup.');
                alert('Please accept the terms and conditions before completing setup.');
                $button.prop('disabled', false);
                $button.find('span').first().text(originalText);
                return;
            }

            // Get currency from wizard (if available)
            // Priority: 1. Selected currency from dropdown, 2. Current currency from localized data, 3. USD default
            var currencyValue = $('#wizard-currency-select').val();
            var currency = (currencyValue && currencyValue.trim() !== '') ? currencyValue.trim() : 
                          (typeof vortemWizard !== 'undefined' && vortemWizard.currentCurrency ? vortemWizard.currentCurrency : 'USD');
            
            VortemLogger.log('VortemWizard: Completing setup with currency:', currency);

            // Mark setup as completed
            $.ajax({
                url: vortemWizard.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vortem_wizard_complete',
                    currency: currency,
                    accepted: termsAccepted,
                    nonce: vortemWizard.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var redirectUrl = response.data.redirect_url;
                        if (!redirectUrl) {
                            redirectUrl = vortemWizard.ajaxUrl.replace('admin-ajax.php', 'admin.php') + '?page=vortem-owerview';
                        }
                        window.location.href = redirectUrl;
                    } else {
                        alert(response.data.message || 'Failed to complete setup');
                        $button.prop('disabled', false);
                        $button.find('span').first().text(originalText);
                    }
                },
                error: function(xhr, status, error) {
                    VortemLogger.error('Error completing wizard:', xhr, status, error);
                    alert('An error occurred while completing setup. Please try again.');
                    $button.prop('disabled', false);
                    $button.find('span').first().text(originalText);
                }
            });
        },

        completeAndGoToDashboard: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var $button = $(this);
            
            // Check if session token exists
            if (typeof vortemWizard === 'undefined' || !vortemWizard.hasSessionToken) {
                VortemLogger.error('Session token not found. Cannot complete setup.');
                alert((vortemWizard.strings && vortemWizard.strings['Please complete authentication first.']) ? vortemWizard.strings['Please complete authentication first.'] : 'Please complete authentication first.');
                return;
            }

            // Check if terms are accepted: use DB value from server, or trust user reached step 4 (default true)
            var termsAccepted = (typeof vortemWizard !== 'undefined' && vortemWizard.termsAccepted !== undefined) ? vortemWizard.termsAccepted : true;
            if (!termsAccepted) {
                VortemLogger.error('Terms not accepted. Cannot complete setup.');
                alert((vortemWizard.strings && vortemWizard.strings['Please accept the terms and conditions before completing setup.']) ? vortemWizard.strings['Please accept the terms and conditions before completing setup.'] : 'Please accept the terms and conditions before completing setup.');
                return;
            }

            // Disable button and show loading
            $button.prop('disabled', true);
            var originalHtml = $button.html();
            var loadingText = VortemWizard.formatLoadingText((vortemWizard.strings && vortemWizard.strings.loading) ? vortemWizard.strings.loading : 'Loading...');
            $button.html('<span>' + loadingText + '</span>');

            // Get currency from wizard (if available)
            var currencyValue = $('#wizard-currency-select').val();
            var currency = (currencyValue && currencyValue.trim() !== '') ? currencyValue.trim() : 
                          (typeof vortemWizard !== 'undefined' && vortemWizard.currentCurrency ? vortemWizard.currentCurrency : 'USD');
            
            VortemLogger.log('VortemWizard: Completing setup and going to dashboard with currency:', currency);

            // Mark setup as completed
            $.ajax({
                url: vortemWizard.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vortem_wizard_complete',
                    currency: currency,
                    accepted: termsAccepted,
                    nonce: vortemWizard.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var redirectUrl = response.data.redirect_url;
                        if (!redirectUrl) {
                            redirectUrl = vortemWizard.ajaxUrl.replace('admin-ajax.php', 'admin.php') + '?page=vortem-owerview';
                        }
                        window.location.href = redirectUrl;
                    } else {
                        alert(response.data.message || ((vortemWizard.strings && vortemWizard.strings['Failed to complete setup']) ? vortemWizard.strings['Failed to complete setup'] : 'Failed to complete setup'));
                        $button.prop('disabled', false);
                        $button.html(originalHtml);
                    }
                },
                error: function(xhr, status, error) {
                    VortemLogger.error('Error completing wizard:', xhr, status, error);
                    alert((vortemWizard.strings && vortemWizard.strings['An error occurred while completing setup. Please try again.']) ? vortemWizard.strings['An error occurred while completing setup. Please try again.'] : 'An error occurred while completing setup. Please try again.');
                    $button.prop('disabled', false);
                    $button.html(originalHtml);
                }
            });
        },

        validateCurrentStep: function(step) {
            switch(step) {
                case 2: // Configuration step
                    // Configuration is always valid (currency can be changed later)
                    return true;
                case 3: // Terms step
                    // Check if terms are accepted
                    if (!$('#accept-terms').is(':checked')) {
                        return false;
                    }
                    // Check if data processing consent is accepted
                    if (!$('#accept-data-processing').is(':checked')) {
                        return false;
                    }
                    // Check if session token exists
                    if (typeof vortemWizard === 'undefined' || !vortemWizard.hasSessionToken) {
                        VortemLogger.error('Session token not found. Please complete authentication first.');
                        return false;
                    }
                    break;
            }
            return true;
        },

        authenticate: function() {
            VortemLogger.log('VortemWizard: authenticate called');

            VortemLogger.log('VortemWizard: Sending sign up request...');
            VortemLogger.log('VortemWizard: AJAX URL:', vortemWizard.ajaxUrl);
            VortemLogger.log('VortemWizard: Nonce:', vortemWizard.nonce);

            // Get currency from wizard (if available)
            // Priority: 1. Selected currency from dropdown, 2. Current currency from localized data, 3. USD default
            var currencyValue = $('#wizard-currency-select').val();
            var currency = (currencyValue && currencyValue.trim() !== '') ? currencyValue.trim() : 
                          (typeof vortemWizard !== 'undefined' && vortemWizard.currentCurrency ? vortemWizard.currentCurrency : 'USD');
            
            VortemLogger.log('VortemWizard: Currency being sent:', currency);

            $.ajax({
                url: vortemWizard.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vortem_wizard_authenticate',
                    currency: currency,
                    nonce: vortemWizard.nonce
                },
                success: function(response) {
                    VortemLogger.log('VortemWizard: AJAX Success response:', response);
                    
                    if (response.success) {
                        VortemLogger.log('VortemWizard: Sign up successful');
                        VortemWizard.isAuthenticated = true;
                        // Update button states - sign up successful
                        VortemWizard.updateAuthButtons(true);
                    } else {
                        VortemLogger.log('VortemWizard: Sign up failed:', response.data);
                        VortemWizard.isAuthenticated = false;
                        // Update button states - sign up failed
                        VortemWizard.updateAuthButtons(false);
                    }
                },
                error: function(xhr, status, error) {
                    VortemLogger.log('VortemWizard: AJAX Error:', xhr, status, error);
                    VortemLogger.log('VortemWizard: Response text:', xhr.responseText);
                    VortemWizard.isAuthenticated = false;
                    // Update button states - sign up error
                    VortemWizard.updateAuthButtons(false);
                }
            });
        },

        updateTermsStepButtonState: function() {
            var termsAccepted = $('#accept-terms').is(':checked');
            var dataProcessingAccepted = $('#accept-data-processing').is(':checked');
            var bothAccepted = termsAccepted && dataProcessingAccepted;
            var $nextButton = $('.wizard-next');
            
            if (bothAccepted) {
                $nextButton.prop('disabled', false).removeClass('disabled');
                VortemLogger.log('Next button enabled - both checkboxes checked');
            } else {
                $nextButton.prop('disabled', true).addClass('disabled');
                VortemLogger.log('Next button disabled - both checkboxes must be checked');
            }
        },

        toggleTermsAcceptance: function() {
            var termsAccepted = $('#accept-terms').is(':checked');
            var dataProcessingAccepted = $('#accept-data-processing').is(':checked');
            var bothAccepted = termsAccepted && dataProcessingAccepted;
            var currentStep = this.getCurrentStep();
            var $completeButton = $('.wizard-complete');
            
            VortemLogger.log('Toggle terms acceptance - terms:', termsAccepted, 'data processing:', dataProcessingAccepted, 'both:', bothAccepted, 'step:', currentStep);
            
            // Update Next button state using the dedicated function
            this.updateTermsStepButtonState();
            
            // Only process AJAX and Complete button if we're on step 3 (Terms)
            if (currentStep === 3) {
                if (bothAccepted) {
                    // Save terms acceptance via AJAX
                    $.ajax({
                        url: vortemWizard.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'vortem_wizard_accept_terms',
                            accepted: 'true',
                            data_processing_accepted: 'true',
                            nonce: vortemWizard.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                VortemLogger.log('Terms and data processing consent accepted successfully');
                            }
                        },
                        error: function() {
                            VortemLogger.error('Failed to save terms acceptance');
                        }
                    });
                }
                
                // Also update Complete button if it exists (for step 4)
                if ($completeButton.length > 0) {
                    if (bothAccepted) {
                        $completeButton.prop('disabled', false).removeClass('disabled');
                    } else {
                        $completeButton.prop('disabled', true).addClass('disabled');
                    }
                }
            }
        },


        updateAuthButtons: function(isSuccess) {
            // Features step removed - no button update needed
        },

        switchCurrencyValue: function(currency) {
            VortemLogger.log('Switching currency to:', currency);

            $.ajax({
                url: vortemWizard.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vortem_wizard_switch_currency',
                    currency: currency,
                    nonce: vortemWizard.nonce
                },
                success: function(response) {
                    VortemLogger.log('Currency switch response:', response);
                    if (response.success) {
                        // Currency saved successfully
                        VortemLogger.log('Currency switched successfully to:', currency);
                    } else {
                        alert(vortemWizard.strings.error || 'An error occurred while switching currency');
                    }
                },
                error: function(xhr, status, error) {
                    VortemLogger.log('Currency switch error:', xhr, status, error);
                    alert(vortemWizard.strings.error || 'An error occurred while switching currency');
                }
            });
        }
    };

// Initialize when document is ready
$(document).ready(function() {
    VortemLogger.log('Document ready, initializing VortemWizard...'); // Debug log
    
    // Initialize immediately to prevent flash
    VortemWizard.init();
    
    // Test if jQuery is working
    VortemLogger.log('jQuery version:', $.fn.jquery); // Debug log
    
    // Test if button exists
    VortemLogger.log('Next button found:', $('.wizard-next').length); // Debug log
    
    // Test if restart wizard button exists
    VortemLogger.log('Restart wizard button found:', $('#restart-wizard').length); // Debug log
    VortemLogger.log('Restart wizard modal found:', $('#restart-wizard-modal').length); // Debug log
    
    // Test direct click handler as fallback
    $('#restart-wizard').on('click', function(e) {
        VortemLogger.log('Direct click handler triggered for restart wizard');
        VortemWizard.restartWizard(e);
    });
});

// Also initialize on DOMContentLoaded for faster loading
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        VortemLogger.log('DOMContentLoaded, pre-initializing currency display...');
        if (typeof VortemWizard !== 'undefined' && VortemWizard.getCurrentStep() === 1) {
            VortemWizard.preInitCurrencyDisplay();
        }
    });
} else {
    VortemLogger.log('DOM already loaded, initializing immediately...');
    if (typeof VortemWizard !== 'undefined' && VortemWizard.getCurrentStep() === 1) {
        VortemWizard.preInitCurrencyDisplay();
    }
}

})(jQuery);