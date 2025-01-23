define([
    'jquery',
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list',
    'Magento_Checkout/js/model/quote'
], function ($, Component, rendererList, quote) {
    'use strict';

    // Register the custom payment method
    rendererList.push(
        {
            type: 'amwal_pay',
            component: 'Amwal_Pay/js/view/payment/method-renderer/amwalpay'
        }
    );

    return Component.extend({
        initialize: function () {
            this._super();
            this.loadScript();
        },

        loadScript: function () {
            var environment = window.checkoutConfig.payment['amwal_pay'].environment;
            var scriptUrl;

            // Determine the script URL based on the environment
            if (environment === "prod") {
                scriptUrl = "https://checkout.amwalpg.com/js/SmartBox.js?v=1.1";
            } else if (environment === "uat") {
                scriptUrl = "https://test.amwalpg.com:7443/js/SmartBox.js?v=1.1";
            } else if (environment === "sit") {
                scriptUrl = "https://test.amwalpg.com:19443/js/SmartBox.js?v=1.1";
            } else {
                console.error('Invalid environment value:', environment);
                return;
            }

            // Load the script dynamically
            var script = document.createElement('script');
            script.type = 'text/javascript';
            script.src = scriptUrl;
            script.async = true;
            script.onload = function () {
                // console.log('Amwal script loaded successfully:', scriptUrl);
            };
            script.onerror = function () {
                // console.error('Failed to load Amwal script:', scriptUrl);
            };
            document.head.appendChild(script);
        }
    });
});
