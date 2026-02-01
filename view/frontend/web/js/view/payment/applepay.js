define([
    'jquery',
    'uiComponent',
    'Magento_Checkout/js/model/quote',
    'Magento_Customer/js/model/customer',
    'mage/url',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Ui/js/model/messageList',
    'underscore'
], function ($, Component, quote, customer, url, fullScreenLoader, messageList, _) {
    'use strict';

    let applePayInitialized = false;
    let applePayProcessing = false;
    let activeRequest = null;

    return Component.extend({
        defaults: {
            template: 'Amwal_Pay/payment/applepay'
        },

        /** ===============================
         * INIT
         * =============================== */
        initialize: function () {
            this._super();

            const debouncedInit = _.debounce(() => {
                applePayInitialized = false;
                this.tryInitApplePay();
            }, 300);

            quote.shippingAddress.subscribe(debouncedInit);
            quote.shippingMethod.subscribe(debouncedInit);

            return this;
        },

        /** ===============================
         * BASIC HELPERS
         * =============================== */
        isActive: function () {
            const cfg = window.checkoutConfig.payment.amwal_pay || {};
            return !!(cfg.active && cfg.has_apple_pay && window.ApplePaySession);
            // return true;

        },

        getCustomerEmail: function () {
            if (customer.isLoggedIn()) {
                return customer.customerData.email || null;
            }
            return quote.guestEmail ||
                (quote.shippingAddress() && quote.shippingAddress().email) ||
                null;
        },

        getTelephone: function () {
            const address = quote.shippingAddress();
            return address && address.telephone ? address.telephone : '99999999';
        },

        /** ===============================
         * DATA BUILDERS (REUSED)
         * =============================== */
        buildShippingAddress: function () {
            const address = quote.shippingAddress();
            if (!address) return null;

            return {
                firstname: address.firstname || 'Customer',
                lastname: address.lastname || 'ApplePay',
                street: address.street,
                city: address.city,
                region: address.region,
                region_id: address.regionId,
                postcode: address.postcode,
                countryId: address.countryId,
                telephone: this.getTelephone(),
                email: this.getCustomerEmail()
            };
        },

        buildShippingMethod: function () {
            const method = quote.shippingMethod();
            if (!method) return null;

            return {
                carrier_code: method.carrier_code,
                method_code: method.method_code
            };
        },

        /** ===============================
         * VALIDATION
         * =============================== */
        isApplePayAvailable: function () {
            const address = quote.shippingAddress();
            const method = quote.shippingMethod();
            const email = this.getCustomerEmail();

            if (!address || !method || !email) return false;

            if (!Array.isArray(address.street) || !address.street[0]) return false;
            if (!address.countryId || !address.postcode || !address.city) return false;

            if (address.countryId === 'US' && !address.regionId && !address.region) {
                return false;
            }

            return true;
        },

        /** ===============================
         * INIT HANDLER
         * =============================== */
        tryInitApplePay: function () {
            if (!this.isActive() || !this.isApplePayAvailable()) {
                this.clearApplePayButton();
                return;
            }

            this.initApplePay();
        },

        clearApplePayButton: function () {
            $('#apple_pay_button').empty();
        },

        /** ===============================
         * APPLE PAY INIT (AJAX)
         * =============================== */
        initApplePay: function () {
            if (applePayInitialized) return;

            // Abort previous request
            if (activeRequest && activeRequest.readyState !== 4) {
                activeRequest.abort();
            }

            const shippingAddress = this.buildShippingAddress();
            const shippingMethod = this.buildShippingMethod();

            if (!shippingAddress || !shippingMethod) return;

            activeRequest = $.post(
                url.build('amwal/applepay/ApplePayInit'),
                {
                    quote_id: quote.getQuoteId(),
                    address: JSON.stringify(shippingAddress),
                    method: JSON.stringify(shippingMethod),
                    email: shippingAddress.email
                }
            ).done((res) => {
                if (!res.success) {
                    console.error('ApplePayInit failed:', res.message);
                    return;
                }

                applePayInitialized = true;
                this.renderApplePay(res.payload);
            }).fail((xhr) => {
                if (xhr.statusText !== 'abort') {
                    applePayInitialized = false;
                }
            });
        },

        /** ===============================
         * RENDER + CALLBACKS
         * =============================== */
        renderApplePay: function (payload) {
            this.clearApplePayButton();

            SmartBox.Checkout.configure = {
                ...payload,

                completeCallback: (res) => {
                    if (applePayProcessing) return;

                    applePayProcessing = true;
                    fullScreenLoader.startLoader();

                    this.placeOrder(res);
                },

                errorCallback: (err) => {
                    applePayProcessing = false;
                    fullScreenLoader.stopLoader();
                    console.error('Apple Pay error:', err);
                }
            };

            SmartBox.Checkout.addPayWithApplePayButton();
        },

        /** ===============================
         * PLACE ORDER (AJAX)
         * =============================== */
        placeOrder: function (res) {
            const shippingAddress = this.buildShippingAddress();
            const shippingMethod = this.buildShippingMethod();

            $.post(
                url.build('amwal/applepay/placeOrder'),
                {
                    quote_id: quote.getQuoteId(),
                    transaction: res.data.data,
                    address: JSON.stringify(shippingAddress),
                    method: JSON.stringify(shippingMethod),
                    email: shippingAddress.email,
                    responseCode: res.data.responseCode
                }
            ).done((result) => {
                applePayProcessing = false;

                if (res.data.responseCode !== '00') {
                    fullScreenLoader.stopLoader();
                    const msg = res.data.data.message;
                    messageList.addErrorMessage({
                        message: 'Apple Pay failed: ' + msg
                    });
                    window.location.href = url.build('checkout/cart');
                    return;
                }

                if (result.success) {
                    window.location.href = result.redirect;
                } else {
                    fullScreenLoader.stopLoader();
                    console.error('Order failed:', result.message);
                }
            }).fail(() => {
                applePayProcessing = false;
                fullScreenLoader.stopLoader();
            });
        }
    });
});
