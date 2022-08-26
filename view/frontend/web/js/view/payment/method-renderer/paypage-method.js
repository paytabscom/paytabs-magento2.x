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
                template: 'PayTabs_PayPage/payment/paypage'
            },

            initialize: function () {
                var self = this;

                self._super();
                this.vaultEnabler = new VaultEnabler();
                this.vaultEnabler.setPaymentCode(this.getVaultCode());

                this.redirectAfterPlaceOrder = !this.canInitialize();

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

            /**
             * True: Default Order flow (Place then Payment)
             * @returns bool
             */
            canInitialize: function (code = null) {
                code = code || this.getCode();

                return typeof window.checkoutConfig.payment[code] !== 'undefined' &&
                    window.checkoutConfig.payment[code]['can_initialize'] === true;
            },

            isFramed: function () {
                return typeof window.checkoutConfig.payment[this.getCode()] !== 'undefined' &&
                    window.checkoutConfig.payment[this.getCode()]['iframe_mode'] === true;
            },

            redirectAfterPlaceOrder: false,

            /** Returns send check to info */
            getMailingAddress: function () {
                return window.checkoutConfig.payment.checkmo.mailingAddress;
            },

            placeOrder: function (data, event) {
                let force = this.payment_info && this.payment_info.ready;

                if (!this.canInitialize() && !force) {
                    console.log('placeOrder: Collect');
                    this.ptPaymentCollect(data, event);
                    return;
                }

                if (force) {
                    console.log('placeOrder: Force');
                }
                this._super(data, event);
            },

            afterPlaceOrder: function () {
                try {
                    let quoteId = quote.getQuoteId();

                    this.pt_start_payment_ui(true);

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


            ptPaymentCollect: function (data, event) {
                if (this.canInitialize()) {
                    console.log('Default flow');
                    return;
                }
                try {
                    let quoteId = quote.getQuoteId();

                    this.pt_start_payment_ui(true);

                    this.payPage(quoteId);

                    this.payment_info = {
                        data: data,
                        event: event,
                        ready: false
                    };
                } catch (error) {
                    alert({
                        title: $.mage.__('PaymentCollect error'),
                        content: $.mage.__(error),
                        actions: {
                            always: function () { }
                        }
                    });
                }

                return false;
            },

            ptStartPaymentListining: function (stop = false) {
                if (stop) {
                    clearInterval(page.iframe_listining);
                    return;
                }

                var page = this;
                page.payment_info.ready = true;

                page.iframe_listining = setInterval(() => {
                    let c = $("#pt_iframe_" + page.getCode()).contents().find("body").html();
                    console.log(c);

                    if (c == 'Done - Loading...') {
                        clearInterval(page.iframe_listining);
                        page.placeOrder(page.payment_info.data, page.payment_info.event);

                        page.displayIframeUI(false);
                        delete page.payment_info;
                    }

                }, 3000);
            },


            payPage: function (quoteId) {
                $("body").trigger('processStart');
                var page = this;

                let isOrder = this.canInitialize();

                let url = 'paytabs/paypage/create';
                if (!isOrder) {
                    url = 'paytabs/paypage/createpre';
                }

                $.post(
                    _urlBuilder.build(url),
                    { quote: quoteId }
                )
                    .done(function (result) {
                        // console.log(result);
                        if (result && result.success) {
                            var redirectURL = result.payment_url;
                            let framed_mode = page.isFramed() || !page.canInitialize();

                            if (!result.had_paid) {
                                if (framed_mode) {
                                    page.displayIframe(result.payment_url);
                                    if (!isOrder) {
                                        page.ptStartPaymentListining(false);
                                    }
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
                            let msg = result.result || result.message;
                            alert({
                                title: $.mage.__('Creating PayTabs page error'),
                                content: $.mage.__(msg),
                                clickableOverlay: !isOrder,
                                buttons: [{
                                    text: $.mage.__('Close'),
                                    class: 'action primary accept',

                                    click: function () {
                                        if (!isOrder) {
                                        } else {
                                            $.mage.redirect(_urlBuilder.build('checkout/cart'));
                                        }
                                    }
                                }]
                            });

                            page.pt_start_payment_ui(false);
                        }
                    })
                    .fail(function (xhr, status, error) {
                        console.log(error, xhr);
                        // alert(status);
                        page.pt_start_payment_ui(false);
                    })
                    .complete(function () {
                        $("body").trigger('processStop');
                        page.pt_start_payment_ui(false);
                    });
            },

            pt_start_payment_ui: function (is_start) {
                if (is_start) {
                    $('.payment-method._active .btn_place_order').hide('fast');
                    $('.payment-method._active .btn_pay').show('fast');
                } else {
                    $('.payment-method._active .btn_place_order').show('fast');
                    $('.payment-method._active .btn_pay').hide('fast');
                }
            },

            displayIframe: function (src) {
                let pt_iframe = $('<iframe>', {
                    src: src,
                    frameborder: 0,
                    id: 'pt_iframe_' + this.getCode(),
                }).css({
                    'min-width': '400px',
                    'width': '100%',
                    'height': '450px'
                });

                // Hide the Address & Actions sections
                this.displayIframeUI(true);

                // Append the iFrame to correct payment method
                $(pt_iframe).appendTo($('.payment-method._active .paytabs_iframe'));
            },

            displayIframeUI: function (is_iframe) {
                let classes = [
                    '.payment-method._active .payment-method-billing-address',
                    '.payment-method._active .actions-toolbar',
                    '.payment-method._active .pt_vault'
                ];

                let classes_str = classes.join();

                if (is_iframe) {
                    // Hide the Address & Actions sections
                    $(classes_str).hide('fast');
                } else {
                    $(classes_str).show('fast');

                    $('#pt_iframe_' + this.getCode()).remove();
                }
            },

        });
    }
);
