/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/model/quote',
        'mage/url'
    ],
    function (
        Component,
        quote,
        _urlBuilder
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'PayTabs_PayPage/payment/paypage'
            },

            redirectAfterPlaceOrder: false,

            /** Returns send check to info */
            getMailingAddress: function () {
                return window.checkoutConfig.payment.checkmo.mailingAddress;
            },

            afterPlaceOrder: function () {
                let quoteId = quote.getQuoteId();
                this.payPage(quoteId);
            },

            payPage: function (quoteId) {
                jQuery.post(
                    _urlBuilder.build('paypage/paypage/create'),
                    { quote: quoteId }
                )
                    .done(function (result) {
                        console.log(result);
                        if (result && result.response_code == 4012) {
                            // if (confirm('redirect?'))
                            window.location = result.payment_url;
                        } else {
                            alert(result.result);
                        }
                    })
                    .fail(function (err) {
                        console.log(err);
                    });
            }

        });
    }
);
