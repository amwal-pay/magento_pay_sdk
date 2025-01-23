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
                                console.log("afterPlaceOrder:success");
                                console.log(response)
                                self.callSmartBox(response.jsonData);
                            } else {
                                console.log("afterPlaceOrder:error");
                                console.log(response)
                                self.renderErrors(response);
                            }
                        },
                        error: function (response) {
                            console.log("afterPlaceOrder:error");
                            console.log(response)
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
                return {"method": this.item.method};
            },
            renderErrors: function (data) {
                fullScreenLoader.stopLoader();
                console.log(data);
            },
            callSmartBox: function (data) {
                var callback = window.checkoutConfig.payment[this.getCode()].callback;
                SmartBox.Checkout.configure = {
                    ...data,
    
                    completeCallback: function (data) {
                        var dateResponse = data.data.data;
    
                        // Redirect with parameters
                        window.location = callback + '?ordercomplete=true&amount=' + dateResponse.amount + '&currencyId=' + dateResponse.currencyId + '&customerId=' + dateResponse.customerId + '&customerTokenId=' + dateResponse.customerTokenId + '&merchantReference=' + dateResponse.merchantReference + '&responseCode=' + data.data.responseCode + '&transactionId=' + dateResponse.transactionId + '&transactionTime=' + dateResponse.transactionTime + '&secureHashValue=' + dateResponse.secureHashValue;
                    },
    
                    errorCallback: function () {
                        // setTimeout(() => {
                        //     alert("An error has occurred.");
                        // }, 3000);
                    },
    
                    cancelCallback: function () {
                        // setTimeout(() => {
                        //     alert("The operation was cancelled.");
                        // }, 3000);
                    },
                };
    
                // Show the SmartBox UI
                SmartBox.Checkout.showSmartBox();
            }
        });
    }
);

