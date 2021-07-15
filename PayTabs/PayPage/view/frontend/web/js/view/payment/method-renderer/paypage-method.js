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
        // 'Magento_Checkout/js/action/place-order',
        'mage/url',
        'Magento_Ui/js/modal/alert'
    ],
    function (
        $,
        Component,
        quote,
        // placeOrderAction,
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

            // placeOrder: function (data, event) { },

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
                var page = this;
                $.post(
                    _urlBuilder.build('paypage/paypage/create'),
                    { quote: quoteId }
                )
                    .done(function (result) {
                        console.log(result);
                        if (result && result.success) {
                            var redirectURL = result.payment_url;
                            let framed_mode = result.framed_mode == '1';

                            if (!result.had_paid) {
                                if (framed_mode) {
                                    page.displayIframe(result.payment_url);
                                } else {
                                    $.mage.redirect(redirectURL);
                                }
                            } else {
                                alert({
                                    title: 'Previous paid amount detected',
                                    content: 'A previous payment amount has been detected for this Order',
                                    clickableOverlay: false,
                                    buttons: [
                                        {
                                            text: 'Pay anyway',
                                            class: 'action primary accept',
                                            click: function () {
                                                $.mage.redirect(redirectURL);
                                            }
                                        },
                                        {
                                            text: 'Order details',
                                            class: 'action secondary',
                                            click: function () {
                                                $.mage.redirect(_urlBuilder.build('sales/order/view/order_id/' + result.order_id + '/'));
                                            }
                                        }
                                    ]
                                });
                            }

                        } else {
                            let msg = result.message;
                            alert({
                                title: $.mage.__('Creating PayTabs page error'),
                                content: $.mage.__(msg),
                                clickableOverlay: false,
                                buttons: [{
                                    text: $.mage.__('Close'),
                                    class: 'action primary accept',

                                    click: function () {
                                        $.mage.redirect(_urlBuilder.build('checkout/cart'));
                                    }
                                }]
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
            },

            displayIframe: function (src) {
                var ifrm = document.createElement("iframe");
                ifrm.setAttribute("src", src);
                ifrm.setAttribute("frameborder", 0);
                // ifrm.style.width = "640px";
                ifrm.style.height = "450px";

                // ToDo: Append the iFrame to correct payment method
                document.getElementsByName('pnl_iframe')[0].appendChild(ifrm);
            }

        });
    }
);
