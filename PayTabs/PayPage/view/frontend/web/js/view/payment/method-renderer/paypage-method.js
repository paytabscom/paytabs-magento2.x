/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/model/quote',
        'mage/url',
        'Magento_Ui/js/modal/alert'
    ],
    function (
        $,
        Component,
        quote,
        _urlBuilder,
        alert
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
                try {
                    let quoteId = quote.getQuoteId();
                    this.payPage(quoteId);
                } catch (error) {
                    alert({
                        title: $.mage.__('AfterPlaceOrder error'),
                        content: $.mage.__(error),
                        actions: {
                            always: function () { }
                        }
                    });
                }
            },

            payPage: function (quoteId) {
                $("body").trigger('processStart');
                $.post(
                    _urlBuilder.build('paypage/paypage/create'),
                    { quote: quoteId }
                )
                    .done(function (result) {
                        console.log(result);
                        if (result && result.response_code == 4012) {
                            // if (confirm('redirect?'))
                            $.mage.redirect(result.payment_url);
                        } else {
                            let msg = result.details || result.result;
                            alert({
                                title: $.mage.__('Creating PayTabs page error'),
                                content: $.mage.__(msg),
                                clickableOverlay: false,
                                actions: {
                                    always: function () { }
                                }
                            });
                        }
                    })
                    .fail(function (err) {
                        console.log(err);
                        alert(err);
                    })
                    .complete(function () {
                        $("body").trigger('processStop');
                    });
            }

        });
    }
);
