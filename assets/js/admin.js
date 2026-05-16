/**
 * Vortem Admin JavaScript
 *
 * External Libraries Used:
 * - jQuery (jQuery Foundation) - https://jquery.com/ | License: MIT | WordPress-bundled library used for DOM manipulation, AJAX, and event handling
 */

jQuery(document).ready(function ($) {
  "use strict";

  // Debug: Log that admin.js is loaded
  VortemLogger.log("=== VORTEM ADMIN JS LOADED ===");
  VortemLogger.log("Timestamp:", new Date().toISOString());
  VortemLogger.log("Current URL:", window.location.href);
  VortemLogger.log("Script version:", "VORTEM VERSION");
  VortemLogger.log(
    "Script file:",
    document.currentScript ? document.currentScript.src : "unknown"
  );
  VortemLogger.log("AJAX URL:", vortem_admin ? vortem_admin.ajax_url : "UNDEFINED");
  VortemLogger.log("Nonce:", vortem_admin ? vortem_admin.nonce : "UNDEFINED");
  VortemLogger.log("vortem_admin object:", vortem_admin);

  // Check for conflicting scripts
  VortemLogger.log("=== CHECKING FOR CONFLICTING SCRIPTS ===");
  $('script[src*="admin.js"]').each(function (index) {
    VortemLogger.log("Admin.js script " + index + ":", $(this).attr("src"));
  });

  // Create isolated AJAX handler for Vortem to avoid conflicts
  VortemLogger.log("=== CREATING ISOLATED AJAX HANDLER ===");

  // Store original jQuery AJAX
  var originalJQueryAjax = $.ajax;

  // Create a safe AJAX wrapper specifically for Vortem
  window.vortemAjax = function (options) {
    VortemLogger.log("Vortem AJAX called with options:", options);

    // Ensure we're using the original jQuery AJAX, not any overridden version
    return originalJQueryAjax.call($, options);
  };

  VortemLogger.log("Vortem AJAX handler created:", typeof window.vortemAjax);
  VortemLogger.log(
    "Testing vortemAjax availability:",
    window.vortemAjax ? "Available" : "NOT AVAILABLE"
  );

  // Suppress third-party AJAX errors that aren't ours
  $(document).ajaxError(function (event, jqXHR, ajaxSettings, thrownError) {
    // Only log errors from non-Vortem requests to reduce console noise
    if (ajaxSettings.data && typeof ajaxSettings.data === "string") {
      if (ajaxSettings.data.indexOf("vortem_") === -1) {
        // Suppress non-Vortem AJAX errors
        VortemLogger.log("Suppressed non-Vortem AJAX error");
        return;
      }
    }

    VortemLogger.log("Vortem AJAX Error:", {
      url: ajaxSettings.url,
      status: jqXHR.status,
      error: thrownError,
      response: jqXHR.responseText,
    });
  });

  // Debug: Check if buttons exist
  VortemLogger.log("=== BUTTON EXISTENCE CHECK ===");
  VortemLogger.log(
    "Validate endpoint button exists:",
    $("#validate-endpoint").length
  );
  VortemLogger.log("Fetch products button exists:", $("#fetch-products").length);
  VortemLogger.log("Import products button exists:", $("#import-products").length);
  VortemLogger.log("Test import button exists:", $("#test-import").length);
  VortemLogger.log("Sync products button exists:", $("#sync-products").length);
  VortemLogger.log("Refresh auth button exists:", $("#refresh-auth").length);

  // Debug: Log all buttons on the page
  VortemLogger.log("=== ALL BUTTONS ON PAGE ===");
  $("button").each(function (index) {
    VortemLogger.log("Button " + index + ":", {
      id: $(this).attr("id"),
      class: $(this).attr("class"),
      text: $(this).text().trim(),
      visible: $(this).is(":visible"),
      enabled: !$(this).prop("disabled"),
    });
  });

  // Debug: Show alert if on products page
  if (window.location.href.indexOf("vortem-products") !== -1) {
    VortemLogger.log("=== ON VORTEM PRODUCTS PAGE ===");
    VortemLogger.log("Products page detected - JavaScript loaded successfully");

    // Additional debugging for products page
    setTimeout(function () {
      VortemLogger.log("=== DELAYED BUTTON CHECK (after 1 second) ===");
      VortemLogger.log(
        "Validate endpoint button:",
        $("#validate-endpoint").length,
        $("#validate-endpoint").is(":visible")
      );
      VortemLogger.log(
        "Fetch products button:",
        $("#fetch-products").length,
        $("#fetch-products").is(":visible")
      );
    }, 1000);
  }

  // Note: Removed duplicate delegated event handlers to prevent conflicts
  // Direct event handlers are used instead for better performance and clarity

  // Auto-dismiss toast notifications after 8 seconds
  setTimeout(function () {
    $(".vortem-toast").fadeOut(1000, function () {
      $(this).remove();
    });
  }, 8000);

  // Enhanced dismiss functionality for toast notifications
  $(document).on("click", ".vortem-toast .notice-dismiss", function () {
    $(this)
      .closest(".vortem-toast")
      .fadeOut(500, function () {
        $(this).remove();
      });
  });

  // Sync Products
  $("#sync-products").on("click", function () {
    var button = $(this);
    var originalText = button.text();

    VortemLogger.log("Sync button clicked");
    VortemLogger.log("AJAX URL:", vortem_admin.ajax_url);
    VortemLogger.log("Nonce:", vortem_admin.nonce_sync);

    button.prop("disabled", true).text(vortem_admin.strings.sync_started);

    $.ajax({
      url: vortem_admin.ajax_url,
      type: "POST",
      data: {
        action: "vortem_sync_products",
        nonce: vortem_admin.nonce_sync,
        force_sync: false,
      },
      success: function (response) {
        if (response.success) {
          // Check if there's a specific message from the API
          var message =
            response.data && response.data.message
              ? response.data.message
              : vortem_admin.strings.sync_completed;
          showMessage(message, "success");
          // Refresh page after 2 seconds
          setTimeout(function () {
            location.reload();
          }, 2000);
        } else {
          // Show the specific error message from the API
          var errorMessage =
            response.data && response.data.message
              ? response.data.message
              : vortem_admin.strings.sync_failed;
          showMessage(errorMessage, "error");
        }
      },
      error: function (xhr, status, error) {
        VortemLogger.log("AJAX Error:", status, error);
        VortemLogger.log("Response:", xhr.responseText);
        showMessage(vortem_admin.strings.sync_failed, "error");
      },
      complete: function () {
        button.prop("disabled", false).text(originalText);
      },
    });
  });

  // Force Sync
  $("#force-sync").on("click", function () {
    var button = $(this);
    var originalText = button.text();

    button.prop("disabled", true).text(vortem_admin.strings.sync_started);

    $.ajax({
      url: vortem_admin.ajax_url,
      type: "POST",
      data: {
        action: "vortem_sync_products",
        nonce: vortem_admin.nonce_sync,
        force_sync: true,
      },
      success: function (response) {
        if (response.success) {
          // Check if there's a specific message from the API
          var message =
            response.data && response.data.message
              ? response.data.message
              : vortem_admin.strings.sync_completed;
          showMessage(message, "success");
          setTimeout(function () {
            location.reload();
          }, 2000);
        } else {
          // Show the specific error message from the API
          var errorMessage =
            response.data && response.data.message
              ? response.data.message
              : vortem_admin.strings.sync_failed;
          showMessage(errorMessage, "error");
        }
      },
      error: function (xhr, status, error) {
        VortemLogger.log("AJAX Error:", status, error);
        VortemLogger.log("Response:", xhr.responseText);
        showMessage(vortem_admin.strings.sync_failed, "error");
      },
      complete: function () {
        button.prop("disabled", false).text(originalText);
      },
    });
  });

  // Reset Sync Status
  $("#reset-sync").on("click", function () {
    if (!confirm(vortem_admin.strings.confirm_reset)) {
      return;
    }

    var button = $(this);
    var originalText = button.text();

    button.prop("disabled", true).text("Resetting...");

    $.ajax({
      url: vortem_admin.ajax_url,
      type: "POST",
      data: {
        action: "vortem_reset_sync_status",
        nonce: vortem_admin.nonce,
      },
      success: function (response) {
        if (response.success) {
          showMessage("Sync status reset successfully!", "success");
          setTimeout(function () {
            location.reload();
          }, 2000);
        } else {
          showMessage(response.data || "Failed to reset sync status", "error");
        }
      },
      error: function () {
        showMessage("Failed to reset sync status", "error");
      },
      complete: function () {
        button.prop("disabled", false).text(originalText);
      },
    });
  });

  // Refresh Auth
  $("#refresh-auth").on("click", function () {
    var button = $(this);
    var originalText = button.text();

    button.prop("disabled", true).text("Refreshing...");

    $.ajax({
      url: vortem_admin.ajax_url,
      type: "POST",
      data: {
        action: "vortem_refresh_auth",
        nonce: vortem_admin.nonce,
      },
      success: function (response) {
        if (response.success) {
          var authData = response.data;
          if (authData.valid) {
            showMessage(
              "Authentication refreshed successfully! Plan: " + authData.plan,
              "success"
            );
          } else {
            showMessage(
              "Authentication failed: " +
                (authData.error || "Unknown error"),
              "error"
            );
          }
          setTimeout(function () {
            location.reload();
          }, 2000);
        } else {
          showMessage(response.data || "Failed to refresh authentication", "error");
        }
      },
      error: function () {
        showMessage("Failed to refresh authentication", "error");
      },
      complete: function () {
        button.prop("disabled", false).text(originalText);
      },
    });
  });

  // Sync Orders
  $("#sync-orders").on("click", function () {
    var button = $(this);
    var originalText = button.text();

    button.prop("disabled", true).text("Syncing...");

    $.ajax({
      url: vortem_admin.ajax_url,
      type: "POST",
      data: {
        action: "vortem_sync_orders",
        nonce: vortem_admin.nonce,
      },
      success: function (response) {
        if (response.success) {
          showMessage("Orders synced successfully!", "success");
          setTimeout(function () {
            location.reload();
          }, 2000);
        } else {
          showMessage(response.data || "Failed to sync orders", "error");
        }
      },
      error: function () {
        showMessage("Failed to sync orders", "error");
      },
      complete: function () {
        button.prop("disabled", false).text(originalText);
      },
    });
  });

  // Process Pending Orders
  $("#process-pending").on("click", function () {
    var button = $(this);
    var originalText = button.text();

    button.prop("disabled", true).text("Processing...");

    $.ajax({
      url: vortem_admin.ajax_url,
      type: "POST",
      data: {
        action: "vortem_process_pending_orders",
        nonce: vortem_admin.nonce,
      },
      success: function (response) {
        if (response.success) {
          showMessage("Pending orders processed successfully!", "success");
          setTimeout(function () {
            location.reload();
          }, 2000);
        } else {
          showMessage(
            response.data || "Failed to process pending orders",
            "error"
          );
        }
      },
      error: function () {
        showMessage("Failed to process pending orders", "error");
      },
      complete: function () {
        button.prop("disabled", false).text(originalText);
      },
    });
  });


  /**
   * Show custom notice in the same format as PHP show_custom_notice
   * 
   * @param {string} message - HTML message to display
   * @param {string} type - Notice type: 'success', 'error', 'warning', 'info'
   */
  function showCustomNotice(message, type) {
    type = type || 'info';
    
    // Remove existing validation notices (error/success) but preserve the "Configure your Vortem.ai settings below" notice
    $('.vortem-custom-notice').filter(function() {
      var text = $(this).text();
      // Remove notices that don't contain "Configure your Vortem.ai settings below" (preserve that one)
      return text.indexOf('Configure your Vortem.ai settings below') === -1;
    }).remove();
    
    var noticeClass = 'vortem-custom-notice vortem-plugin-notice vortem-notice-' + type;
    var noticeHtml = '<div class="' + noticeClass + '"><p>' + message + '</p></div>';
    
    // Find the vortem-page-content container
    var $pageContent = $('.vortem-page-content');
    
    if ($pageContent.length > 0) {
      // Find the "Configure your Vortem.ai settings below" notice by text content
      var $configureNotice = $pageContent.find('.vortem-custom-notice').filter(function() {
        return $(this).text().indexOf('Configure your Vortem.ai settings below') !== -1;
      });
      
      if ($configureNotice.length > 0) {
        // Insert notice after the "Configure your Vortem.ai settings below" notice
        $configureNotice.after(noticeHtml);
      } else {
        // Fallback: find the settings form and insert before it
        var $form = $pageContent.find('#vortem-settings-form');
        if ($form.length > 0) {
          // Insert notice before the form (after header and all PHP notices)
          $form.before(noticeHtml);
        } else {
          // Fallback: find modern-header and insert after it
          var $header = $pageContent.find('.modern-header');
          if ($header.length > 0) {
            $header.after(noticeHtml);
          } else {
            // Last fallback: check for existing notices and insert after them
            var $existingNotices = $pageContent.find('.vortem-custom-notice');
            if ($existingNotices.length > 0) {
              $existingNotices.last().after(noticeHtml);
            } else {
              // Last fallback: prepend to page content
              $pageContent.prepend(noticeHtml);
            }
          }
        }
      }
    } else {
      // Fallback: prepend to wrap or body
      var $container = $('.wrap').length > 0 ? $('.wrap') : $('body');
      $container.prepend(noticeHtml);
    }
    
    // Scroll to notice
    $('html, body').animate({
      scrollTop: $('.vortem-custom-notice').first().offset().top - 20
    }, 300);
  }


  // Show message function
  function showMessage(message, type) {
    VortemLogger.log("=== SHOWING MESSAGE ===");
    VortemLogger.log("Message:", message);
    VortemLogger.log("Type:", type);

    var messageClass = type === "success" ? "vortem-success" : "vortem-error";
    var messageHtml =
      '<div class="notice notice-' +
      type +
      ' is-dismissible vortem-plugin-notice"><p class="' +
      messageClass +
      '">' +
      message +
      "</p></div>";

    VortemLogger.log("Message HTML:", messageHtml);
    VortemLogger.log("Target element:", $(".wrap h1"));
    VortemLogger.log("Target element exists:", $(".wrap h1").length);

    // Add message to page content area instead of after h1
    $(".vortem-page-content").prepend(messageHtml);

    VortemLogger.log("Message added to DOM");
  }

  // View Product Details
  $(document).on("click", ".view-details", function () {
    var sku = $(this).data("sku");

    $.ajax({
      url: vortem_admin.ajax_url,
      type: "POST",
      data: {
        action: "vortem_get_product_details",
        nonce: vortem_admin.nonce,
        sku: sku,
      },
      success: function (response) {
        if (response.success) {
          showProductModal(response.data, "View Product");
        } else {
          showMessage(
            response.data || "Failed to load product details",
            "error"
          );
        }
      },
      error: function () {
        showMessage("Failed to load product details", "error");
      },
    });
  });

  // Edit Product
  $(document).on("click", ".edit-product", function () {
    var sku = $(this).data("sku");

    $.ajax({
      url: vortem_admin.ajax_url,
      type: "POST",
      data: {
        action: "vortem_edit_product",
        nonce: vortem_admin.nonce,
        sku: sku,
      },
      success: function (response) {
        if (response.success) {
          showProductModal(response.data, "Edit Product");
        } else {
          showMessage(
            response.data || "Failed to load product for editing",
            "error"
          );
        }
      },
      error: function () {
        showMessage("Failed to load product for editing", "error");
      },
    });
  });

  // Save Product Changes
  $(document).on("click", ".save-product", function () {
    var sku = $(this).data("sku");
    var formData = {
      action: "vortem_save_product",
      nonce: vortem_admin.nonce,
      sku: sku,
      name: $("#product_name").val(),
      category: $("#product_category").val(),
      price: $("#product_price").val(),
      sync_status: $("#product_sync_status").val(),
      description: $("#product_description").val(),
    };

    $.ajax({
      url: vortem_admin.ajax_url,
      type: "POST",
      data: formData,
      success: function (response) {
        if (response.success) {
          showMessage("Product updated successfully!", "success");
          closeProductModal();
          // Refresh the page to show updated data
          setTimeout(function () {
            location.reload();
          }, 1500);
        } else {
          showMessage(response.data || "Failed to update product", "error");
        }
      },
      error: function () {
        showMessage("Failed to update product", "error");
      },
    });
  });

  // Cancel Edit
  $(document).on("click", ".cancel-edit", function () {
    closeProductModal();
  });

  // Show Product Modal
  function showProductModal(content, title) {
    var modalHtml = '<div id="vortem-product-modal" class="vortem-modal">';
    modalHtml += '<div class="vortem-modal-content">';
    modalHtml += '<div class="vortem-modal-header">';
    modalHtml += "<h2>" + title + "</h2>";
    modalHtml += '<span class="vortem-modal-close">&times;</span>';
    modalHtml += "</div>";
    modalHtml += '<div class="vortem-modal-body">';
    modalHtml += content;
    modalHtml += "</div>";
    modalHtml += "</div>";
    modalHtml += "</div>";

    $("body").append(modalHtml);
    $("#vortem-product-modal").fadeIn();
  }

  // Close Product Modal
  function closeProductModal() {
    $("#vortem-product-modal").fadeOut(function () {
      $(this).remove();
    });
  }

  // Close modal when clicking outside or on close button
  $(document).on("click", ".vortem-modal-close, .vortem-modal", function (e) {
    if (e.target === this) {
      closeProductModal();
    }
  });

  // Prevent modal content clicks from closing modal
  $(document).on("click", ".vortem-modal-content", function (e) {
    e.stopPropagation();
  });

  // Validate Endpoint Button
  $("#validate-endpoint").on("click", function (e) {
    VortemLogger.log("=== VALIDATE ENDPOINT CLICKED (DIRECT) ===");
    VortemLogger.log("Event:", e);
    VortemLogger.log("Button element:", this);
    VortemLogger.log("Button jQuery object:", $(this));
    VortemLogger.log("Button text:", $(this).text());
    VortemLogger.log("Button disabled:", $(this).prop("disabled"));
    VortemLogger.log("vortem_admin object:", vortem_admin);
    VortemLogger.log(
      "AJAX URL:",
      vortem_admin ? vortem_admin.ajax_url : "UNDEFINED"
    );
    VortemLogger.log("Nonce:", vortem_admin ? vortem_admin.nonce : "UNDEFINED");

    var button = $(this);
    var originalText = button.text();

    VortemLogger.log("Original button text:", originalText);
    VortemLogger.log("Disabling button...");
    button.prop("disabled", true).text("Validating...");

    VortemLogger.log("Sending AJAX request...");

    // Validate required data before sending
    if (!vortem_admin || !vortem_admin.ajax_url) {
      VortemLogger.error("vortem_admin object or ajax_url is missing!");
      showMessage("❌ Configuration error: AJAX URL not found", "error");
      button.prop("disabled", false).text(originalText);
      return;
    }

    if (!vortem_admin.nonce) {
      VortemLogger.error("Nonce is missing!");
      showMessage("❌ Configuration error: Security nonce not found", "error");
      button.prop("disabled", false).text(originalText);
      return;
    }

    vortemAjax({
      url: vortem_admin.ajax_url,
      type: "POST",
      data: {
        action: "vortem_validate_endpoint",
        nonce: vortem_admin.nonce,
      },
      beforeSend: function () {
        VortemLogger.log("Vortem AJAX request sent to:", vortem_admin.ajax_url);
        VortemLogger.log("Request data:", {
          action: "vortem_validate_endpoint",
          nonce: vortem_admin.nonce,
        });
      },
      success: function (response) {
        VortemLogger.log("=== VORTEM AJAX SUCCESS ===");
        VortemLogger.log("Full response:", response);
        VortemLogger.log("Response success:", response.success);
        VortemLogger.log("Response data:", response.data);

        if (response.success) {
          VortemLogger.log("Validation successful!");
          showMessage("✅ Endpoint validation successful!", "success");
        } else {
          VortemLogger.log("Validation failed:", response.data);
          showMessage(
            "❌ Endpoint validation failed: " +
              (response.data || "Unknown error"),
            "error"
          );
        }
      },
      error: function (xhr, status, error) {
        VortemLogger.log("=== VORTEM AJAX ERROR ===");
        VortemLogger.log("XHR object:", xhr);
        VortemLogger.log("Status:", status);
        VortemLogger.log("Error:", error);
        VortemLogger.log("Response text:", xhr.responseText);
        VortemLogger.log("Status code:", xhr.status);
        showMessage(
          "❌ Failed to validate endpoint - check your connection",
          "error"
        );
      },
      complete: function () {
        VortemLogger.log("Vortem AJAX request completed, re-enabling button");
        button.prop("disabled", false).text(originalText);
      },
    });
  });

  // Fetch Products Button
  $("#fetch-products").on("click", function (e) {
    VortemLogger.log("=== FETCH PRODUCTS CLICKED (DIRECT) ===");
    VortemLogger.log("Event:", e);
    VortemLogger.log("Button element:", this);
    VortemLogger.log("Button jQuery object:", $(this));
    VortemLogger.log("Button text:", $(this).text());
    VortemLogger.log("Button disabled:", $(this).prop("disabled"));
    VortemLogger.log("vortem_admin object:", vortem_admin);
    VortemLogger.log(
      "AJAX URL:",
      vortem_admin ? vortem_admin.ajax_url : "UNDEFINED"
    );
    VortemLogger.log("Nonce:", vortem_admin ? vortem_admin.nonce : "UNDEFINED");

    var button = $(this);
    var originalText = button.text();
    var limit = $("#fetch-limit").val();

    VortemLogger.log("Original button text:", originalText);
    VortemLogger.log("Limit value:", limit);
    VortemLogger.log("Fetch limit element exists:", $("#fetch-limit").length);
    VortemLogger.log("Fetch limit element value:", $("#fetch-limit").val());

    VortemLogger.log("Disabling button...");
    button.prop("disabled", true).text("Fetching...");

    VortemLogger.log("Sending AJAX request...");

    // Validate required data before sending
    if (!vortem_admin || !vortem_admin.ajax_url) {
      VortemLogger.error("vortem_admin object or ajax_url is missing!");
      showMessage("❌ Configuration error: AJAX URL not found", "error");
      button.prop("disabled", false).text(originalText);
      return;
    }

    if (!vortem_admin.nonce) {
      VortemLogger.error("Nonce is missing!");
      showMessage("❌ Configuration error: Security nonce not found", "error");
      button.prop("disabled", false).text(originalText);
      return;
    }

    vortemAjax({
      url: vortem_admin.ajax_url,
      type: "POST",
      data: {
        action: "vortem_fetch_products",
        nonce: vortem_admin.nonce,
        limit: limit,
      },
      beforeSend: function () {
        VortemLogger.log("Vortem AJAX request sent to:", vortem_admin.ajax_url);
        VortemLogger.log("Request data:", {
          action: "vortem_fetch_products",
          nonce: vortem_admin.nonce,
          limit: limit,
        });
      },
      success: function (response) {
        VortemLogger.log("=== VORTEM AJAX SUCCESS ===");
        VortemLogger.log("Full response:", response);
        VortemLogger.log("Response success:", response.success);
        VortemLogger.log("Response data:", response.data);

        if (response.success) {
          var count =
            response.data.returned || response.data.products.length || 0;
          VortemLogger.log("Products count:", count);
          VortemLogger.log("Products data:", response.data.products);
          showMessage(
            "✅ Products fetched successfully! " +
              count +
              " products retrieved.",
            "success"
          );

          // Update notice-info in vortem-page-content
          updateProductFetchNotice(count, response.data.products);

          // Display products in dashboard
          VortemLogger.log("Displaying products in dashboard...");
          displayProductsInDashboard(response.data.products);

          // Refresh imports counter after successful fetch
          refreshImportsCounter();
        } else {
          VortemLogger.log("Response indicates failure");
          var errorMessage = "Unknown error";
          if (response.data) {
            if (typeof response.data === "string") {
              errorMessage = response.data;
            } else if (response.data.message) {
              errorMessage = response.data.message;
            } else {
              errorMessage = JSON.stringify(response.data);
            }
          }
          VortemLogger.log("Error message:", errorMessage);
          showMessage("❌ Failed to fetch products: " + errorMessage, "error");
        }
      },
      error: function (xhr, status, error) {
        VortemLogger.log("=== VORTEM AJAX ERROR ===");
        VortemLogger.log("XHR object:", xhr);
        VortemLogger.log("Status:", status);
        VortemLogger.log("Error:", error);
        VortemLogger.log("Response text:", xhr.responseText);
        VortemLogger.log("Status code:", xhr.status);
        showMessage(
          "❌ Failed to fetch products - check your connection",
          "error"
        );
      },
      complete: function () {
        VortemLogger.log("Vortem AJAX request completed, re-enabling button");
        button.prop("disabled", false).text(originalText);
      },
    });
  });

  // Function to refresh imports counter silently (without showing messages)
  // Global function to format number - always use English numerals
  function formatNumberForLanguage(num) {
    if (num === null || num === undefined || num === '') {
      return num;
    }
    return String(num);
  }
  
  // Make function globally available
  window.formatNumberForLanguage = formatNumberForLanguage;

  function refreshImportsCounter() {
    VortemLogger.log("=== REFRESHING IMPORTS COUNTER SILENTLY ===");
    VortemLogger.log("Function called from:", new Error().stack);

    // Validate required data before sending
    if (!vortem_admin || !vortem_admin.ajax_url) {
      VortemLogger.error("vortem_admin object or ajax_url is missing!");
      VortemLogger.log("vortem_admin:", vortem_admin);
      return;
    }

    VortemLogger.log("Making AJAX call to refresh auth status...");
    VortemLogger.log("Nonce:", vortem_admin.nonce_refresh_imports);
    VortemLogger.log("AJAX URL:", vortem_admin.ajax_url);

    window.vortemAjax({
      url: vortem_admin.ajax_url,
      type: "POST",
      data: {
        action: "vortem_refresh_imports_counter",
        nonce: vortem_admin.nonce_refresh_imports,
      },
      success: function (response) {
        VortemLogger.log("=== SILENT REFRESH AUTH STATUS SUCCESS ===");
        VortemLogger.log("Response:", response);
        VortemLogger.log("Response success:", response.success);
        VortemLogger.log("Response data:", response.data);

        if (response.success) {
          VortemLogger.log("Auth status data:", response.data);
          VortemLogger.log("Updating dashboard counters...");

          // Update all dashboard counters
          // Translate auth status before setting it
          // Use pre-translated status if available, otherwise translate in JavaScript
          var statusText = response.data.status_translated || response.data.status || '';
          if (!statusText && response.data.status && typeof vortem_admin !== 'undefined' && vortem_admin.strings) {
            // Fallback: translate in JavaScript if not pre-translated
            var originalStatus = response.data.status;
            statusText = vortem_admin.strings[originalStatus] || 
                        vortem_admin.strings[originalStatus.charAt(0).toUpperCase() + originalStatus.slice(1).toLowerCase()] ||
                        originalStatus;
          }
          $("#dashboard-auth-status").text(statusText);
          $("#dashboard-days-left").text(response.data.days_left);
          $("#dashboard-imports-used").text(response.data.imports_used);
          $("#dashboard-imports-limit").text(response.data.imports_limit);
          $("#dashboard-daily-imports-used").text(response.data.daily_imports_used);
          $("#dashboard-remaining-imports").text(response.data.remaining_imports);
          $("#dashboard-imported-products").text(response.data.imported_products);
          
          // Update top products imported count if it exists
          $("#top-products-imported-count").text(response.data.imported_products);

          // Update products page counter if it exists
          $("#imports-used-counter").text(response.data.imports_used);

          VortemLogger.log("Dashboard counters updated successfully");
        } else {
          VortemLogger.log("Silent refresh failed:", response.data);
        }
      },
      error: function (xhr, status, error) {
        VortemLogger.log("=== SILENT REFRESH IMPORTS COUNTER AJAX ERROR ===");
        VortemLogger.log("XHR object:", xhr);
        VortemLogger.log("Status:", status);
        VortemLogger.log("Error:", error);
        VortemLogger.log("Response text:", xhr.responseText);
        VortemLogger.log("Status code:", xhr.status);
        VortemLogger.log("Ready state:", xhr.readyState);
      },
    });
  }

  // Make refreshImportsCounter available globally
  window.refreshImportsCounter = refreshImportsCounter;


  // Auto-refresh imports counter for dashboard page
  function initDashboardAutoRefresh() {
    VortemLogger.log("=== INITIALIZING DASHBOARD AUTO REFRESH ===");

    // Check if we're on the dashboard page
    if (window.location.href.indexOf("page=vortem-owerview") !== -1) {
      VortemLogger.log("Dashboard page detected, setting up auto refresh");

      // Refresh immediately on page load
      refreshImportsCounter();

      // Set up periodic refresh every 30 seconds
      setInterval(function () {
        VortemLogger.log("=== AUTO REFRESH TRIGGERED ===");
        refreshImportsCounter();
      }, 30000); // 30 seconds
    }
  }

  // Add button testing function
  window.testVortemButtons = function () {
    VortemLogger.log("=== TESTING VORTEM BUTTONS ===");

    // Check if vortemAjax is available
    VortemLogger.log("vortemAjax available:", typeof window.vortemAjax);

    // Check if vortem_admin is available
    VortemLogger.log("vortem_admin available:", typeof vortem_admin);
    VortemLogger.log("vortem_admin object:", vortem_admin);

    // Test validate endpoint button
    VortemLogger.log("Testing validate endpoint button...");
    var validateBtn = $("#validate-endpoint");
    VortemLogger.log("Validate button found:", validateBtn.length);
    VortemLogger.log("Validate button visible:", validateBtn.is(":visible"));
    VortemLogger.log("Validate button enabled:", !validateBtn.prop("disabled"));
    VortemLogger.log(
      "Validate button HTML:",
      validateBtn.length ? validateBtn[0].outerHTML : "NOT FOUND"
    );

    // Check event handlers
    var events = $._data(validateBtn[0], "events");
    VortemLogger.log("Validate button event handlers:", events);

    if (validateBtn.length) {
      VortemLogger.log("Triggering validate endpoint click...");
      validateBtn.trigger("click");
    } else {
      VortemLogger.error("Validate endpoint button not found!");
    }

    // Test fetch products button
    VortemLogger.log("Testing fetch products button...");
    var fetchBtn = $("#fetch-products");
    VortemLogger.log("Fetch button found:", fetchBtn.length);
    VortemLogger.log("Fetch button visible:", fetchBtn.is(":visible"));
    VortemLogger.log("Fetch button enabled:", !fetchBtn.prop("disabled"));
    VortemLogger.log(
      "Fetch button HTML:",
      fetchBtn.length ? fetchBtn[0].outerHTML : "NOT FOUND"
    );

    // Check event handlers
    var fetchEvents = $._data(fetchBtn[0], "events");
    VortemLogger.log("Fetch button event handlers:", fetchEvents);

    if (fetchBtn.length) {
      VortemLogger.log("Triggering fetch products click...");
      fetchBtn.trigger("click");
    } else {
      VortemLogger.error("Fetch products button not found!");
    }
  };

  // Add global debugging function
  window.debugVortem = function () {
    VortemLogger.log("=== VORTEM DEBUG INFO ===");
    VortemLogger.log("jQuery version:", $.fn.jquery);
    VortemLogger.log("vortem_admin object:", vortem_admin);
    VortemLogger.log("Current page:", window.location.href);
    VortemLogger.log("All buttons:", $("button").length);
    VortemLogger.log(
      "Vortem buttons:",
      $('button[id*="vortem"], button[id*="validate"], button[id*="fetch"]')
        .length
    );

    // Check if scripts are loaded (wp_script_is is a PHP function, not available in JavaScript)
    VortemLogger.log("Scripts loaded:", {
      "vortem-admin":
        typeof vortem_admin !== "undefined" ? "loaded" : "not loaded",
    });
  };

  // Add manual button initialization function
  window.initVortemButtons = function () {
    VortemLogger.log("=== MANUAL BUTTON INITIALIZATION ===");
    initializeButtons();
  };

  // Add button status check function
  window.checkButtonStatus = function () {
    VortemLogger.log("=== BUTTON STATUS CHECK ===");
    var validateBtn = $("#validate-endpoint");
    var fetchBtn = $("#fetch-products");

    VortemLogger.log("Validate button:", {
      exists: validateBtn.length > 0,
      visible: validateBtn.is(":visible"),
      enabled: !validateBtn.prop("disabled"),
      text: validateBtn.text(),
      id: validateBtn.attr("id"),
      class: validateBtn.attr("class"),
    });

    VortemLogger.log("Fetch button:", {
      exists: fetchBtn.length > 0,
      visible: fetchBtn.is(":visible"),
      enabled: !fetchBtn.prop("disabled"),
      text: fetchBtn.text(),
      id: fetchBtn.attr("id"),
      class: fetchBtn.attr("class"),
    });

    return {
      validate: validateBtn.length > 0,
      fetch: fetchBtn.length > 0,
    };
  };

  // Add simple AJAX test function
  window.testAjax = function () {
    VortemLogger.log("=== TESTING AJAX ===");

    if (!vortem_admin || !vortem_admin.ajax_url) {
      VortemLogger.error("vortem_admin object or ajax_url is missing!");
      return false;
    }

    if (!vortem_admin.nonce) {
      VortemLogger.error("Nonce is missing!");
      return false;
    }

    VortemLogger.log("Sending simple AJAX test using vortemAjax...");

    vortemAjax({
      url: vortem_admin.ajax_url,
      type: "POST",
      data: {
        action: "vortem_simple_test",
        nonce: vortem_admin.nonce,
      },
      beforeSend: function () {
        VortemLogger.log("Vortem AJAX test request sent to:", vortem_admin.ajax_url);
      },
      success: function (response) {
        VortemLogger.log("=== VORTEM AJAX TEST SUCCESS ===");
        VortemLogger.log("Response:", response);
        showMessage(
          "✅ AJAX test successful: " + response.data.message,
          "success"
        );
      },
      error: function (xhr, status, error) {
        VortemLogger.log("=== VORTEM AJAX TEST ERROR ===");
        VortemLogger.log("XHR:", xhr);
        VortemLogger.log("Status:", status);
        VortemLogger.log("Error:", error);
        VortemLogger.log("Response text:", xhr.responseText);
        showMessage("❌ AJAX test failed: " + error, "error");
      },
    });
  };

  // Add function to check for conflicting AJAX handlers
  window.checkAjaxConflicts = function () {
    VortemLogger.log("=== CHECKING FOR AJAX CONFLICTS ===");

    // Check if there are any global AJAX handlers that might conflict
    VortemLogger.log("jQuery AJAX settings:", $.ajaxSettings);

    // Check for any global AJAX error handlers
    if ($.ajaxSetup) {
      VortemLogger.log("Global AJAX setup exists");
    }

    // Check for any global AJAX error handlers
    if ($.ajaxError) {
      VortemLogger.log("Global AJAX error handler exists");
    }

    // Check for any global AJAX success handlers
    if ($.ajaxSuccess) {
      VortemLogger.log("Global AJAX success handler exists");
    }

    // Check for any global AJAX complete handlers
    if ($.ajaxComplete) {
      VortemLogger.log("Global AJAX complete handler exists");
    }

    // Check for any global AJAX beforeSend handlers
    if ($.ajaxSend) {
      VortemLogger.log("Global AJAX beforeSend handler exists");
    }

    // Check for any global AJAX start handlers
    if ($.ajaxStart) {
      VortemLogger.log("Global AJAX start handler exists");
    }

    // Check for any global AJAX stop handlers
    if ($.ajaxStop) {
      VortemLogger.log("Global AJAX stop handler exists");
    }

    VortemLogger.log("AJAX conflict check complete");
  };

  // Add function to disable conflicting AJAX handlers
  window.disableAjaxConflicts = function () {
    VortemLogger.log("=== DISABLING AJAX CONFLICTS ===");

    // Remove global AJAX handlers
    if ($.ajaxSetup) {
      $.ajaxSetup({});
    }

    // Remove global AJAX error handlers
    if ($.ajaxError) {
      $(document).off("ajaxError");
    }

    // Remove global AJAX success handlers
    if ($.ajaxSuccess) {
      $(document).off("ajaxSuccess");
    }

    // Remove global AJAX complete handlers
    if ($.ajaxComplete) {
      $(document).off("ajaxComplete");
    }

    // Remove global AJAX beforeSend handlers
    if ($.ajaxSend) {
      $(document).off("ajaxSend");
    }

    // Remove global AJAX start handlers
    if ($.ajaxStart) {
      $(document).off("ajaxStart");
    }

    // Remove global AJAX stop handlers
    if ($.ajaxStop) {
      $(document).off("ajaxStop");
    }

    VortemLogger.log("AJAX conflicts disabled");
  };

  // Auto-run debug on products page
  if (window.location.href.indexOf("vortem-products") !== -1) {
    setTimeout(function () {
      VortemLogger.log("=== AUTO-DEBUG ON PRODUCTS PAGE ===");
      window.debugVortem();

      // Ensure buttons are properly initialized
      initializeButtons();

      // Check for AJAX conflicts
      window.checkAjaxConflicts();

      // Log button information
      VortemLogger.log("=== BUTTON INFO ===");
      VortemLogger.log(
        "Validate button:",
        $("#validate-endpoint").length ? "Found" : "NOT FOUND"
      );
      VortemLogger.log(
        "Fetch button:",
        $("#fetch-products").length ? "Found" : "NOT FOUND"
      );

      // Test if buttons can be clicked programmatically
      if ($("#validate-endpoint").length) {
        VortemLogger.log("Validate button can be triggered");
      }
      if ($("#fetch-products").length) {
        VortemLogger.log("Fetch button can be triggered");
      }
    }, 2000);
  }

  // Add console message with instructions
  VortemLogger.log("=== VORTEM PLUGIN READY ===");
  VortemLogger.log("Available test functions:");
  VortemLogger.log("- testAjax() - Test AJAX connection");
  VortemLogger.log("- testVortemButtons() - Test button functionality");
  VortemLogger.log("- checkButtonStatus() - Check button state");
  VortemLogger.log("- debugVortem() - Show debug information");
  VortemLogger.log("- checkAjaxConflicts() - Check for AJAX conflicts");
  VortemLogger.log("- initVortemButtons() - Reinitialize buttons");

  // Button initialization function
  function initializeButtons() {
    VortemLogger.log("=== INITIALIZING BUTTONS ===");

    // Check if buttons exist and are visible
    var validateBtn = $("#validate-endpoint");
    var fetchBtn = $("#fetch-products");

    VortemLogger.log("Validate button found:", validateBtn.length);
    VortemLogger.log("Fetch button found:", fetchBtn.length);

    if (validateBtn.length === 0) {
      VortemLogger.error("Validate endpoint button not found!");
      return;
    }

    if (fetchBtn.length === 0) {
      VortemLogger.error("Fetch products button not found!");
      return;
    }

    // Ensure buttons are enabled
    validateBtn.prop("disabled", false);
    fetchBtn.prop("disabled", false);

    VortemLogger.log("Buttons initialized successfully");

    // Test button functionality
    VortemLogger.log("Testing button click handlers...");

    // Add a test click to validate button
    validateBtn.off("click").on("click", function (e) {
      VortemLogger.log("=== VALIDATE ENDPOINT CLICKED (REINITIALIZED) ===");
      e.preventDefault();
      e.stopPropagation();

      var button = $(this);
      var originalText = button.text();

      VortemLogger.log("Button clicked, original text:", originalText);
      button.prop("disabled", true).text("Validating...");

      // Restore button after 3 seconds for testing
      setTimeout(function () {
        button.prop("disabled", false).text(originalText);
        VortemLogger.log("Button restored for testing");
      }, 3000);

      // Show test message
      showMessage("✅ Validate endpoint button is working!", "success");
    });

    // Add a test click to fetch button
    fetchBtn.off("click").on("click", function (e) {
      VortemLogger.log("=== FETCH PRODUCTS CLICKED (REINITIALIZED) ===");
      e.preventDefault();
      e.stopPropagation();

      var button = $(this);
      var originalText = button.text();

      VortemLogger.log("Button clicked, original text:", originalText);
      button.prop("disabled", true).text("Fetching...");

      // Restore button after 3 seconds for testing
      setTimeout(function () {
        button.prop("disabled", false).text(originalText);
        VortemLogger.log("Button restored for testing");
      }, 3000);

      // Show test message
      showMessage("✅ Fetch products button is working!", "success");
    });

    VortemLogger.log("Button handlers reinitialized successfully");
  }

  // Update product fetch notice in vortem-page-content
  function updateProductFetchNotice(count, products) {
    VortemLogger.log("Updating product fetch notice:", count, products);

    // Find all notice-info elements within vortem-page-content
    var notices = $(".vortem-page-content .notice.notice-info");

    if (notices.length > 0) {
      // Update the first notice-info element
      var noticeHtml = '<div class="notice notice-info vortem-notice">';
      noticeHtml += "<p><strong>Products Fetched Successfully!</strong></p>";
      noticeHtml +=
        "<p>" +
        count +
        " products retrieved from the API and are ready for import.</p>";
      noticeHtml += "</div>";

      notices.first().replaceWith(noticeHtml);
    } else {
      // If no notice exists, add one at the top of vortem-page-content
      var vortemPageContent = $(".vortem-page-content");
      if (vortemPageContent.length > 0) {
        var noticeHtml =
          '<div class="notice notice-info vortem-notice vortem-plugin-notice">';
        noticeHtml += "<p><strong>Products Fetched Successfully!</strong></p>";
        noticeHtml +=
          "<p>" +
          count +
          " products retrieved from the API and are ready for import.</p>";
        noticeHtml += "</div>";

        vortemPageContent.prepend(noticeHtml);
      }
    }
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
        noticeHtml += "<p><strong>Product Imported Successfully!</strong></p>";
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

  // Helper function to format numbers with commas
  function formatNumberWithCommas(num, decimals) {
    if (num === null || num === undefined || isNaN(num)) {
      return '0';
    }
    var parts = num.toFixed(decimals).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return parts.join('.');
  }

  // Display products in dashboard
  function displayProductsInDashboard(products) {
    VortemLogger.log("Displaying products in dashboard:", products);

    var dashboardContent = $("#product-dashboard-content");

    if (!products || products.length === 0) {
      dashboardContent.html(
        '<div class="notice notice-info vortem-notice vortem-plugin-notice"><p><strong>No Products Found</strong></p><p>No products were retrieved from the API. Please check your connection and try again.</p></div>'
      );
      return;
    }

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
        html += '<button type="button" class="button button-secondary delete-product-btn" data-product-id="' + productId + '">' + deleteText + '</button>';
        html += '<button type="button" class="button button-primary import-product-btn" data-product-id="' + productId + '" style="display: none;">' + importText + '</button>';
      } else {
        html += '<button type="button" class="button button-primary import-product-btn" data-product-id="' + productId + '">' + importText + '</button>';
        html += '<button type="button" class="button button-secondary delete-product-btn" data-product-id="' + productId + '" style="display: none;">' + deleteText + '</button>';
      }
      
      html += "</div>";
      html += "</div>";
      html += "</div>"; // End content section
      html += "</div>"; // End card
    });

    html += "</div>";

    dashboardContent.html(html);

    // Check import status for each product
    checkProductImportStatus(products);
  }

  // Check import status for products
  function checkProductImportStatus(products) {
    products.forEach(function (product) {
      $.ajax({
        url: vortem_admin.ajax_url,
        type: "POST",
        data: {
          action: "vortem_check_product_status",
          nonce: vortem_admin.nonce,
          product_id: product.product_id,
          sku: product.sku || "",
        },
        success: function (response) {
          if (response.success) {
            var productId = product.product_id;
            var importBtn = $(
              '.import-product-btn[data-product-id="' + productId + '"]'
            );
            var deleteBtn = $(
              '.delete-product-btn[data-product-id="' + productId + '"]'
            );

            // If product exists in WooCommerce (even if not properly imported), show Delete button
            if (response.data.exists_in_woocommerce) {
              // Hide Import button and show Delete button
              importBtn.hide();
              deleteBtn.show();

              var card = importBtn.closest(".vortem-product-card");
              var imageContainer = card.find(".product-image-container");

              // Update status badge if product exists but wasn't properly imported
              if (!response.data.is_imported) {
                var statusBadge = card.find(".product-status-badge");
                var existsText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.exists) ? vortem_admin.strings.exists : 'Exists';
                if (statusBadge.length) {
                  statusBadge.text(existsText).css({
                    background: "#f56e28",
                    color: "white",
                  });
                }
              }

              // Add preview button if product has woo_product_id
              if (response.data.woo_product_id && imageContainer.length > 0) {
                var existingPreview = imageContainer.find('.product-preview-button');
                if (existingPreview.length === 0) {
                  var previewUrl = (typeof vortem_admin !== 'undefined' && vortem_admin.site_url) ? vortem_admin.site_url + '?p=' + response.data.woo_product_id + '&preview=true' : '#';
                  var previewText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.preview) ? vortem_admin.strings.preview : 'Preview';
                  var previewButton = '<a href="' + previewUrl + '" target="_blank" class="product-preview-button" data-woo-product-id="' + response.data.woo_product_id + '">' + previewText + '</a>';
                  imageContainer.prepend(previewButton);
                }
              }
            } else if (response.data.is_imported) {
              // Product is properly imported, show delete button
              importBtn.hide();
              deleteBtn.show();

              var card = importBtn.closest(".vortem-product-card");
              var imageContainer = card.find(".product-image-container");

              // Add preview button if product has woo_product_id
              if (response.data.woo_product_id && imageContainer.length > 0) {
                var existingPreview = imageContainer.find('.product-preview-button');
                if (existingPreview.length === 0) {
                  var previewUrl = (typeof vortem_admin !== 'undefined' && vortem_admin.site_url) ? vortem_admin.site_url + '?p=' + response.data.woo_product_id + '&preview=true' : '#';
                  var previewText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.preview) ? vortem_admin.strings.preview : 'Preview';
                  var previewButton = '<a href="' + previewUrl + '" target="_blank" class="product-preview-button" data-woo-product-id="' + response.data.woo_product_id + '">' + previewText + '</a>';
                  imageContainer.prepend(previewButton);
                }
              }
            }
          }
        },
        error: function () {
          // If check fails, assume not imported
          VortemLogger.log(
            "Failed to check status for product:",
            product.product_id
          );
        },
      });
    });
  }

  // Handle individual product import - REMOVED: Now handled by vortem-buttons.js with modal
  // The import functionality is now in vortem-buttons.js which shows a modal first

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

  // Handle individual product delete
  $(document).on("click", ".delete-product-btn", function () {
    var button = $(this);

    // Check if button is already disabled to prevent duplicate clicks
    if (button.prop("disabled") || button.hasClass("deleting")) {
      VortemLogger.log("Delete button already disabled, preventing duplicate click");
      return;
    }

    var productId = button.data("product-id");
    var card = button.closest(".vortem-product-card");
    var originalText = button.html();

    var deleteMessage = "Are you sure you want to delete this product from WordPress?";
    if (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.delete_product_confirmation) {
      deleteMessage = vortem_admin.strings.delete_product_confirmation;
    }

    // Show modal instead of confirm
    showDeleteConfirmationModal(deleteMessage, function () {
      // Immediately mark as deleting and disable to prevent duplicate clicks
      var deletingText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.deleting) ? vortem_admin.strings.deleting : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 4px; animation: spin 1s linear infinite;"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-dasharray="31.416" stroke-dashoffset="23.562" opacity="0.9"/></svg> Deleting...';
      button.addClass("deleting").prop("disabled", true).html(deletingText);

      $.ajax({
        url: vortem_admin.ajax_url,
        type: "POST",
        data: {
          action: "vortem_delete_single_product",
          nonce: vortem_admin.nonce,
          product_id: productId,
        },
        success: function (response) {
          if (response.success) {
            showMessage("✅ Product deleted successfully!", "success");
            // Hide delete button and show import button
            button.removeClass("deleting").hide();
            var importBtn = button.siblings(".import-product-btn");
            if (importBtn.length) {
              // Reset button state - remove importing class and set correct text
              importBtn.removeClass("importing").prop("disabled", false);
              var importText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.import) ? vortem_admin.strings.import : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 4px;"><path d="M12 15V3M12 15L8 11M12 15L16 11M2 17L2 19C2 20.1046 2.89543 21 4 21L20 21C21.1046 21 22 20.1046 22 19V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Import';
              importBtn.html(importText);
              importBtn.show();
            }

            // Remove preview button
            var previewButton = card.find(".product-preview-button");
            if (previewButton.length) {
              previewButton.remove();
            }

            // Update product card label from "Added" back to "New"
            var statusBadge = card.find(".product-status-badge");
            var newText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.new) ? vortem_admin.strings.new : 'NEW';
            if (statusBadge.length) {
              statusBadge.removeClass("status-added").addClass("status-new").text(newText).css({
                background: "rgb(0, 115, 170)",
                color: "white",
                display: "block",
              });
            }
          } else {
            showMessage(
              "❌ Failed to delete product: " +
                (response.data.message || "Unknown error"),
              "error"
            );
            button
              .removeClass("deleting")
              .prop("disabled", false)
              .html(originalText);
          }
        },
        error: function (xhr, status, error) {
          VortemLogger.log("AJAX Error:", xhr, status, error);
          showMessage(
            "❌ Failed to delete product - check your connection",
            "error"
          );
          var deleteText = (typeof vortem_admin !== 'undefined' && vortem_admin.strings && vortem_admin.strings.delete) ? vortem_admin.strings.delete : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 4px;"><path d="M3 6H5H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 6V4C8 3.46957 8.21071 2.96086 8.58579 2.58579C8.96086 2.21071 9.46957 2 10 2H14C14.5304 2 15.0391 2.21071 15.4142 2.58579C15.7893 2.96086 16 3.46957 16 4V6M19 6V20C19 20.5304 18.7893 21.0391 18.4142 21.4142C18.0391 21.7893 17.5304 22 17 22H7C6.46957 22 5.96086 21.7893 5.58579 21.4142C5.21071 21.0391 5 20.5304 5 20V6H19Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 11V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 11V17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Delete';
          button
            .removeClass("deleting")
            .prop("disabled", false)
            .html(deleteText);
        },
      });
    });
  });

  // Initialize dashboard auto-refresh
  initDashboardAutoRefresh();

  // Global auto-fade handler for all plugin notices
  (function () {
    // Add vortem-plugin-notice class to existing notices that don't have it
    function markPluginNotices() {
      $(
        ".vortem-custom-notice, .vortem-notice, .vortem-auto-validation-notice, .vortem-auto-fetch-notice"
      )
        .not(".vortem-plugin-notice")
        .addClass("vortem-plugin-notice");
    }

    // Fade out plugin notices after 5 seconds
    function fadeOutPluginNotices() {
      $(".vortem-plugin-notice").each(function () {
        var $notice = $(this);
        // Skip if already fading or dismissed
        if (
          $notice.hasClass("vortem-fading-out") ||
          $notice.hasClass("notice-dismissed")
        ) {
          return;
        }

        // Add fading class to trigger CSS transition
        $notice.addClass("vortem-fading-out");

        // Remove from DOM after animation completes
        setTimeout(function () {
          if ($notice.length && document.body.contains($notice[0])) {
            $notice.remove();
          }
        }, 500); // Match CSS transition duration
      });
    }

    // Watch for new notices added dynamically
    var noticeObserver = new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        mutation.addedNodes.forEach(function (node) {
          if (node.nodeType === 1) {
            var $node = $(node);
            // Check if the node itself is a plugin notice or contains one
            if (
              $node.hasClass("vortem-custom-notice") ||
              $node.hasClass("vortem-notice") ||
              $node.hasClass("vortem-auto-validation-notice") ||
              $node.hasClass("vortem-auto-fetch-notice")
            ) {
              if (!$node.hasClass("vortem-plugin-notice")) {
                $node.addClass("vortem-plugin-notice");
              }
              // Schedule fade-out for this notice
              setTimeout(function () {
                if (
                  $node.length &&
                  document.body.contains($node[0]) &&
                  !$node.hasClass("vortem-fading-out") &&
                  !$node.hasClass("notice-dismissed")
                ) {
                  $node.addClass("vortem-fading-out");
                  setTimeout(function () {
                    if ($node.length && document.body.contains($node[0])) {
                      $node.remove();
                    }
                  }, 500);
                }
              }, 5000);
            } else {
              // Check for plugin notices within the added node
              $node
                .find(
                  ".vortem-custom-notice, .vortem-notice, .vortem-auto-validation-notice, .vortem-auto-fetch-notice"
                )
                .not(".vortem-plugin-notice")
                .each(function () {
                  var $notice = $(this);
                  $notice.addClass("vortem-plugin-notice");
                  // Schedule fade-out
                  setTimeout(function () {
                    if (
                      $notice.length &&
                      document.body.contains($notice[0]) &&
                      !$notice.hasClass("vortem-fading-out") &&
                      !$notice.hasClass("notice-dismissed")
                    ) {
                      $notice.addClass("vortem-fading-out");
                      setTimeout(function () {
                        if (
                          $notice.length &&
                          document.body.contains($notice[0])
                        ) {
                          $notice.remove();
                        }
                      }, 500);
                    }
                  }, 5000);
                });
            }
          }
        });
      });
    });

    // Mark existing notices and fade them out
    markPluginNotices();
    setTimeout(fadeOutPluginNotices, 5000);

    // Observe the document body for new notices
    if (document.body) {
      noticeObserver.observe(document.body, {
        childList: true,
        subtree: true,
      });
    }

    // Re-mark notices periodically to catch any missed ones
    setInterval(function () {
      markPluginNotices();
    }, 1000);
  })();

  // Fix settings page wrapper positioning on language change
  (function() {
    // Add class to settings page wrapper for CSS targeting
    const $settingsForm = $('#vortem-settings-form');
    if ($settingsForm.length) {
      const $wrapper = $settingsForm.closest('.vortem-page-wrapper');
      if ($wrapper.length) {
        $wrapper.addClass('vortem-settings-page');
      }
    }

    // Re-apply class after language change to ensure it persists
    $(document).on('vortem:language-changed', function() {
      const $settingsForm = $('#vortem-settings-form');
      if ($settingsForm.length) {
        const $wrapper = $settingsForm.closest('.vortem-page-wrapper');
        if ($wrapper.length) {
          $wrapper.addClass('vortem-settings-page');
          // Force reflow to recalculate styles
          void $wrapper[0].offsetHeight;
        }
      }
    });
  })();
});
