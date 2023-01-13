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

                this.redirectAfterPlaceOrder = this.isPaymentPreorder();

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
             * True: Collect payment before (Payment then Place)
             * False: Default Order flow (Place then Payment)
             * @returns bool
             */
            isPaymentPreorder: function (code = null) {
                code = code || this.getCode();

                return typeof window.checkoutConfig.payment[code] !== 'undefined' &&
                    window.checkoutConfig.payment[code]['payment_preorder'] === true;
            },

            isFramed: function () {
                return typeof window.checkoutConfig.payment[this.getCode()] !== 'undefined' &&
                    window.checkoutConfig.payment[this.getCode()]['iframe_mode'] === true;
            },

            redirectAfterPlaceOrder: false,

            placeOrder: function (data, event) {
                let force = this.payment_info && this.payment_info.ready;

                if (this.isPaymentPreorder() && !force) {
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
                let isPreorder = this.isPaymentPreorder();
                if (isPreorder) {
                    return this._super();
                }

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
                if (!this.isPaymentPreorder()) {
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

            ptStartPaymentListining: function (pt_iframe) {
                let page = this;
                page.payment_info.ready = true;

                $(pt_iframe).on("load", function () {
                    let c = $(this).contents().find("body").html();
                    console.log('iframe ', c);

                    if (c == 'Done - Loading...') {
                        page.redirectAfterPlaceOrder = true;
                        page.placeOrder(page.payment_info.data, page.payment_info.event);

                        page.displayIframeUI(false);
                        delete page.payment_info;
                    }
                });
            },


            payPage: function (quoteId) {
                $("body").trigger('processStart');
                var page = this;

                let isPreorder = this.isPaymentPreorder();

                let url = 'paytabs/paypage/create';
                let payload = {
                    quote: quoteId
                };

                if (isPreorder) {
                    url = 'paytabs/paypage/createpre';
                    payload = {
                        quote: quoteId,
                        vault: Number(this.vaultEnabler.isActivePaymentTokenEnabler()),
                        method: this.getCode()
                    };
                }

                $.post(
                    _urlBuilder.build(url),
                    payload
                )
                    .done(function (result) {
                        // console.log(result);
                        if (result && result.success) {
                            try {
                                let tran_ref = result.tran_ref;
                                $('.payment-method._active .paytabs_ref').text('Payment reference: ' + tran_ref);
                            } catch (error) {
                                console.log(error);
                            }
                            var redirectURL = result.payment_url;
                            let framed_mode = page.isFramed() || page.isPaymentPreorder();

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
                            let msg = result.result || result.message;
                            alert({
                                title: $.mage.__('Creating PayTabs page error'),
                                content: $.mage.__(msg),
                                clickableOverlay: isPreorder,
                                buttons: [{
                                    text: $.mage.__('Close'),
                                    class: 'action primary accept',

                                    click: function () {
                                        if (isPreorder) {
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
                    .always(function () {
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

                // Append the iFrame to correct payment method
                $(pt_iframe).appendTo($('.payment-method._active .paytabs_iframe'));

                // Hide the Address & Actions sections
                this.displayIframeUI(true);

                let isPreorder = this.isPaymentPreorder();
                if (isPreorder) {
                    this.ptStartPaymentListining(pt_iframe);
                }
            },

            displayIframeUI: function (show_iframe) {
                let classes = [
                    '.payment-method._active .payment-method-billing-address',
                    '.payment-method._active .actions-toolbar',
                    '.payment-method._active .pt_vault'
                ];

                let code = this.getCode();
                let iframe_id = '#pt_iframe_' + code;
                let loader_id = '#pt_loader_' + code;

                $(iframe_id).on("load", function () {
                    $(loader_id).hide('fast');
                });

                let classes_str = classes.join();

                if (show_iframe) {
                    // Hide the Address & Actions sections
                    $(classes_str).hide('fast');
                    $(loader_id).show('fast');
                } else {
                    $(classes_str).show('fast');

                    $(iframe_id).remove();
                }
            },

            //

            getIcon: function () {
                if (this.hasIcon())
                    return window.checkoutConfig.payment[this.getCode()].icon;
            },

            hasIcon: function () {
                return typeof window.checkoutConfig.payment[this.getCode()] !== 'undefined' &&
                    typeof window.checkoutConfig.payment[this.getCode()].icon !== 'undefined';
            },

            shippingExcluded: function () {
                let isEnabled = typeof window.checkoutConfig.payment[this.getCode()] !== 'undefined' &&
                    window.checkoutConfig.payment[this.getCode()].exclude_shipping === true;

                if (isEnabled) {
                    try {
                        let totals = quote.totals();
                        let hasShippingFees = totals.shipping_amount > 0;

                        return hasShippingFees;
                    } catch (error) {
                        console.log(error);
                    }
                }

                return false;
            },

            shippingTotal: function () {
                try {
                    let totals = quote.totals();
                    if (this.useOrderCurrency()) {
                        return totals.shipping_amount + ' ' + totals.quote_currency_code;
                    } else {
                        return totals.base_shipping_amount + ' ' + totals.base_currency_code;
                    }
                } catch (error) {
                    console.log(error);
                }
                return quote.totals().shipping_amount;
            },

            useOrderCurrency: function () {
                return typeof window.checkoutConfig.payment[this.getCode()] !== 'undefined' &&
                    window.checkoutConfig.payment[this.getCode()].currency_select == 'order_currency';
            },
        });
    }
);
