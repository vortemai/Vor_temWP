/**
 * Vortem Button Handlers - Clean Implementation
 *
 * External Libraries Used:
 * - jQuery (jQuery Foundation) - https://jquery.com/ | License: MIT | WordPress-bundled library used for DOM manipulation, AJAX, and event handling
 */

(function ($) {
  "use strict";

  VortemLogger.log("=== VORTEM BUTTONS SCRIPT LOADED ===");

  // Wait for DOM to be ready
  $(document).ready(function () {
    VortemLogger.log("DOM Ready - Initializing Vortem buttons");

    // Check if we have the required data
    if (typeof vortem_admin === "undefined") {
      VortemLogger.error("vortem_admin object is not defined!");
      return;
    }

    VortemLogger.log("vortem_admin:", vortem_admin);

    // Remove any existing handlers for import-product-btn to prevent conflicts
    $(document).off("click", ".import-product-btn");

    // Auto-validate endpoint and fetch products when user reaches vortem-products page
    if (window.location.href.indexOf("vortem-products") !== -1) {
      VortemLogger.log("=== AUTO-LOADING PRODUCTS ON PAGE LOAD ===");
      
      // Wait for DOM to be fully ready
      $(document).ready(function() {
        VortemLogger.log("DOM ready, starting auto-fetch...");
        
        // Check if the skeleton loader exists (confirms we're on the right page)
        if ($("#products-skeleton-loader").length > 0) {
          VortemLogger.log("Skeleton loader found, proceeding with auto-fetch");
          autoFetchProducts();
        } else {
          VortemLogger.log("Skeleton loader not found, skipping auto-fetch");
        }
        
        // Fallback: If products still aren't loaded after 3 seconds, try again
        setTimeout(function() {
          if ($("#products-skeleton-loader").is(":visible")) {
            VortemLogger.log("=== FALLBACK: RETRYING PRODUCT FETCH ===");
            autoFetchProducts();
          }
        }, 3000);
      });
    }

    // Auto-validate endpoint function
    function autoValidateEndpoint() {
      VortemLogger.log("=== AUTO-VALIDATING ENDPOINT ===");

      // Show validation notice
      showAutoValidationNotice();

      // Make AJAX request
      $.ajax({
        url: vortem_admin.ajax_url,
        type: "POST",
        dataType: "json",
        data: {
          action: "vortem_validate_endpoint",
          nonce: vortem_admin.nonce,
        },
        success: function (response) {
          VortemLogger.log("Auto-validate endpoint response:", response);
          if (response.success) {
            showAutoValidationSuccess();
            // Remove validate endpoint button after successful validation
            $("#validate-endpoint").fadeOut(500, function () {
              $(this).remove();
            });
            // Automatically fetch products after successful validation
            autoFetchProducts();
          } else {
            showAutoValidationError(response.data.message || "Unknown error");
            // Show notices for failed validation
            showValidationFailedNotices();
          }
        },
        error: function (xhr, status, error) {
          VortemLogger.error("Auto-validate endpoint error:", xhr.responseText);
          showAutoValidationError("Connection error: " + error);
        },
      });
    }

    // Show auto-validation notice
    function showAutoValidationNotice() {
      var noticeHtml =
        '<div class="notice notice-info vortem-auto-validation-notice vortem-plugin-notice is-dismissible" style="margin: 20px 0;">' +
        "<p><strong>Validating Endpoint...</strong></p>" +
        "<p>Automatically checking API endpoint connection...</p>" +
        '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>' +
        "</div>";

      // Remove any existing auto-validation notices
      $(".vortem-auto-validation-notice").remove();

      // Add notice to page content area
      $(".vortem-page-content").prepend(noticeHtml);
    }

    // Show auto-validation success
    function showAutoValidationSuccess() {
      var noticeHtml =
        '<div class="notice notice-success vortem-auto-validation-notice vortem-plugin-notice is-dismissible" style="margin: 20px 0;">' +
        "<p><strong>✅ Endpoint Validation Successful!</strong></p>" +
        "<p>Vortem.ai API endpoint is working correctly. You can now fetch products.</p>" +
        '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>' +
        "</div>";

      // Replace existing notice
      $(".vortem-auto-validation-notice").replaceWith(noticeHtml);
    }

    // Show auto-validation error
    function showAutoValidationError(errorMessage) {
      var noticeHtml =
        '<div class="notice notice-error vortem-auto-validation-notice vortem-plugin-notice is-dismissible" style="margin: 20px 0;">' +
        "<p><strong>❌ Endpoint Validation Failed</strong></p>" +
        "<p>API endpoint validation failed: " +
        errorMessage +
        "</p>" +
        "<p>Please check your API configuration and try again.</p>" +
        '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>' +
        "</div>";

      // Replace existing notice
      $(".vortem-auto-validation-notice").replaceWith(noticeHtml);
    }

    // Show validation failed notices
    function showValidationFailedNotices() {
      var readyNoticeHtml =
        '<div class="notice notice-info vortem-notice vortem-plugin-notice is-dismissible" style="margin: 20px 0;">' +
        "<p><strong>Ready to Fetch Products</strong></p>" +
        '<p>Click "Fetch Products" to load products from the API.</p>' +
        '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>' +
        "</div>";

      var noProductsNoticeHtml =
        '<div class="notice notice-info vortem-notice vortem-plugin-notice is-dismissible" style="margin: 20px 0;">' +
        "<p><strong>No Products Available</strong></p>" +
        "<p>No products have been synced yet. Please fetch products from the API first.</p>" +
        '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>' +
        "</div>";

      // Add notices to page content area
      $(".vortem-page-content").append(readyNoticeHtml);
      $(".vortem-page-content").append(noProductsNoticeHtml);
    }

    // Auto-fetch products function
    function autoFetchProducts(page) {
      VortemLogger.log("=== AUTO-FETCHING PRODUCTS ===");
      VortemLogger.log("Page parameter:", page);
      VortemLogger.log("Skeleton loader visible:", $("#products-skeleton-loader").is(":visible"));
      VortemLogger.log("Dashboard content exists:", $("#product-dashboard-content").length > 0);

      // Get products per page from database (default to 16 if not set)
      var productsPerPage = vortem_admin.products_per_page || 16;
      page = page || 1;

      VortemLogger.log("Products per page:", productsPerPage);
      VortemLogger.log("Fetching page:", page);

      // Show fetching notice
      showAutoFetchingNotice();

      // Check if filter state exists (from admin page)
      var showImportedOnly = 0;
      if (typeof filterState !== "undefined" && filterState.showImportedOnly) {
        showImportedOnly = 1;
      }

      // Use the same AJAX approach as the refresh button
      var ajaxData = {
        action: "vortem_fetch_products",
        nonce: vortem_admin.nonce,
        limit: productsPerPage,
        page: page,
        show_imported_only: showImportedOnly
      };

      // Make AJAX request to fetch products
      $.ajax({
        url: vortem_admin.ajax_url,
        type: "POST",
        dataType: "json",
        data: ajaxData,
        success: function (response) {
          VortemLogger.log("Auto-fetch products response:", response);
          VortemLogger.log('Auto-fetch response success:', response.success);
          
          if (response.success && response.data) {
            // Check for products in different possible locations (same as refresh button)
            var products = null;
            if (response.data.products && Array.isArray(response.data.products)) {
              products = response.data.products;
              VortemLogger.log('Products found in response.data.products:', products.length);
            } else if (response.data.data && Array.isArray(response.data.data)) {
              products = response.data.data;
              VortemLogger.log('Products found in response.data.data:', products.length);
            } else if (Array.isArray(response.data)) {
              products = response.data;
              VortemLogger.log('Products found in response.data (direct array):', products.length);
            } else {
              VortemLogger.log('No products found in response');
            }
            
            if (products && products.length > 0) {
              var totalFound = response.data.total_found || products.length;
              var currentPage = response.data.page || page || 1;
              var totalPages = response.data.total_pages || 1;
              
              showAutoFetchSuccess(products.length);
              displayProducts(products, {
                currentPage: currentPage,
                totalPages: totalPages,
                totalFound: totalFound,
                limit: productsPerPage,
              });
            } else {
              showAutoFetchError("No products found in API response");
            }
          } else {
            showAutoFetchError(response.data.message || "Unknown error");
          }
        },
        error: function (xhr, status, error) {
          VortemLogger.error("Auto-fetch products error:", xhr.responseText);
          showAutoFetchError("Connection error: " + error);
        },
      });
    }

    // Show auto-fetching notice
    function showAutoFetchingNotice() {
      var noticeHtml =
        '<div class="notice notice-info vortem-auto-fetch-notice vortem-plugin-notice is-dismissible" style="margin: 20px 0;">' +
        "<p><strong>Fetching Products...</strong></p>" +
        "<p>Automatically loading products from the API...</p>" +
        '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>' +
        "</div>";

      // Make sure the target container exists
      if ($("#status-messages").length === 0) {
        $("body").append(
          '<div id="status-messages" style="margin-top: 20px;"></div>'
        );
      }

      // Remove any existing auto-fetch notices
      $("#status-messages .vortem-auto-fetch-notice").remove();

      // Inject the notice HTML inside the #status-messages container
      $("#status-messages").html(noticeHtml);

      // Enable dismiss button functionality
      $(document).on("click", ".notice-dismiss", function () {
        $(this)
          .closest(".notice")
          .fadeOut(3000, function () {
            $(this).remove();
          });
      });
    }

    // Show auto-fetch success (make it globally accessible)
    window.showAutoFetchSuccess = function (count) {
      var noticeHtml =
        '<div class="notice notice-success vortem-auto-fetch-notice vortem-plugin-notice is-dismissible" style="margin: 20px 0;">' +
        "<p><strong>✅ Products Fetched Successfully!</strong></p>" +
        "<p>Successfully loaded " +
        count +
        " products from the API.</p>" +
        '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>' +
        "</div>";

      // Get the status-messages container
      var statusMessages = $("#status-messages");

      // If container exists, replace its contents with the notice
      if (statusMessages.length) {
        // Remove any existing notices in the container
        statusMessages.empty();
        statusMessages.html(noticeHtml);

        // Trigger WordPress notice dismiss functionality
        $(document).trigger("wp-updates-notice-added");
      } else {
        // Fallback: replace existing notice if status-messages container doesn't exist
        $(".vortem-auto-fetch-notice").replaceWith(noticeHtml);
      }

      // Auto-dismiss after 5 seconds
      setTimeout(function () {
        $(".vortem-auto-fetch-notice").fadeOut(500, function () {
          $(this).remove();
        });
      }, 5000);
    };

    // Show auto-fetch error
    function showAutoFetchError(errorMessage) {
      var noticeHtml =
        '<div class="notice notice-error vortem-auto-fetch-notice vortem-plugin-notice is-dismissible" style="margin: 20px 0;">' +
        "<p><strong>❌ Product Fetch Failed</strong></p>" +
        "<p>Failed to fetch products: " +
        errorMessage +
        "</p>" +
        "<p>Please check your API configuration and try again.</p>" +
        '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>' +
        "</div>";

      // Replace existing notice
      $(".vortem-auto-fetch-notice").replaceWith(noticeHtml);
    }

    // Handle dismiss button clicks for auto-validation notices
    $(document).on(
      "click",
      ".vortem-auto-validation-notice .notice-dismiss",
      function () {
        $(this)
          .closest(".vortem-auto-validation-notice")
          .fadeOut(500, function () {
            $(this).remove();
          });
      }
    );

    // Handle dismiss button clicks for auto-fetch notices
    $(document).on(
      "click",
      ".vortem-auto-fetch-notice .notice-dismiss",
      function () {
        $(this)
          .closest(".vortem-auto-fetch-notice")
          .fadeOut(500, function () {
            $(this).remove();
          });
      }
    );

    // Handle dismiss button clicks for validation failed notices
    $(document).on("click", ".vortem-notice .notice-dismiss", function () {
      $(this)
        .closest(".vortem-notice")
        .fadeOut(500, function () {
          $(this).remove();
        });
    });

    // Validate Endpoint Button Handler
    $(document).on("click", "#validate-endpoint", function (e) {
      e.preventDefault();
      VortemLogger.log("=== VALIDATE ENDPOINT BUTTON CLICKED ===");

      var button = $(this);
      var originalText = button.text();

      // Disable button
      button.prop("disabled", true).text("Validating...");

      // Make AJAX request
      $.ajax({
        url: vortem_admin.ajax_url,
        type: "POST",
        dataType: "json",
        data: {
          action: "vortem_validate_endpoint",
          nonce: vortem_admin.nonce,
        },
        success: function (response) {
          VortemLogger.log("Validate endpoint response:", response);
          if (response.success) {
            showVortemMessage("Endpoint validation successful!", "success");
            // Remove validate endpoint button after successful validation
            button.fadeOut(500, function () {
              $(this).remove();
            });
          } else {
            showVortemMessage(
              "Endpoint validation failed: " +
                (response.data.message || "Unknown error"),
              "error"
            );
            button.prop("disabled", false).text(originalText);
          }
        },
        error: function (xhr, status, error) {
          VortemLogger.error("Validate endpoint error:", xhr.responseText);
          showVortemMessage("Failed to validate endpoint: " + error, "error");
          button.prop("disabled", false).text(originalText);
        },
      });
    });

    // Helper function to show messages
    function showVortemMessage(message, type) {
      var alertClass = type === "success" ? "notice-success" : "notice-error";
      var messageHtml =
        '<div class="notice ' +
        alertClass +
        ' is-dismissible" style="margin: 20px 0;"><p>' +
        message +
        "</p></div>";

      // Remove old messages
      $(".vortem-message").remove();

      // Add new message to page content area
      $(".vortem-page-content").prepend(
        '<div class="vortem-message">' + messageHtml + "</div>"
      );

      // Auto-dismiss after 5 seconds
      setTimeout(function () {
        $(".vortem-message").fadeOut(500, function () {
          $(this).remove();
        });
      }, 5000);
    }

    // Helper function to format numbers with commas
    function formatNumberWithCommas(num, decimals) {
      if (num === null || num === undefined || isNaN(num)) {
        return '0';
      }
      var parts = num.toFixed(decimals).split('.');
      parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
      return parts.join('.');
    }

    // Helper function to display products with pagination
    function displayProducts(products, paginationData) {
      VortemLogger.log("Displaying products:", products);
      VortemLogger.log("Pagination data:", paginationData);

      var dashboardContent = $("#product-dashboard-content");
      if (!dashboardContent.length) {
        VortemLogger.error("Product dashboard content div not found");
        return;
      }

      if (!products || products.length === 0) {
        dashboardContent.html(
          '<div class="notice notice-info vortem-notice vortem-plugin-notice"><p><strong>No Products Found</strong></p><p>No products were retrieved from the API. Please check your connection and try again.</p></div>'
        );
        return;
      }

      paginationData = paginationData || {
        currentPage: 1,
        totalPages: 1,
        totalFound: products.length,
        limit: 12,
      };

      var html = '<div class="products-grid">';

      products.forEach(function (product) {
        var imageUrl =
          product.images && product.images.main ? product.images.main : "";
        
        // Price handling - support price range and sale price
        var priceOriginal = null;
        var priceSale = null;
        var priceLow = null;
        var priceHigh = null;
        var currency = (typeof vortem_admin !== 'undefined' && vortem_admin.currency_code) ? vortem_admin.currency_code : 'USD';
        
        if (product.variations && product.variations.length > 0) {
          priceSale = parseFloat(product.variations[0].price);
        } else if (product.price) {
          // Check for high_price and low_price fields (range format)
          if (product.price.low_price) {
            priceLow = parseFloat(product.price.low_price);
          }
          if (product.price.high_price) {
            priceHigh = parseFloat(product.price.high_price);
          }
          // Fallback to sale/original format
          if (product.price.sale) {
            priceSale = parseFloat(product.price.sale);
          }
          if (product.price.original) {
            priceOriginal = parseFloat(product.price.original);
          }
          if (product.price.currency) {
            currency = product.price.currency;
          }
        }

        var productId = product.product_id || product.sku;
        var productTitle = product.title || "Untitled Product";
        var category = product.vortem_cat || '';

        // Determine status badge text based on import status
        var addedText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.added) ? vortem_admin.strings.added : 'Added';
        var newText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.new) ? vortem_admin.strings.new : 'NEW';
        var statusBadgeText =
          product.woo_product_id && product.woo_product_id !== ""
            ? addedText
            : newText;
        var statusBadgeClass = (product.woo_product_id && product.woo_product_id !== '') ? 'status-added' : 'status-new';
        
        // Sales count
        var salescount = product.salescount || product.sales_count || product.salesCount || '0';

        // Create modern minimal product card
        html +=
          '<div class="vortem-product-card" data-product-id="' +
          productId +
          '">';

        // Image container
        html += '<div class="product-image-container">';
        html += '<div class="product-status-badge ' + statusBadgeClass + '">' + statusBadgeText + '</div>';
        // Preview button (only for products added to WooCommerce)
        if (product.woo_product_id && product.woo_product_id !== '') {
          var previewUrl = product.preview_url || (typeof vortem_admin !== 'undefined' && vortem_admin.site_url ? vortem_admin.site_url + '?p=' + product.woo_product_id + '&preview=true' : '#');
          var previewText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.preview) ? vortem_admin.strings.preview : 'Preview';
          html += '<a href="' + previewUrl + '" target="_blank" class="product-preview-button" data-woo-product-id="' + product.woo_product_id + '">' + previewText + '</a>';
        }
        if (imageUrl) {
          html += '<img src="' + imageUrl + '" alt="' + productTitle + '" loading="lazy" onerror="var img=this; img.onerror=null; img.style.display=\'none\'; var placeholder=document.createElement(\'div\'); placeholder.className=\'no-image-placeholder\'; placeholder.innerHTML=\'<svg width=\\\'64\\\' height=\\\'64\\\' viewBox=\\\'0 0 24 24\\\' fill=\\\'none\\\' xmlns=\\\'http://www.w3.org/2000/svg\\\' style=\\\'opacity: 0.3; margin-bottom: 8px;\\\'><path d=\\\'M20 7L12 3L4 7M20 7L12 11M20 7V17L12 21M12 11L4 7M12 11V21M4 7V17L12 21\\\' stroke=\\\'currentColor\\\' stroke-width=\\\'2\\\' stroke-linecap=\\\'round\\\' stroke-linejoin=\\\'round\\\'/></svg><span>No Image Available</span>\'; img.parentNode.appendChild(placeholder);">';
        } else {
          html += '<div class="no-image-placeholder"><svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="opacity: 0.3; margin-bottom: 8px;"><path d="M20 7L12 3L4 7M20 7L12 11M20 7V17L12 21M12 11L4 7M12 11V21M4 7V17L12 21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg><span>No Image Available</span></div>';
        }
        html += "</div>";

        // Product content section
        html += '<div class="product-content-section">';

        // Category badge (if available)
        if (category) {
          var categoryParts = category.split('/');
          var categoryName = categoryParts[categoryParts.length - 1] || category;
          html += '<div class="product-category-badge">' + categoryName + '</div>';
        }

        // Product title
        html += '<div class="product-title-section">';
        html += '<h3>' + productTitle + '</h3>';
        html += "</div>";

        // Price section - modern minimal design with sales badge on the same line
        html += '<div class="product-price-container">';
        html += '<div class="product-price-wrapper">';
        // Display price range if both low_price and high_price are available
        if (priceLow !== null && priceHigh !== null) {
          html += '<span class="product-price-value">' + formatNumberWithCommas(priceLow, 1) + ' - ' + formatNumberWithCommas(priceHigh, 1) + ' ' + currency + '</span>';
        } else if (priceLow === null && priceHigh !== null) {
          // Display only high_price if low_price is null
          html += '<span class="product-price-value">' + formatNumberWithCommas(priceHigh, 2) + ' ' + currency + '</span>';
        } else if (priceSale !== null) {
          html += '<span class="product-price-value">' + formatNumberWithCommas(priceSale, 2) + ' ' + currency + '</span>';
          if (priceOriginal !== null && priceOriginal > priceSale) {
            html += '<span class="product-price-original">' + formatNumberWithCommas(priceOriginal, 2) + ' ' + currency + '</span>';
          }
        } else if (priceOriginal !== null) {
          html += '<span class="product-price-value">' + formatNumberWithCommas(priceOriginal, 2) + ' ' + currency + '</span>';
        } else {
          html += '<span class="product-price-value">N/A</span>';
        }
        html += '</div>';
        
        // Sales count badge on the right side
        if (salescount && salescount !== '0') {
          var salesText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.sales) ? vortem_admin.strings.sales : 'sales';
          html += '<div class="product-sales-badge">' + salescount + ' ' + salesText + '</div>';
        }
        html += '</div>';

        // Action buttons
        html += '<div class="product-actions-section">';
        html += '<div class="product-actions-buttons">';
        
        var isImported = product.woo_product_id && product.woo_product_id !== '';
        var importText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.import) ? vortem_admin.strings.import : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 4px;"><path d="M12 15V3M12 15L8 11M12 15L16 11M2 17L2 19C2 20.1046 2.89543 21 4 21L20 21C21.1046 21 22 20.1046 22 19V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Import';
        var deleteText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.delete) ? vortem_admin.strings.delete : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 4px;"><path d="M3 6H5H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 6V4C8 3.46957 8.21071 2.96086 8.58579 2.58579C8.96086 2.21071 9.46957 2 10 2H14C14.5304 2 15.0391 2.21071 15.4142 2.58579C15.7893 2.96086 16 3.46957 16 4V6M19 6V20C19 20.5304 18.7893 21.0391 18.4142 21.4142C18.0391 21.7893 17.5304 22 17 22H7C6.46957 22 5.96086 21.7893 5.58579 21.4142C5.21071 21.0391 5 20.5304 5 20V6H19Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 11V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 11V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Delete';
        
        if (isImported) {
          html += '<button class="button button-secondary delete-product-btn" data-product-id="' + productId + '">' + deleteText + '</button>';
          html += '<button class="button button-primary import-product-btn" data-product-id="' + productId + '" style="display: none;">' + importText + '</button>';
        } else {
          html += '<button class="button button-primary import-product-btn" data-product-id="' + productId + '">' + importText + '</button>';
          html += '<button class="button button-secondary delete-product-btn" data-product-id="' + productId + '" style="display: none;">' + deleteText + '</button>';
        }
        
        html += "</div>";
        html += "</div>";
        html += "</div>"; // End content section
        html += "</div>"; // End card
      });

      html += "</div>";

      // Add pagination controls
      html += buildPaginationControls(paginationData);

      dashboardContent.html(html);

      // Check import status for each product
      checkProductsImportStatus(products);
      
      // Also check if there are recently imported products from sessionStorage
      try {
        var importedProducts = JSON.parse(sessionStorage.getItem('vortem_imported_products') || '[]');
        if (importedProducts.length > 0) {
          VortemLogger.log("Found recently imported products, re-checking status");
          // Re-check status for all products on page
          setTimeout(function() {
            var allProducts = [];
            $('.vortem-product-card').each(function() {
              var card = $(this);
              var cardProductId = card.data('product-id');
              if (cardProductId) {
                allProducts.push({ product_id: cardProductId, sku: cardProductId });
              }
            });
            if (allProducts.length > 0) {
              checkProductsImportStatus(allProducts);
            }
            // Clear old imported products (older than 5 minutes)
            var now = Date.now();
            var recentImports = importedProducts.filter(function(item) {
              return (now - item.timestamp) < 300000; // 5 minutes
            });
            sessionStorage.setItem('vortem_imported_products', JSON.stringify(recentImports));
          }, 500);
        }
      } catch (e) {
        VortemLogger.error("Error checking sessionStorage:", e);
      }

      // Attach pagination event handlers
      attachPaginationHandlers();
    }

    // Build pagination controls HTML
    function buildPaginationControls(paginationData) {
      var currentPage = paginationData.currentPage || 1;
      var totalPages = paginationData.totalPages || 1;
      var totalFound = paginationData.totalFound || 0;

      var showingText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.showing) ? vortem_admin.strings.showing : 'Showing';
      var productsText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.products) ? vortem_admin.strings.products : 'products';
      var ofText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.of) ? vortem_admin.strings.of : 'of';
      
      if (totalPages <= 1) {
        return (
          '<div class="vortem-pagination-info">' + showingText + ' ' +
          totalFound +
          ' ' + productsText + '</div>'
        );
      }

      var html = '<div class="vortem-pagination">';

      // Pagination info
      var startItem = (currentPage - 1) * (paginationData.limit || 12) + 1;
      var endItem = Math.min(
        currentPage * (paginationData.limit || 12),
        totalFound
      );
      html += '<div class="vortem-pagination-info">';
      html +=
        showingText + ' ' +
        startItem +
        "-" +
        endItem +
        ' ' + ofText + ' ' +
        totalFound +
        ' ' + productsText;
      html += "</div>";

      // Pagination controls
      html += '<div class="vortem-pagination-controls">';

      // Previous button
      var previousText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.previous) ? vortem_admin.strings.previous : '← Previous';
      if (currentPage > 1) {
        html +=
          '<button type="button" class="button vortem-pagination-btn" data-page="' +
          (currentPage - 1) +
          '">' + previousText + '</button>';
      } else {
        html +=
          '<button type="button" class="button vortem-pagination-btn" disabled>' + previousText + '</button>';
      }

      // Page numbers (show up to 5 pages around current page)
      var startPage = Math.max(1, currentPage - 2);
      var endPage = Math.min(totalPages, currentPage + 2);

      if (startPage > 1) {
        html +=
          '<button type="button" class="button vortem-pagination-btn" data-page="1">1</button>';
        if (startPage > 2) {
          html += "<span>...</span>";
        }
      }

      for (var i = startPage; i <= endPage; i++) {
        if (i === currentPage) {
          html +=
            '<button type="button" class="button button-primary vortem-pagination-btn" data-page="' +
            i +
            '" disabled>' +
            i +
            "</button>";
        } else {
          html +=
            '<button type="button" class="button vortem-pagination-btn" data-page="' +
            i +
            '">' +
            i +
            "</button>";
        }
      }

      if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
          html += "<span>...</span>";
        }
        html +=
          '<button type="button" class="button vortem-pagination-btn" data-page="' +
          totalPages +
          '">' +
          totalPages +
          "</button>";
      }

      // Next button
      var nextText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.next) ? vortem_admin.strings.next : 'Next →';
      if (currentPage < totalPages) {
        html +=
          '<button type="button" class="button vortem-pagination-btn" data-page="' +
          (currentPage + 1) +
          '">' + nextText + '</button>';
      } else {
        html +=
          '<button type="button" class="button vortem-pagination-btn" disabled>' + nextText + '</button>';
      }

      html += "</div>";
      html += "</div>";

      return html;
    }

    // Attach pagination event handlers
    function attachPaginationHandlers() {
      $(document).off("click", ".vortem-pagination-btn");
      $(document).on("click", ".vortem-pagination-btn", function (e) {
        e.preventDefault();

        var button = $(this);
        if (button.prop("disabled")) {
          return;
        }

        var page = parseInt(button.data("page"));
        if (!page || page < 1) {
          return;
        }

        VortemLogger.log("Pagination: Loading page", page);
        autoFetchProducts(page);
      });
    }

    // Update product import notice in vortem-page-content
    function updateProductImportNotice(productId, success, errorMessage) {
      VortemLogger.log(
        "Updating product import notice:",
        productId,
        success,
        errorMessage
      );

      // Find all notice-info elements within vortem-page-content
      var notices = $(".vortem-page-content .notice.notice-info");

      if (notices.length > 0) {
        // Update the first notice-info element
        var noticeHtml =
          '<div class="notice notice-info vortem-notice vortem-plugin-notice">';
        if (success) {
          noticeHtml +=
            "<p><strong>Product Imported Successfully!</strong></p>";
          noticeHtml +=
            "<p>Product ID " +
            productId +
            " has been imported to WooCommerce as a draft.</p>";
        } else {
          noticeHtml += "<p><strong>Product Import Failed</strong></p>";
          noticeHtml +=
            "<p>Failed to import product ID " +
            productId +
            ": " +
            (errorMessage || "Unknown error") +
            "</p>";
        }
        noticeHtml += "</div>";

        notices.first().replaceWith(noticeHtml);
      } else {
        // If no notice exists, add one at the top of vortem-page-content
        var vortemPageContent = $(".vortem-page-content");
        if (vortemPageContent.length > 0) {
          var noticeHtml =
            '<div class="notice notice-info vortem-notice vortem-plugin-notice">';
          if (success) {
            noticeHtml +=
              "<p><strong>Product Imported Successfully!</strong></p>";
            noticeHtml +=
              "<p>Product ID " +
              productId +
              " has been imported to WooCommerce as a draft.</p>";
          } else {
            noticeHtml += "<p><strong>Product Import Failed</strong></p>";
            noticeHtml +=
              "<p>Failed to import product ID " +
              productId +
              ": " +
              (errorMessage || "Unknown error") +
              "</p>";
          }
          noticeHtml += "</div>";

          vortemPageContent.prepend(noticeHtml);
        }
      }
    }

    // Check import status for products
    function checkProductsImportStatus(products) {
      VortemLogger.log("Checking import status for products...");

      products.forEach(function (product) {
        var productId = product.product_id || product.sku;
        // Use SKU from product, or fallback to product_id (without AE_ prefix if present)
        var sku = product.sku || product.product_id || "";
        // Remove AE_ prefix from SKU if present for better matching
        if (sku && sku.indexOf('AE_') === 0) {
          sku = sku.substring(3);
        }

        $.ajax({
          url: vortem_admin.ajax_url,
          type: "POST",
          data: {
            action: "vortem_check_product_status",
            nonce: vortem_admin.nonce,
            product_id: productId,
            sku: sku,
          },
          success: function (response) {
            if (response.success) {
              // Try to find card by productId (with or without AE_ prefix)
              var card = $('.vortem-product-card[data-product-id="' + productId + '"]');
              
              // If not found and productId has "AE_" prefix, try without it
              if (card.length === 0 && productId.indexOf('AE_') === 0) {
                var productIdWithoutPrefix = productId.substring(3); // Remove "AE_" prefix
                card = $('.vortem-product-card[data-product-id="' + productIdWithoutPrefix + '"]');
              }
              
              // If still not found and productId doesn't have "AE_" prefix, try with it
              if (card.length === 0 && productId.indexOf('AE_') !== 0) {
                var productIdWithPrefix = 'AE_' + productId;
                card = $('.vortem-product-card[data-product-id="' + productIdWithPrefix + '"]');
              }
              
              if (card.length === 0) {
                VortemLogger.error("Card not found for product:", productId);
                return;
              }
              
              var importBtn = card.find(".import-product-btn");
              var deleteBtn = card.find(".delete-product-btn");
              var imageContainer = card.find(".product-image-container");

              // If product exists in WooCommerce (even if not properly imported), show Delete button
              if (response.data.exists_in_woocommerce || response.data.is_imported) {
                // Hide import button and show delete button
                importBtn.hide();
                deleteBtn.show();

                // Update status badge
                var statusBadge = card.find(".product-status-badge");
                var addedText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.added) ? vortem_admin.strings.added : 'Added';
                var existsText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.exists) ? vortem_admin.strings.exists : 'Exists';
                if (response.data.is_imported) {
                  // Properly imported
                  statusBadge.removeClass("status-new").addClass("status-added").text(addedText).css({
                    background: "rgb(70, 180, 80)",
                    color: "white",
                    display: "block",
                  });
                } else {
                  // Exists but not properly imported
                  statusBadge.text(existsText).css({
                    background: "#f56e28",
                    color: "white",
                    display: "block",
                  });
                }

                // Add preview button if product has woo_product_id
                if (response.data.woo_product_id && imageContainer.length > 0) {
                  var existingPreview = imageContainer.find('.product-preview-button');
                  if (existingPreview.length === 0) {
                    var previewUrl = (typeof vortem_admin !== 'undefined' && vortem_admin.site_url) ? vortem_admin.site_url + '?p=' + response.data.woo_product_id + '&preview=true' : '#';
                    var previewText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.preview) ? vortem_admin.strings.preview : 'Preview';
                    var previewButton = '<a href="' + previewUrl + '" target="_blank" class="product-preview-button" data-woo-product-id="' + response.data.woo_product_id + '">' + previewText + '</a>';
                    imageContainer.prepend(previewButton);
                    VortemLogger.log("Preview button added for product:", productId);
                  }
                }
              }
            }
          },
          error: function (xhr, status, error) {
            VortemLogger.error("Status check error for", productId, ":", error);
          },
        });
      });
    }

    // Loading overlay functions
    var defaultProcessingText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.processing) ? vortem_admin.strings.processing : 'Processing...';
    var defaultSubmessageText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.please_wait_import) ? vortem_admin.strings.please_wait_import : 'Please wait while we import your product';
    
    var typewriterTimeouts = [];
    var currentLoadingType = 'normal';
    
    function showLoading(message = defaultProcessingText, submessage = defaultSubmessageText, items = null) {
      // Clear any existing typewriter timeouts
      typewriterTimeouts.forEach(function(timeout) {
        clearTimeout(timeout);
      });
      typewriterTimeouts = [];
      
      // Reset elements
      $('.vortem-loading-text').text('');
      $('.vortem-loading-subtext').text('').show();
      $('.vortem-loading-items').html('').hide();
      
      $('#vortem-loading-overlay').show();
      $('body').css('overflow', 'hidden');
      
      // Typewriter effect for main text
      var mainTextElement = $('.vortem-loading-text')[0];
      var charIndex = 0;
      
      function typeNextChar() {
        if (charIndex < message.length) {
          mainTextElement.textContent += message.charAt(charIndex);
          charIndex++;
          var timeout = setTimeout(typeNextChar, 50); // 50ms per character
          typewriterTimeouts.push(timeout);
        } else {
          // Main text typing complete
          if (items && items.length > 0) {
            // Hide subtext and show items one by one for SEO import
            var timeout = setTimeout(function() {
              $('.vortem-loading-subtext').fadeOut(300, function() {
                $('.vortem-loading-items').show();
                showItemsOneByOne(items, 0);
              });
            }, 500);
            typewriterTimeouts.push(timeout);
          } else {
            // Show subtext with typewriter for normal import
            var timeout = setTimeout(function() {
              typeSubtext(submessage);
            }, 300);
            typewriterTimeouts.push(timeout);
          }
        }
      }
      
      typeNextChar();
    }
    
    function typeSubtext(text) {
      var subtextElement = $('.vortem-loading-subtext')[0];
      subtextElement.textContent = '';
      var charIndex = 0;
      
      function typeNextChar() {
        if (charIndex < text.length) {
          subtextElement.textContent += text.charAt(charIndex);
          charIndex++;
          var timeout = setTimeout(typeNextChar, 40);
          typewriterTimeouts.push(timeout);
        }
      }
      
      typeNextChar();
    }
    
    function showItemsOneByOne(items, index) {
      if (index >= items.length) return;
      
      var itemHtml = '<li class="vortem-loading-item" style="opacity: 0;" data-completed="false">' + 
                     '<span class="vortem-loading-item-icon vortem-loading-item-pending">' +
                     '<span class="vortem-loading-spinner"></span>' +
                     '</span> ' + 
                     '<span class="vortem-loading-item-text">' + items[index] + '</span>' +
                     '</li>';
      
      $('.vortem-loading-items').append(itemHtml);
      
      var timeout = setTimeout(function() {
        $('.vortem-loading-items li').last().animate({ opacity: 1 }, 400);
        var nextTimeout = setTimeout(function() {
          showItemsOneByOne(items, index + 1);
        }, 600);
        typewriterTimeouts.push(nextTimeout);
      }, 100);
      typewriterTimeouts.push(timeout);
    }
    
    function showCompletionChecks() {
      return new Promise(function(resolve) {
        var items = $('.vortem-loading-items li');
        var currentIndex = 0;
        
        function showNextCheck() {
          if (currentIndex >= items.length) {
            resolve();
            return;
          }
          
          var item = $(items[currentIndex]);
          var icon = item.find('.vortem-loading-item-icon');
          
          // Remove pending state and spinner
          icon.removeClass('vortem-loading-item-pending');
          icon.find('.vortem-loading-spinner').remove();
          
          // Add completed state with green check
          icon.addClass('vortem-loading-item-completed');
          icon.html('✓');
          
          // Mark item as completed
          item.attr('data-completed', 'true');
          
          // Add bounce animation
          icon.addClass('vortem-check-bounce');
          
          currentIndex++;
          
          var timeout = setTimeout(function() {
            showNextCheck();
          }, 400); // 400ms between each check
          typewriterTimeouts.push(timeout);
        }
        
        // Start showing checks after a short delay
        var timeout = setTimeout(function() {
          showNextCheck();
        }, 500);
        typewriterTimeouts.push(timeout);
      });
    }

    function hideLoading() {
      // Clear all typewriter timeouts
      typewriterTimeouts.forEach(function(timeout) {
        clearTimeout(timeout);
      });
      typewriterTimeouts = [];
      
      $('#vortem-loading-overlay').hide();
      $('body').css('overflow', 'auto');
    }

    // Import product as draft handler - Opens modal first
    $(document).on("click", ".import-product-btn", function (e) {
      e.preventDefault();
      e.stopPropagation(); // Stop event from bubbling to other handlers
      e.stopImmediatePropagation(); // Stop other handlers on the same element
      
      VortemLogger.log("=== IMPORT AS DRAFT BUTTON CLICKED ===");
      VortemLogger.log("Button element:", this);
      VortemLogger.log("Button classes:", this.className);

      var button = $(this);

      // Check if button is already disabled to prevent duplicate clicks
      if (button.prop("disabled") || button.hasClass("importing")) {
        VortemLogger.log("Button already disabled, preventing duplicate click");
        return false;
      }

      var productId = button.data("product-id");
      VortemLogger.log("Product ID:", productId);

      if (!productId) {
        VortemLogger.error("Product ID not found");
        return false;
      }

      // Show import method selection modal
      showImportMethodModal(productId, button);
      
      return false; // Prevent default and stop propagation
    });

    // Show import method selection modal
    function showImportMethodModal(productId, button) {
      var modal = $("#import-method-modal");
      
      if (modal.length === 0) {
        // Fallback to normal import if modal doesn't exist
        VortemLogger.log("Modal not found, falling back to normal import");
        performImport(productId, button, "normal");
        return;
      }

      // Store product ID and button in modal data for later use
      modal.data("product-id", productId);
      modal.data("button", button);
      
      // Remove previous event handlers
      $("#normal-import-btn, #seo-import-btn, #cancel-import-method-btn, .vortem-modal-overlay").off("click");

      // Show modal
      modal.show();

      // Handle normal import button
      $("#normal-import-btn").on("click", function () {
        modal.hide();
        performImport(productId, button, "normal");
      });

      // Handle SEO import button
      $("#seo-import-btn").on("click", function () {
        modal.hide();
        performImport(productId, button, "seo");
      });

      // Handle cancel button and overlay
      $("#cancel-import-method-btn, .vortem-modal-overlay").on("click", function () {
        modal.hide();
      });
    }

    // SEO Import Success Modal with countdown
    function showSEOImportModal() {
      return new Promise(function(resolve) {
        var modal = document.getElementById('seo-import-modal');
        var okBtn = document.getElementById('seo-import-ok');
        var countdownEl = document.getElementById('seo-import-countdown');
        var backdrop = modal ? modal.querySelector('.modal-backdrop') : null;
        
        if (!modal) {
          resolve();
          return;
        }

        var countdown = 5;
        var countdownInterval = null;
        var autoCloseTimeout = null;
        var backdropHandler = null;
        
        var cleanup = function() {
          if (countdownInterval) clearInterval(countdownInterval);
          if (autoCloseTimeout) clearTimeout(autoCloseTimeout);
          modal.classList.remove('show');
          modal.setAttribute('aria-hidden', 'true');
          if (countdownEl) countdownEl.textContent = '';
          if (okBtn) okBtn.onclick = null;
          if (backdropHandler && modal) {
            modal.removeEventListener('click', backdropHandler);
            backdropHandler = null;
          }
        };

        var updateCountdown = function() {
          countdown--;
          
          // When countdown reaches 1, show it, then automatically trigger OK after 1 second
          if (countdown === 1) {
            if (countdownEl) {
              countdownEl.textContent = '(1)';
            }
            // Clear the interval since we'll handle the final trigger with setTimeout
            if (countdownInterval) {
              clearInterval(countdownInterval);
              countdownInterval = null;
            }
            // After 1 more second, automatically trigger OK action (as if user clicked OK)
            setTimeout(function() {
              cleanup();
              resolve();
            }, 1000);
            return;
          }
          
          // If countdown is still greater than 1, show the countdown
          if (countdown > 1) {
            if (countdownEl) {
              countdownEl.textContent = '(' + countdown + ')';
            }
          } else {
            // This should not happen, but as fallback
            cleanup();
            resolve();
          }
        };

        // Show initial countdown
        if (countdownEl) {
          countdownEl.textContent = '(' + countdown + ')';
        }
        
        // Update countdown every second
        countdownInterval = setInterval(updateCountdown, 1000);
        
        // Auto-close after 5 seconds (as fallback)
        autoCloseTimeout = setTimeout(function() {
          if (countdownInterval) clearInterval(countdownInterval);
          cleanup();
          resolve();
        }, 5000);

        // OK button handler
        if (okBtn) {
          okBtn.onclick = function() {
            cleanup();
            resolve();
          };
        }

        // Backdrop click handler
        backdropHandler = function(e) {
          if (e.target.classList.contains('modal-backdrop') && e.target.getAttribute('data-close') === 'seo-import-modal') {
            cleanup();
            resolve();
          }
        };
        if (modal) {
          modal.addEventListener('click', backdropHandler);
        }

        // Show modal
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
      });
    }

    function performImport(productId, button, importType) {
      var card = button.closest(".vortem-product-card");
      var originalText = button.text();

      VortemLogger.log("Starting import - Product ID:", productId, "Type:", importType);

      // Immediately mark as importing and disable to prevent duplicate clicks
      var importingText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.importing) ? vortem_admin.strings.importing : 'Importing...';
      button.addClass("importing").prop("disabled", true).text(importingText);
      VortemLogger.log('Button disabled, showing "Importing..."');

      // Show loading overlay with appropriate text based on import type
      var importingDraftText, willCreateText, seoItems = null;
      if (importType === "seo") {
        importingDraftText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.importing_product_seo) ? vortem_admin.strings.importing_product_seo : 'Importing product with SEO optimization...';
        willCreateText = ''; // Not used for SEO import
        
        // Build SEO items array
        seoItems = [
          (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.seo_item_keyphrase) ? vortem_admin.strings.seo_item_keyphrase : 'Keyphrase',
          (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.seo_item_seo_title) ? vortem_admin.strings.seo_item_seo_title : 'SEO Title',
          (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.seo_item_seo_description) ? vortem_admin.strings.seo_item_seo_description : 'SEO Description',
          (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.seo_item_meta_description) ? vortem_admin.strings.seo_item_meta_description : 'Meta Description',
          (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.seo_item_tags) ? vortem_admin.strings.seo_item_tags : 'Tags',
          (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.seo_item_meta_title) ? vortem_admin.strings.seo_item_meta_title : 'Meta Title',
          (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.seo_item_headings) ? vortem_admin.strings.seo_item_headings : 'Headings'
        ];
      } else {
        importingDraftText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.importing_product_draft) ? vortem_admin.strings.importing_product_draft : 'Importing product as draft...';
        willCreateText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.will_create_product) ? vortem_admin.strings.will_create_product : 'This will create the product in WooCommerce';
      }
      showLoading(importingDraftText, willCreateText, seoItems);

      $.ajax({
        url: vortem_admin.ajax_url,
        type: "POST",
        dataType: "json",
        data: {
          action: "vortem_import_single_product",
          nonce: vortem_admin.nonce,
          product_id: productId,
          import_type: importType,
        },
        beforeSend: function () {
          VortemLogger.log("Sending import AJAX request for product:", productId, "Type:", importType);
        },
        success: function (response) {
          VortemLogger.log("Import product response:", response);
          if (response.success) {
            VortemLogger.log("Import successful, updating UI");
            
            // For SEO imports, show completion checks before hiding loading
            if (importType === "seo") {
              showCompletionChecks().then(function() {
                // After all checks are shown, hide loading and continue
                hideLoading();
                handleSuccessfulImport();
              });
            } else {
              // For normal imports, hide loading immediately
              hideLoading();
              handleSuccessfulImport();
            }
            
            function handleSuccessfulImport() {
              var successMessage = importType === "seo" 
                ? "Product imported with SEO optimization successfully!"
                : "Product imported as draft successfully!";
              showVortemMessage(successMessage, "success");

            // Update notice-info in vortem-page-content
            updateProductImportNotice(productId, true);

            // Hide import button and show delete button
            VortemLogger.log("Changing button from import to delete");
            button.removeClass("importing").hide();
            var deleteBtn = button.siblings(".delete-product-btn");
            if (deleteBtn.length) {
              deleteBtn.show().prop("disabled", false);
            }

            VortemLogger.log("Button updated, showing delete button");

            // Add preview button if product was imported successfully
            if (response.data && response.data.woo_product_id) {
              var wooProductId = response.data.woo_product_id;
              var previewUrl = (typeof vortem_admin !== 'undefined' && vortem_admin.site_url) ? vortem_admin.site_url + '?post_type=product&p=' + wooProductId + '&preview=true' : '#';
              var previewText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.preview) ? vortem_admin.strings.preview : 'Preview';
              
              var imageContainer = card.find('.product-image-container');
              if (imageContainer.length) {
                // Check if preview button already exists
                var existingPreview = imageContainer.find('.product-preview-button');
                if (existingPreview.length === 0) {
                  // Add preview button
                  var previewButton = '<a href="' + previewUrl + '" target="_blank" class="product-preview-button" data-woo-product-id="' + wooProductId + '">' + previewText + '</a>';
                  imageContainer.prepend(previewButton);
                  VortemLogger.log("Preview button added");
                }
              }
            }

            // Update status badge
            var statusBadge = card.find(".product-status-badge");
            VortemLogger.log("Status badge:", statusBadge);
            var addedText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.added) ? vortem_admin.strings.added : 'Added';
            if (statusBadge.length) {
              statusBadge.removeClass("status-new").addClass("status-added").text(addedText).css({
                background: "rgb(70, 180, 80)",
                color: "white",
                display: "block",
              });
              VortemLogger.log("Status badge updated");
            } else {
              VortemLogger.log("Status badge not found");
            }

            // Removed auto-refresh after import to prevent page reload
            VortemLogger.log("Import completed successfully, UI updated");
            
            // Store import info in sessionStorage to check status when user returns
            if (response.data && response.data.woo_product_id) {
              try {
                var importedProducts = JSON.parse(sessionStorage.getItem('vortem_imported_products') || '[]');
                importedProducts.push({
                  product_id: productId,
                  woo_product_id: response.data.woo_product_id,
                  timestamp: Date.now()
                });
                sessionStorage.setItem('vortem_imported_products', JSON.stringify(importedProducts));
              } catch (e) {
                VortemLogger.error("Failed to store import info:", e);
              }
            }
            
            // Re-check import status for all products on the page to ensure UI is updated
            var allProducts = [];
            $('.vortem-product-card').each(function() {
              var card = $(this);
              var cardProductId = card.data('product-id');
              if (cardProductId) {
                allProducts.push({ product_id: cardProductId, sku: cardProductId });
              }
            });
            
            if (allProducts.length > 0) {
              VortemLogger.log("Re-checking import status for all products after import");
              checkProductsImportStatus(allProducts);
            }
            
            // For SEO imports, show modal before redirecting
            if (importType === "seo") {
              var editUrl = null;
              if (response.data && response.data.edit_url) {
                editUrl = response.data.edit_url;
              } else if (response.data && response.data.woo_product_id) {
                var adminUrl = (typeof vortem_admin !== 'undefined' && vortem_admin.ajax_url) 
                  ? vortem_admin.ajax_url.replace('admin-ajax.php', 'post.php')
                  : window.location.origin + '/wp-admin/post.php';
                editUrl = adminUrl + '?post=' + response.data.woo_product_id + '&action=edit';
              }
              
              if (editUrl) {
                // Show SEO import success modal, then redirect
                showSEOImportModal().then(function() {
                  VortemLogger.log("Redirecting to product edit page:", editUrl);
                  window.location.href = editUrl;
                });
              } else {
                // Fallback: redirect immediately if no edit URL
                if (response.data && response.data.edit_url) {
                  window.location.href = response.data.edit_url;
                } else if (response.data && response.data.woo_product_id) {
                  var adminUrl = (typeof vortem_admin !== 'undefined' && vortem_admin.ajax_url) 
                    ? vortem_admin.ajax_url.replace('admin-ajax.php', 'post.php')
                    : window.location.origin + '/wp-admin/post.php';
                  var editUrl = adminUrl + '?post=' + response.data.woo_product_id + '&action=edit';
                  window.location.href = editUrl;
                }
              }
            } else {
              // For non-SEO imports, redirect immediately
              if (response.data && response.data.edit_url) {
                VortemLogger.log("Redirecting to product edit page:", response.data.edit_url);
                window.location.href = response.data.edit_url;
              } else if (response.data && response.data.woo_product_id) {
                var adminUrl = (typeof vortem_admin !== 'undefined' && vortem_admin.ajax_url) 
                  ? vortem_admin.ajax_url.replace('admin-ajax.php', 'post.php')
                  : window.location.origin + '/wp-admin/post.php';
                var editUrl = adminUrl + '?post=' + response.data.woo_product_id + '&action=edit';
                VortemLogger.log("Redirecting to product edit page (fallback):", editUrl);
                window.location.href = editUrl;
              }
            }
            } // End of handleSuccessfulImport
          } else {
            hideLoading();
            VortemLogger.log("Import failed:", response.data);
            showVortemMessage(
              "Failed to import product: " +
                (response.data.message || "Unknown error"),
              "error"
            );

            // Update notice-info for failed import
            updateProductImportNotice(
              productId,
              false,
              response.data.message || "Unknown error"
            );

            button
              .removeClass("importing")
              .prop("disabled", false)
              .text(originalText);
          }
        },
        error: function (xhr, status, error) {
          hideLoading();
          VortemLogger.error("Import product error:", xhr.responseText);
          VortemLogger.error("Error details:", status, error);
          showVortemMessage("Failed to import product: " + error, "error");
          button
            .removeClass("importing")
            .prop("disabled", false)
            .text(originalText);
        },
      });
    }

    // Show delete confirmation modal
    function showDeleteConfirmationModal(message, callback) {
      var modal = $("#delete-product-modal");
      var modalMessage = $("#delete-product-modal-message");
      
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
      $("#confirm-delete-product-btn, #cancel-delete-product-btn, .vortem-modal-overlay").off("click");

      // Handle confirm button
      $("#confirm-delete-product-btn").on("click", function () {
        modal.hide();
        callback();
      });

      // Handle cancel button and overlay
      $("#cancel-delete-product-btn, .vortem-modal-overlay").on("click", function () {
        modal.hide();
      });
    }

    // Delete product handler
    $(document).on("click", ".delete-product-btn", function (e) {
      e.preventDefault();
      VortemLogger.log("=== DELETE PRODUCT BUTTON CLICKED ===");
      VortemLogger.log("Button element:", this);
      VortemLogger.log("Button classes:", this.className);

      var button = $(this);

      // Check if button is already disabled to prevent duplicate clicks
      if (button.prop("disabled") || button.hasClass("deleting")) {
        VortemLogger.log("Button already disabled, preventing duplicate click");
        return;
      }

      var productId = button.data("product-id");
      var card = button.closest(".vortem-product-card");
      var originalText = button.html();

      VortemLogger.log("Product ID:", productId);
      VortemLogger.log("Card element:", card);

      var deleteMessage = "Are you sure you want to delete this product from WordPress?";
      if (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.delete_product_confirmation) {
        deleteMessage = vortem_admin.strings.delete_product_confirmation;
      }

      // Show modal instead of confirm
      showDeleteConfirmationModal(deleteMessage, function () {
        VortemLogger.log("User confirmed delete");

        // Immediately mark as deleting and disable to prevent duplicate clicks
        var deletingText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.deleting) ? vortem_admin.strings.deleting : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 4px; animation: spin 1s linear infinite;"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-dasharray="31.416" stroke-dashoffset="23.562" opacity="0.9"/></svg> Deleting...';
        button.addClass("deleting").prop("disabled", true).html(deletingText);
        VortemLogger.log('Button disabled, showing "Deleting..."');

        $.ajax({
          url: vortem_admin.ajax_url,
          type: "POST",
          dataType: "json",
          data: {
            action: "vortem_delete_single_product",
            nonce: vortem_admin.nonce,
            product_id: productId,
          },
          beforeSend: function () {
            VortemLogger.log("Sending delete AJAX request for product:", productId);
          },
          success: function (response) {
            VortemLogger.log("Delete product response:", response);
            if (response.success) {
              VortemLogger.log("Delete successful, updating UI");
              var deletedMsg = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.product_deleted_successfully) ? vortem_admin.strings.product_deleted_successfully : 'Product deleted successfully!';
              showVortemMessage(deletedMsg, "success");

              // Hide delete button and show import button
              VortemLogger.log("Changing button from delete to import");
              button.removeClass("deleting").hide();
              var importBtn = button.siblings(".import-product-btn");
              if (importBtn.length) {
                // Reset button state - remove importing class and set correct text
                importBtn.removeClass("importing").prop("disabled", false);
                var importText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.import) ? vortem_admin.strings.import : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 4px;"><path d="M12 15V3M12 15L8 11M12 15L16 11M2 17L2 19C2 20.1046 2.89543 21 4 21L20 21C21.1046 21 22 20.1046 22 19V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Import';
                importBtn.html(importText);
                importBtn.show();
              }

              VortemLogger.log("Button updated, showing import button");

              // Remove preview button
              var previewButton = card.find(".product-preview-button");
              if (previewButton.length) {
                previewButton.remove();
                VortemLogger.log("Preview button removed");
              }

              // Reset status badge
              var statusBadge = card.find(".product-status-badge");
              VortemLogger.log("Status badge:", statusBadge);
              var newText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.new) ? vortem_admin.strings.new : 'NEW';
              if (statusBadge.length) {
                statusBadge.removeClass("status-added").addClass("status-new").text(newText).css({
                  background: "rgb(0, 115, 170)",
                  color: "white",
                  display: "block",
                });
                VortemLogger.log("Status badge reset to NEW");
              }
            } else {
              VortemLogger.log("Delete failed:", response.data);
              showVortemMessage(
                "Failed to delete product: " +
                  (response.data.message || "Unknown error"),
                "error"
              );
              var deleteText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.delete) ? vortem_admin.strings.delete : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 4px;"><path d="M3 6H5H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 6V4C8 3.46957 8.21071 2.96086 8.58579 2.58579C8.96086 2.21071 9.46957 2 10 2H14C14.5304 2 15.0391 2.21071 15.4142 2.58579C15.7893 2.96086 16 3.46957 16 4V6M19 6V20C19 20.5304 18.7893 21.0391 18.4142 21.4142C18.0391 21.7893 17.5304 22 17 22H7C6.46957 22 5.96086 21.7893 5.58579 21.4142C5.21071 21.0391 5 20.5304 5 20V6H19Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 11V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 11V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Delete';
              button
                .removeClass("deleting")
                .prop("disabled", false)
                .html(deleteText);
            }
          },
          error: function (xhr, status, error) {
            VortemLogger.error("Delete product error:", xhr.responseText);
            VortemLogger.error("Error details:", status, error);
            showVortemMessage("Failed to delete product: " + error, "error");
            var deleteText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.delete) ? vortem_admin.strings.delete : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 4px;"><path d="M3 6H5H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 6V4C8 3.46957 8.21071 2.96086 8.58579 2.58579C8.96086 2.21071 9.46957 2 10 2H14C14.5304 2 15.0391 2.21071 15.4142 2.58579C15.7893 2.96086 16 3.46957 16 4V6M19 6V20C19 20.5304 18.7893 21.0391 18.4142 21.4142C18.0391 21.7893 17.5304 22 17 22H7C6.46957 22 5.96086 21.7893 5.58579 21.4142C5.21071 21.0391 5 20.5304 5 20V6H19Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 11V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 11V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Delete';
            button
              .removeClass("deleting")
              .prop("disabled", false)
              .html(deleteText);
          },
        });
      });
    });

    // Simple toast function as fallback
    function showSimpleToast(message, type) {
      type = type || 'warning';
      
      // Create toast element
      var $toast = $('<div class="vortem-simple-toast"></div>');
      $toast.css({
        'position': 'fixed',
        'bottom': '24px',
        'right': '24px',
        'padding': '16px 20px',
        'border-radius': '12px',
        'background': type === 'warning' ? '#fffbeb' : (type === 'error' ? '#fef2f2' : '#ecfdf5'),
        'border': '2px solid ' + (type === 'warning' ? '#f59e0b' : (type === 'error' ? '#ef4444' : '#10b981')),
        'box-shadow': '0 10px 40px rgba(0,0,0,0.15)',
        'z-index': '100010',
        'max-width': '400px',
        'min-width': '300px',
        'font-size': '14px',
        'line-height': '1.5',
        'color': type === 'warning' ? '#b45309' : (type === 'error' ? '#b91c1c' : '#047857'),
        'opacity': '0',
        'transform': 'translateY(20px) scale(0.95)',
        'transition': 'all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1)',
        'font-family': '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif'
      });
      
      $toast.html('<div style="display: flex; align-items: flex-start; gap: 12px;">' +
        '<div style="flex-shrink: 0; color: ' + (type === 'warning' ? '#f59e0b' : (type === 'error' ? '#ef4444' : '#10b981')) + ';">' +
        (type === 'warning' ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>' : 
         type === 'error' ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>' :
         '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>') +
        '</div>' +
        '<div style="flex: 1;">' +
        '<div style="font-size: 14px; font-weight: 600; margin-bottom: 2px;">vortem.ai</div>' +
        '<div style="font-size: 13px; color: #64748b; line-height: 1.5;">' + message + '</div>' +
        '</div>' +
        '<button onclick="this.parentElement.parentElement.remove()" style="flex-shrink: 0; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; border: none; background: transparent; color: #94a3b8; cursor: pointer; border-radius: 6px;">' +
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
        '</button>' +
        '</div>');
      
      $('body').append($toast);
      
      // Animate in
      setTimeout(function() {
        $toast.css({
          'opacity': '1',
          'transform': 'translateY(0) scale(1)'
        });
      }, 10);
      
      // Auto-hide after 5 seconds
      setTimeout(function() {
        $toast.css({
          'opacity': '0',
          'transform': 'translateY(20px) scale(0.95)'
        });
        setTimeout(function() {
          $toast.remove();
        }, 300);
      }, 5000);
    }

    // Log ready message
    VortemLogger.log("=== VORTEM BUTTONS READY ===");
    VortemLogger.log("Buttons initialized successfully");

    // Check if buttons exist
    setTimeout(function () {
      VortemLogger.log("Button check:");
      VortemLogger.log(
        "- Validate endpoint:",
        $("#validate-endpoint").length ? "Found" : "NOT FOUND"
      );
      VortemLogger.log(
        "- Fetch products:",
        $("#fetch-products").length ? "Found" : "NOT FOUND"
      );
    }, 500);
  });
})(jQuery);
