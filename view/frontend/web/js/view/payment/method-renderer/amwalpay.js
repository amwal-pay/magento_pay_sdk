define(
    [
        'Magento_Checkout/js/view/payment/default',
        'jquery',
        'Magento_Checkout/js/model/payment/additional-validators',
        'mage/url',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/model/full-screen-loader'
    ],
    function (
        Component,
        $,
        additionalValidators,
        url,
        placeOrderAction,
        fullScreenLoader
    ) {
        return Component.extend({
            defaults: {
                template: 'Amwal_Pay/payment/amwalpay',
            },
            afterPlaceOrder: function (data, event) {
                var self = this;

                fullScreenLoader.startLoader();
                $.ajax({
                    type: 'POST',
                    url: url.build('amwal/checkout/smartbox'),
                    data: data,
                    success: function (response) {
                        fullScreenLoader.stopLoader();
                        if (response.success) {
                            self.callSmartBox(response.jsonData);
                        } else {
                            self.renderErrors(response);
                        }
                    },
                    error: function (response) {
                        fullScreenLoader.stopLoader();
                        self.renderErrors(response);
                    }
                });

            },
            placeOrder: function (data, event) {
                if (event) {
                    event.preventDefault();
                }

                if (additionalValidators.validate()) {
                    placeOrder = placeOrderAction(
                        this.getData(),
                        false,
                        this.messageContainer
                    );

                    $.when(placeOrder).done(this.afterPlaceOrder.bind(this));
                    return true;
                }

                return false;
            },
            getData: function () {
                return { "method": this.item.method };
            },
            renderErrors: function (data) {
                fullScreenLoader.stopLoader();
                console.log(data);
            },
            callSmartBox: function (data) {
                var callback = window.checkoutConfig.payment[this.getCode()].callback;
<<<<<<< HEAD
                var payment_view = window.checkoutConfig.payment[this.getCode()].payment_view;
=======
                var base_url = window.checkoutConfig.payment[this.getCode()].base_url;
>>>>>>> c5b80109967affd71a1cab6a7e6cc7a5502d2bfc
                SmartBox.Checkout.configure = {
                    ...data,

                    completeCallback: function (data) {
                        var dateResponse = data.data.data;
                        // Redirect with parameters
                        window.location = callback + '?amount=' + dateResponse.amount + '&currencyId=' + dateResponse.currencyId + '&customerId=' + dateResponse.customerId + '&customerTokenId=' + dateResponse.customerTokenId + '&merchantReference=' + dateResponse.merchantReference + '&responseCode=' + data.data.responseCode + '&transactionId=' + dateResponse.transactionId + '&transactionTime=' + dateResponse.transactionTime + '&secureHashValue=' + dateResponse.secureHashValue + '&message=' + dateResponse.message;
                    },

                    errorCallback: function (data) {
                        console.log('errorCallback -- ' + data);
                    },

                    cancelCallback: function () {
<<<<<<< HEAD
                        fullScreenLoader.startLoader();
                        $.ajax({
                            url: url.build('amwal/checkout/restorequote'),
                            type: 'POST',
                            success: function (response) {
                                fullScreenLoader.stopLoader();
                                window.location = url.build('checkout/cart');
                            },
                            error: function () {
                                fullScreenLoader.stopLoader();
                                window.location = url.build('checkout/cart');
                            }
                        });
=======
                       window.location = base_url;
>>>>>>> c5b80109967affd71a1cab6a7e6cc7a5502d2bfc
                    },
                };
                if (payment_view === '3') {
                    window.location.href = SmartBox.Checkout.getSmartBoxUrl();
                } else {
                    SmartBox.Checkout.showSmartBox();
                }
            }
        });
    }
);