// External Library: jQuery (jQuery Foundation) - https://jquery.com/ | License: MIT | WordPress-bundled library used for DOM ready detection
(function() {
    'use strict';

    const ProductsComponent = {
        init: function(container) {
            if (this.initialized) {
                return;
            }

            this.container = container;
            this.initialized = true;

            if (typeof jQuery !== 'undefined') {
                const $ = jQuery;
                if ($.fn.ready) {
                    $(document).ready(() => {
                        this.ensureInitialized();
                    });
                } else {
                    this.ensureInitialized();
                }
            } else {
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', () => {
                        this.ensureInitialized();
                    });
                } else {
                    this.ensureInitialized();
                }
            }
        },

        ensureInitialized: function() {
            if (this.componentInitialized) {
                return;
            }
            this.componentInitialized = true;
        }
    };

    if (typeof window !== 'undefined') {
        window.ProductsComponent = ProductsComponent;
    }

    return ProductsComponent;
})();

