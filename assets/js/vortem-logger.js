/**
 * Vortem Conditional Logger Utility
 * 
 * Provides conditional console logging that only works in development mode.
 * Checks for development mode from vortem_admin.is_development or falls back to localhost check.
 */

(function() {
    'use strict';

    /**
     * Check if we're in development mode
     * @returns {boolean}
     */
    function isDevelopmentMode() {
        // Check if vortem_admin object exists and has is_development flag
        if (typeof window.vortem_admin !== 'undefined' && 
            window.vortem_admin.hasOwnProperty('is_development')) {
            return window.vortem_admin.is_development === true;
        }

        // Fallback: check if we're on localhost
        var hostname = window.location.hostname;
        return hostname === 'localhost' || 
               hostname === '127.0.0.1' || 
               hostname === '::1' ||
               hostname.indexOf('localhost') !== -1;
    }

    /**
     * Conditional logger object
     * Only logs when in development mode
     */
    window.VortemLogger = {
        log: function() {
            if (isDevelopmentMode()) {
                console.log.apply(console, arguments);
            }
        },
        error: function() {
            if (isDevelopmentMode()) {
                console.error.apply(console, arguments);
            }
        },
        warn: function() {
            if (isDevelopmentMode()) {
                console.warn.apply(console, arguments);
            }
        },
        info: function() {
            if (isDevelopmentMode()) {
                console.info.apply(console, arguments);
            }
        },
        debug: function() {
            if (isDevelopmentMode()) {
                console.debug.apply(console, arguments);
            }
        }
    };

    // Make it available globally
    if (typeof window.vortemLog === 'undefined') {
        window.vortemLog = window.VortemLogger;
    }
})();

