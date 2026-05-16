/**
 * Vortem Navigation Sidebar JavaScript
 * Handles sidebar toggle, slide animations, and overlay
 *
 * External Libraries Used:
 * - jQuery (jQuery Foundation) - https://jquery.com/ | License: MIT | WordPress-bundled library used for DOM manipulation and event handling
 */

(function ($) {
  "use strict";

  $(document).ready(function () {
    const $toggle = $("#vortem-nav-sidebar-toggle");
    const $sidebar = $("#vortem-nav-sidebar");
    const $overlay = $("#vortem-nav-sidebar-overlay");
    const $close = $("#vortem-nav-sidebar-close");
    const $wrapper = $(".vortem-nav-sidebar-wrapper");

    // Toggle sidebar on button click
    $toggle.on("click", function (e) {
      e.preventDefault();
      e.stopPropagation();

      const isExpanded = $toggle.attr("aria-expanded") === "true";

      if (isExpanded) {
        closeSidebar();
      } else {
        openSidebar();
      }
    });

    // Close sidebar on close button click
    $close.on("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      closeSidebar();
    });

    // Close sidebar when clicking overlay
    $overlay.on("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      closeSidebar();
    });

    // Close sidebar on Escape key
    $(document).on("keydown", function (e) {
      if (e.key === "Escape" || e.keyCode === 27) {
        if ($sidebar.hasClass("active")) {
          closeSidebar();
        }
      }
    });

    // Prevent sidebar from closing when clicking inside it
    $sidebar.on("click", function (e) {
      e.stopPropagation();
    });

    /**
     * Open the sidebar
     */
    function openSidebar() {
      $sidebar.addClass("active");
      $overlay.addClass("active");
      $toggle.attr("aria-expanded", "true");

      // Prevent body scroll when sidebar is open
      $("body").css("overflow", "hidden");

      // Auto-expand current menu item after sidebar opens
      setTimeout(function() {
        autoExpandCurrentMenu();
      }, 100);
    }

    /**
     * Close the sidebar
     */
    function closeSidebar() {
      $sidebar.removeClass("active");
      $overlay.removeClass("active");
      $toggle.attr("aria-expanded", "false");

      // Close currency dropdown if open
      const $currencyDropdown = $("#vortem-nav-currency-dropdown");
      if ($currencyDropdown.length && $currencyDropdown.hasClass("active")) {
        $currencyDropdown.removeClass("active");
        $("#vortem-nav-currency-button").attr("aria-expanded", "false");
      }

      // Restore body scroll
      $("body").css("overflow", "");
    }

    // Handle RTL/LTR direction changes dynamically (sidebar position; toggle icon is hamburger, same for both)
    if ($wrapper.length) {
      const observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
          if (
            mutation.type === "attributes" &&
            mutation.attributeName === "dir"
          ) {
            // Direction changed, ensure sidebar is positioned correctly
            const $icon = $toggle.find(".dashicons");
            $icon.removeClass("dashicons-arrow-left-alt dashicons-arrow-right-alt").addClass("dashicons-menu");
          }
        });
      });

      observer.observe($wrapper[0], {
        attributes: true,
        attributeFilter: ["dir"],
      });
    }

    // Close sidebar when clicking on a menu item (optional - can be removed if you want to keep it open)
    $sidebar.on("click", "a", function () {
      // Uncomment the line below if you want the sidebar to close when clicking a menu item
      // closeSidebar();
    });

    // Handle expandable menu items with children
    $sidebar.on("click", ".vortem-nav-sidebar-link-parent", function (e) {
      // Only prevent default if clicking on the toggle icon or if we want to toggle on any click
      // For now, we'll toggle on any click of the parent link
      e.preventDefault();
      e.stopPropagation();

      const $item = $(this).closest(".vortem-nav-sidebar-item-has-children");
      const isExpanded = $item.hasClass("expanded");

      if (isExpanded) {
        $item.removeClass("expanded");
      } else {
        // Close other expanded items (optional - remove if you want multiple items open)
        $sidebar.find(".vortem-nav-sidebar-item-has-children.expanded").removeClass("expanded");
        $item.addClass("expanded");
      }
    });

    // Allow child links to navigate normally
    $sidebar.on("click", ".vortem-nav-sidebar-link-child", function (e) {
      // Allow normal navigation for child links
      // Don't prevent default or stop propagation
    });

    // Auto-expand menu items if current page is a child
    function autoExpandCurrentMenu() {
      const $currentItem = $sidebar.find(".vortem-nav-sidebar-submenu-item.current");
      if ($currentItem.length) {
        const $parentItem = $currentItem.closest(".vortem-nav-sidebar-item-has-children");
        if ($parentItem.length) {
          $parentItem.addClass("expanded");
        }
      }
    }

    // Run auto-expand on page load
    autoExpandCurrentMenu();

    // Currency Switcher
    const $currencyButton = $("#vortem-nav-currency-button");
    const $currencyDropdown = $("#vortem-nav-currency-dropdown");
    const $currencyList = $currencyDropdown.find(".vortem-nav-currency-list");
    const $currencyLoading = $currencyDropdown.find(".vortem-nav-currency-loading");
    const $currencySearchWrapper = $currencyDropdown.find(".vortem-nav-currency-search-wrapper");
    const $currencySearchInput = $currencyDropdown.find(".vortem-nav-currency-search-input");
    let currenciesLoaded = false;
    let currentCurrencyCode = null;

    // Toggle currency dropdown
    if ($currencyButton.length && $currencyDropdown.length) {
      $currencyButton.on("click", function (e) {
        e.preventDefault();
        e.stopPropagation();

        const isExpanded = $currencyButton.attr("aria-expanded") === "true";

        if (isExpanded) {
          closeCurrencyDropdown();
        } else {
          openCurrencyDropdown();
          if (!currenciesLoaded) {
            loadCurrencies();
          }
        }
      });

      // Close dropdown when clicking outside
      $(document).on("click", function (e) {
        if (
          !$currencyButton.is(e.target) &&
          !$currencyDropdown.is(e.target) &&
          $currencyDropdown.has(e.target).length === 0
        ) {
          closeCurrencyDropdown();
        }
      });

      // Close dropdown on Escape key
      $(document).on("keydown", function (e) {
        if (
          (e.key === "Escape" || e.keyCode === 27) &&
          $currencyDropdown.hasClass("active")
        ) {
          closeCurrencyDropdown();
        }
      });
    }

    /**
     * Open currency dropdown
     */
    function openCurrencyDropdown() {
      $currencyDropdown.addClass("active");
      $currencyButton.attr("aria-expanded", "true");
    }

    /**
     * Close currency dropdown
     */
    function closeCurrencyDropdown() {
      $currencyDropdown.removeClass("active");
      $currencyButton.attr("aria-expanded", "false");
      // Clear search input when closing
      if ($currencySearchInput.length) {
        $currencySearchInput.val('');
        filterCurrencies('');
      }
    }

    /**
     * Filter currencies based on search term
     */
    function filterCurrencies(searchTerm) {
      if (!$currencyList.length) {
        return;
      }

      const $options = $currencyList.find('.vortem-nav-currency-option');
      let visibleCount = 0;

      $options.each(function() {
        const $option = $(this);
        const currencyCode = $option.data('currency') || '';
        const currencyName = $option.find('.vortem-nav-currency-name').text() || '';
        const currencyCodeText = $option.find('.vortem-nav-currency-code-small').text() || '';

        // Search in currency code, name, and code text
        const matchesCode = currencyCode.toLowerCase().indexOf(searchTerm) !== -1;
        const matchesName = currencyName.toLowerCase().indexOf(searchTerm) !== -1;
        const matchesCodeText = currencyCodeText.toLowerCase().indexOf(searchTerm) !== -1;

        if (searchTerm === '' || matchesCode || matchesName || matchesCodeText) {
          $option.removeClass('hidden');
          visibleCount++;
        } else {
          $option.addClass('hidden');
        }
      });

      // Show/hide empty message if needed
      VortemLogger.log("filterCurrencies: Search term:", searchTerm, "Visible:", visibleCount);
    }

    /**
     * Load currencies from API
     */
    function loadCurrencies() {
      VortemLogger.log("loadCurrencies: Starting");
      
      if (typeof vortem_admin === "undefined") {
        VortemLogger.error("loadCurrencies: Vortem admin object not found");
        $currencyLoading.html(
          '<span style="color: #d63638;">Error: Configuration not loaded</span>'
        );
        return;
      }

      VortemLogger.log("loadCurrencies: Showing loading, hiding list");
      $currencyLoading.show();
      $currencyList.hide();

      // First, get current currency
      $.ajax({
        url: vortem_admin.ajax_url,
        type: "POST",
        data: {
          action: "vortem_get_current_currency",
          nonce: vortem_admin.nonce,
        },
        success: function (response) {
          VortemLogger.log("loadCurrencies: Current currency response", response);
          if (response && response.success && response.data && response.data.customer_currency) {
            currentCurrencyCode = response.data.customer_currency;
            VortemLogger.log("loadCurrencies: Current currency from API:", currentCurrencyCode);
          } else {
            // Fallback to saved option
            currentCurrencyCode = vortem_admin.currency_code || "USD";
            VortemLogger.log("loadCurrencies: Using fallback currency:", currentCurrencyCode);
          }
          
          // Now load currency list
          fetchCurrencyList();
        },
        error: function (xhr, status, error) {
          VortemLogger.warn("loadCurrencies: Failed to get current currency", error);
          // Fallback to saved option
          currentCurrencyCode = vortem_admin.currency_code || "USD";
          VortemLogger.log("loadCurrencies: Using fallback currency after error:", currentCurrencyCode);
          fetchCurrencyList();
        },
      });
    }

    /**
     * Fetch currency list and populate dropdown
     */
    function fetchCurrencyList() {
      VortemLogger.log("fetchCurrencyList: Starting AJAX request");
      VortemLogger.log("fetchCurrencyList: AJAX URL:", vortem_admin.ajax_url);
      VortemLogger.log("fetchCurrencyList: Action:", "vortem_get_currency_codes");
      
      // Ensure loading is visible at start
      if ($currencyLoading.length) {
        $currencyLoading.show().css("display", "flex");
      }
      if ($currencyList.length) {
        $currencyList.hide();
      }
      
      $.ajax({
        url: vortem_admin.ajax_url,
        type: "POST",
        data: {
          action: "vortem_get_currency_codes",
          nonce: vortem_admin.nonce,
        },
        timeout: 30000, // 30 second timeout
        dataType: "json",
        success: function (response) {
          VortemLogger.log("fetchCurrencyList: AJAX success", response);
          VortemLogger.log("fetchCurrencyList: Response type:", typeof response);
          VortemLogger.log("fetchCurrencyList: Elements check", {
            loadingExists: $currencyLoading.length,
            listExists: $currencyList.length,
            dropdownActive: $currencyDropdown.hasClass("active")
          });
          
          // Always hide loading first - use both hide() and CSS
          if ($currencyLoading.length) {
            $currencyLoading.hide().css({
              "display": "none",
              "visibility": "hidden",
              "opacity": "0"
            });
          }
          
          if ($currencyList.length) {
            $currencyList.empty();
            // Ensure list container is visible
            $currencyList.css({
              "display": "block",
              "visibility": "visible",
              "opacity": "1"
            });
          }

          if (response && response.success && response.data) {
            const currencies = response.data;
            VortemLogger.log("fetchCurrencyList: Currencies data", currencies, "Type:", typeof currencies, "Is Array:", Array.isArray(currencies));
            let currencyArray = [];

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

            // Normalize currencies to array format (matching currency setting section logic)
            if (Array.isArray(currencies)) {
              VortemLogger.log("fetchCurrencyList: Currencies is array, length:", currencies.length);
              currencies.forEach(function(currency) {
                var code = currency.currency_code || currency.code || currency.id;
                var countryCurrency = currency.country_currency || currency.name || currency.currency_name || '';
                var svgContent = currency.svg_content || '';
                var countryFlag = currency.country_flag || '';

                // Use SVG content if available, otherwise fall back to Unicode flag
                var flag = '';
                var flagType = 'emoji';
                if (svgContent && typeof svgContent === 'string') {
                  // Normalize SVG to ensure proper dimensions
                  var normalizedSvg = normalizeSvg(svgContent);
                  if (normalizedSvg && normalizedSvg.trim().match(/<svg[\s>]/i)) {
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
                    flagType: flagType
                  });
                }
              });
            } else if (typeof currencies === "object" && currencies !== null) {
              VortemLogger.log("fetchCurrencyList: Currencies is object, converting to array");
              $.each(currencies, function(key, data) {
                var code = data.currency_code || key;
                var countryCurrency = data.country_currency || data.name || data.currency_name || '';
                var svgContent = data.svg_content || '';
                var countryFlag = data.country_flag || '';

                // Use SVG content if available, otherwise fall back to Unicode flag
                var flag = '';
                var flagType = 'emoji';
                if (svgContent && typeof svgContent === 'string') {
                  // Normalize SVG to ensure proper dimensions
                  var normalizedSvg = normalizeSvg(svgContent);
                  if (normalizedSvg && normalizedSvg.trim().match(/<svg[\s>]/i)) {
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
                    flagType: flagType
                  });
                }
              });
            } else {
              VortemLogger.warn("fetchCurrencyList: Unexpected currency format:", typeof currencies, currencies);
            }

            VortemLogger.log("fetchCurrencyList: Currency array after normalization:", currencyArray.length, "items", currencyArray);

            // Sort currencies by name
            currencyArray.sort(function (a, b) {
              return (a.name || a.code).localeCompare(b.name || b.code);
            });

            // Populate dropdown
            if (currencyArray.length > 0) {
              VortemLogger.log("fetchCurrencyList: Populating dropdown with", currencyArray.length, "currencies");
              currencyArray.forEach(function (currency) {
                const code = currency.code;
                const name = currency.name || code;
                const flag = currency.flag || '';
                const flagType = currency.flagType || 'emoji';
                const isActive = code === currentCurrencyCode;

                const $option = $('<button></button>')
                  .addClass("vortem-nav-currency-option")
                  .attr("data-currency", code)
                  .attr("data-current", isActive ? "1" : "0");

                if (isActive) {
                  $option.addClass("active");
                }

                // Handle flag display based on type
                var flagHtml = '';
                if (flag) {
                  if (flagType === 'svg') {
                    // For SVG content, use it directly without escaping
                    flagHtml = '<span class="vortem-nav-currency-flag vortem-nav-currency-flag-svg">' + flag + '</span> ';
                  } else {
                    // For emoji flags, escape HTML
                    flagHtml = '<span class="vortem-nav-currency-flag">' + $("<div>").text(flag).html() + '</span> ';
                  }
                }

                $option.html(
                  '<span class="vortem-nav-currency-name">' +
                    flagHtml +
                    $("<div>").text(name).html() +
                    '</span><span class="vortem-nav-currency-code-small">' +
                    $("<div>").text(code).html() +
                    "</span>" +
                    (isActive
                      ? '<span class="dashicons dashicons-yes"></span>'
                      : "")
                );

                $option.on("click", function (e) {
                  e.preventDefault();
                  e.stopPropagation();

                  const $clickedOption = $(this);
                  const currencyCode = $clickedOption.data("currency");
                  const isCurrent = $clickedOption.data("current") === "1";

                  if (isCurrent) {
                    closeCurrencyDropdown();
                    return;
                  }

                  // Show loading state
                  $clickedOption.addClass("loading");
                  $currencyButton.addClass("loading");

                  // Switch currency via AJAX
                  switchCurrency(currencyCode, $clickedOption);
                });

                $currencyList.append($option);
              });

              // Show search box after currencies are loaded
              if ($currencySearchWrapper.length) {
                $currencySearchWrapper.show();
              }

              // Setup search functionality
              if ($currencySearchInput.length) {
                $currencySearchInput.off('input keyup').on('input keyup', function() {
                  const searchTerm = $(this).val().toLowerCase().trim();
                  filterCurrencies(searchTerm);
                });
              }

              // Ensure list is visible - use both methods
              if ($currencyList.length) {
                // Force show the list and ensure loading is hidden
                $currencyLoading.addClass("hidden").hide().css({
                  "display": "none",
                  "visibility": "hidden",
                  "opacity": "0"
                });
                
                // Ensure dropdown is active and visible
                if (!$currencyDropdown.hasClass("active")) {
                  $currencyDropdown.addClass("active");
                  $currencyButton.attr("aria-expanded", "true");
                }
                
                $currencyList.show().css({
                  "display": "block",
                  "visibility": "visible",
                  "opacity": "1"
                });
                
                VortemLogger.log("fetchCurrencyList: Currency list should now be visible", {
                  listChildren: $currencyList.children().length,
                  listVisible: $currencyList.is(":visible"),
                  listDisplay: $currencyList.css("display"),
                  loadingVisible: $currencyLoading.is(":visible"),
                  dropdownActive: $currencyDropdown.hasClass("active"),
                  dropdownVisible: $currencyDropdown.is(":visible")
                });
              }
              currenciesLoaded = true;
              VortemLogger.log("fetchCurrencyList: Dropdown populated successfully with", currencyArray.length, "currencies");
            } else {
              VortemLogger.warn("fetchCurrencyList: No currencies in array");
              if ($currencyList.length) {
                $currencyList.html(
                  '<div style="padding: 20px; text-align: center; color: #646970;">No currencies available</div>'
                );
                $currencyList.show().css("display", "block");
              }
            }
          } else {
            VortemLogger.error("fetchCurrencyList: Invalid response", response);
            const errorMsg = (response && response.data && response.data.message) 
              ? response.data.message 
              : "Failed to load currencies. Please check your API connection.";
            if ($currencyList.length) {
              $currencyList.html(
                '<div style="padding: 20px; text-align: center; color: #d63638;">' + 
                $("<div>").text(errorMsg).html() + 
                '</div>'
              );
              $currencyList.show().css("display", "block");
            }
          }
        },
        error: function (xhr, status, error) {
          VortemLogger.error("fetchCurrencyList: AJAX error", {
            status: status,
            error: error,
            responseText: xhr.responseText,
            statusCode: xhr.status,
            readyState: xhr.readyState
          });
          
          // Always hide loading - use both methods
          if ($currencyLoading.length) {
            $currencyLoading.hide().css("display", "none");
          }
          
          let errorMessage = "Error loading currencies";
          if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
            errorMessage = xhr.responseJSON.data.message;
          } else if (xhr.responseText) {
            try {
              const errorData = JSON.parse(xhr.responseText);
              if (errorData.data && errorData.data.message) {
                errorMessage = errorData.data.message;
              }
            } catch (e) {
              // Not JSON, use default
            }
          }
          
          if ($currencyList.length) {
            $currencyList.html(
              '<div style="padding: 20px; text-align: center; color: #d63638;">' + 
              $("<div>").text(errorMessage).html() + 
              '</div>'
            );
            $currencyList.show().css("display", "block");
          }
        },
        complete: function(xhr, status) {
          VortemLogger.log("fetchCurrencyList: AJAX complete", {
            status: status,
            readyState: xhr.readyState,
            statusCode: xhr.status
          });
          // Safety: always hide loading in complete callback - use both methods
          if ($currencyLoading.length) {
            $currencyLoading.addClass("hidden").hide().css({
              "display": "none",
              "visibility": "hidden",
              "opacity": "0"
            });
          }
          // Ensure list is visible if it has content
          if ($currencyList.length && $currencyList.children().length > 0) {
            $currencyList.css({
              "display": "block",
              "visibility": "visible",
              "opacity": "1"
            });
            VortemLogger.log("fetchCurrencyList: Ensuring list is visible in complete callback");
          }
        }
      });
    }

    /**
     * Switch currency via AJAX
     */
    function switchCurrency(currencyCode, $option) {
      if (typeof vortem_admin === "undefined") {
        VortemLogger.error("Vortem admin object not found");
        $option.removeClass("loading");
        $currencyButton.removeClass("loading");
        alert("Error: Admin configuration not loaded. Please refresh the page.");
        return;
      }

      $.ajax({
        url: vortem_admin.ajax_url,
        type: "POST",
        data: {
          action: "vortem_update_currency",
          currency_code: currencyCode,
          nonce: vortem_admin.nonce,
        },
        success: function (response) {
          $option.removeClass("loading");
          $currencyButton.removeClass("loading");

          if (response.success) {
            // Update UI
            $currencyList.find(".vortem-nav-currency-option").removeClass("active");
            $currencyList.find(".dashicons-yes").remove();
            $option.addClass("active");
            $option.append('<span class="dashicons dashicons-yes"></span>');

            // Update currency code in button
            const $currencyCode = $currencyButton.find(".vortem-nav-currency-code");
            $currencyCode.text(currencyCode.toUpperCase());

            // Update data attributes
            $currencyList.find(".vortem-nav-currency-option").attr("data-current", "0");
            $option.attr("data-current", "1");
            currentCurrencyCode = currencyCode;

            // Close dropdown
            closeCurrencyDropdown();

            // Reload page to apply currency changes
            setTimeout(function () {
              window.location.reload();
            }, 300);
          } else {
            alert(
              response.data && response.data.message
                ? response.data.message
                : "Failed to change currency. Please try again."
            );
          }
        },
        error: function (xhr, status, error) {
          $option.removeClass("loading");
          $currencyButton.removeClass("loading");
          VortemLogger.error("Currency switch error:", error);
          alert("An error occurred while switching currency. Please try again.");
        },
      });
    }

  });
  
  // ========== Global Toast System (for all Vortem pages) ==========
  window.VortemToast = window.VortemToast || {
    container: null,
    
    init: function() {
      if (this.container) return;
      
      this.container = document.getElementById('vortem-global-toast-container');
      if (!this.container) {
        this.container = document.createElement('div');
        this.container.id = 'vortem-global-toast-container';
        this.container.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:100010;display:flex;flex-direction:column;gap:10px;';
        document.body.appendChild(this.container);
      }
    },
    
    show: function(type, title, message, duration) {
      duration = duration || 5000;
      this.init();
      
      var icons = {
        success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
        error: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
        warning: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>'
      };
      
      var colors = {
        success: { bg: '#ecfdf5', border: '#10b981', icon: '#10b981', title: '#047857' },
        error: { bg: '#fef2f2', border: '#ef4444', icon: '#ef4444', title: '#b91c1c' },
        warning: { bg: '#fffbeb', border: '#f59e0b', icon: '#f59e0b', title: '#b45309' }
      };
      
      var c = colors[type] || colors.warning;
      
      var toast = document.createElement('div');
      toast.style.cssText = 'display:flex;align-items:flex-start;gap:12px;padding:16px 20px;border-radius:12px;background:' + c.bg + ';border:2px solid ' + c.border + ';box-shadow:0 10px 40px rgba(0,0,0,0.15);max-width:400px;min-width:300px;opacity:0;transform:translateY(20px) scale(0.95);transition:all 0.3s cubic-bezier(0.34,1.56,0.64,1);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;';
      
      toast.innerHTML = '<div style="flex-shrink:0;color:' + c.icon + ';">' + (icons[type] || icons.warning) + '</div>' +
        '<div style="flex:1;">' +
          '<div style="font-size:14px;font-weight:600;color:' + c.title + ';margin-bottom:2px;">' + title + '</div>' +
          '<div style="font-size:13px;color:#64748b;line-height:1.5;">' + message + '</div>' +
        '</div>' +
        '<button onclick="this.parentElement.remove()" style="flex-shrink:0;width:24px;height:24px;display:flex;align-items:center;justify-content:center;border:none;background:transparent;color:#94a3b8;cursor:pointer;border-radius:6px;">' +
          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
        '</button>';
      
      this.container.appendChild(toast);
      
      setTimeout(function() { toast.style.opacity = '1'; toast.style.transform = 'translateY(0) scale(1)'; }, 10);
      
      setTimeout(function() {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(20px) scale(0.95)';
        setTimeout(function() { toast.remove(); }, 300);
      }, duration);
      
      return toast;
    },
    
    success: function(title, message, duration) { return this.show('success', title, message, duration); },
    error: function(title, message, duration) { return this.show('error', title, message, duration); },
    warning: function(title, message, duration) { return this.show('warning', title, message, duration); }
  };
})(jQuery);
