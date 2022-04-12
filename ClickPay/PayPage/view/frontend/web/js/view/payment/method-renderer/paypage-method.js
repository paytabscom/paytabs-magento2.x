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
        'Magento_Ui/js/modal/alert',
        'Magento_Vault/js/view/payment/vault-enabler'
    ],
    function (
        $,
        Component,
        quote,
        // placeOrderAction,
        _urlBuilder,
        alert,
        VaultEnabler
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'ClickPay_PayPage/payment/paypage'
            },

            initialize: function () {
                var self = this;

                self._super();
                this.vaultEnabler = new VaultEnabler();
                this.vaultEnabler.setPaymentCode(this.getVaultCode());

                return self;
            },

            getData: function () {
                var data = {
                    'method': this.getCode(),
                    'additional_data': {
                    }
                };

                data['additional_data'] = _.extend(data['additional_data'], this.additionalData);
                this.vaultEnabler.visitAdditionalData(data);

                return data;
            },

            isVaultEnabled: function () {
                return this.vaultEnabler.isVaultEnabled();
            },

            getVaultCode: function () {
                return window.checkoutConfig.payment[this.getCode()].vault_code;
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

                    $('.payment-method._active .btn_place_order').hide('fast');
                    $('.payment-method._active .btn_pay').show('fast');

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
                    _urlBuilder.build('ClickPay/paypage/create'),
                    { quote: quoteId }
                )
                    .done(function (result) {
                        // console.log(result);
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
                                title: $.mage.__('Creating ClickPay page error'),
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
                let pt_iframe = $('<iframe>', {
                    src: src,
                    frameborder: 0,
                }).css({
                    'min-width': '400px',
                    'width': '100%',
                    'height': '450px'
                });

                // Hide the Address & Actions sections
                $('.payment-method._active .payment-method-billing-address').hide('fast');
                $('.payment-method._active .actions-toolbar').hide('fast');

                // Append the iFrame to correct payment method
                $(pt_iframe).appendTo($('.payment-method._active .ClickPay_iframe'));
            }

        });
    }
);
