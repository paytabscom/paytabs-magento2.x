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

            //

            isPaymentGenerated: function () {
                return this.payment_info &&
                    (this.payment_info.ready
                        && this.payment_info.status == 'generated'
                    );
            },

            isPaymentDone: function () {
                return this.payment_info &&
                    (this.payment_info.status == 'completed');
            },

            isPaymentPopupFail: function () {
                return this.isPaymentGenerated() && this.payment_info.popup_fail;
            },

            openPopup: function () {
                let redirectURL = this.payment_info.payment_url;
                if (!redirectURL) {
                    return;
                }

                let handle = window.open(redirectURL, '_blank');
                this.ptStartPaymentListining('popup', handle);

                if (!handle) {
                    console.log('No handle');
                    this.payment_info.popup_fail = true;

                    $('.payment-method._active .pt_popup_warning').show();
                    this.pt_set_status('warning', 'Popup blocked', 3);
                } else {
                    $('.payment-method._active .pt_popup_warning').hide();
                }
            },

            pendingPaymentHandler: {
                type: null,
                handler: null,
                payment_checker: null,
            },

            payment_info: {
                data: null,
                event: null,
                // Init, generating, generated, completed
                status: 'Init',
                ready: false,
                popup_fail: null,
                payment_url: null,
                status_interval: null,
            },

            //

            redirectAfterPlaceOrder: false,

            placeOrder: function (data, event) {

                if (this.isPaymentPreorder()) {
                    let force = this.isPaymentDone();

                    if (!force) {
                        console.log('placeOrder: Collect');
                        this.ptPaymentCollect(data, event);
                        return;
                    }

                    console.log('placeOrder: Force');
                }

                this._super(data, event);

                this.pt_set_status('info', 'Placing the Order', 3);
            },


            afterPlaceOrder: function () {
                let isPreorder = this.isPaymentPreorder();
                if (isPreorder) {
                    return this._super();
                }

                this.payPage();
            },


            ptPaymentCollect: function (data, event) {
                if (!this.isPaymentPreorder()) {
                    console.log('Default flow');
                    return;
                }

                this.payPage();

                this.payment_info = {
                    data: data,
                    event: event,
                    status: 'generating',
                    ready: false,
                };
            },


            ptStartPaymentListining: function (type, handler) {
                if (!type || !handler) {
                    console.log('Listener missing some information');
                    return;
                }
                let page = this;
                page.pendingPaymentHandler.type = type;
                page.pendingPaymentHandler.handler = handler;

                switch (type) {
                    case 'iframe':
                        $(handler).on('load', function () {
                            let c = $(this).contents().find('body').html();
                            console.log('iframe ', c);

                            if (c == 'Done - Loading...') {
                                page.ptAyncPaymentCompleted();
                            }
                        });
                        break;

                    case 'popup':
                        $("body").trigger('processStart');
                        page.pendingPaymentHandler.payment_checker = setInterval(function () {
                            console.log('Payment Page not yet closed ...');

                            if (handler.closed) {
                                console.log('Payment Page has been closed, continue...');

                                page.ptAyncPaymentCompleted();
                            }
                        }, 1000);

                    default:
                        break;
                }

                this.pt_set_status('info', 'Waiting for the payment to complete', 12);
            },

            ptAyncPaymentCompleted: function () {
                // this.redirectAfterPlaceOrder = true;

                this.payment_info.status = 'completed';

                this.placeOrder(this.payment_info.data, this.payment_info.event);

                this.displayIframeUI(false);

                if (this.pendingPaymentHandler.payment_checker) {
                    clearInterval(this.pendingPaymentHandler.payment_checker);
                }

                $("body").trigger('processStop');
                this.pt_set_status('info', 'Payment completed', 3);
            },

            payPage: function () {
                var page = this;

                let quoteId = quote.getQuoteId();

                $("body").trigger('processStart');
                this.pt_start_payment_ui(true);
                this.pt_set_status('info', 'Generating the payment link');

                //

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
                        page.payment_info.status = 'generated';

                        if (result && result.success) {
                            let tran_ref = result.tran_ref;
                            let redirectURL = result.payment_url;
                            let framed_mode = page.isFramed();
                            let preOrder = page.isPaymentPreorder();

                            page.payment_info.ready = true;
                            page.payment_info.payment_url = redirectURL;

                            $('.payment-method._active .paytabs_ref').text('Payment reference: ' + tran_ref);

                            if (!result.had_paid) {
                                let isFullRedirect = !framed_mode && !preOrder;

                                if (isFullRedirect) {
                                    $.mage.redirect(redirectURL);
                                }

                                var handle = null;

                                if (framed_mode) {
                                    handle = page.displayIframe(result.payment_url);
                                    page.ptStartPaymentListining('iframe', handle);
                                } else if (preOrder) {
                                    page.openPopup();
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
                        }
                    })
                    .fail(function (xhr, status, error) {
                        console.log(error, xhr);
                        // alert(status);
                    })
                    .always(function () {
                        $("body").trigger('processStop');
                        page.pt_start_payment_ui(false);
                    });
            },

            pt_start_payment_ui: function (is_start) {
                if (is_start) {
                    $('.payment-method._active .btn_place_order').hide('fast');
                } else {
                    $('.payment-method._active .btn_place_order').show('fast');
                    this.pt_set_status('', '');
                }
            },

            pt_set_status: function (status, msg, period = 5) {
                let classes = 'info error warning notice success';

                let panel = $('.payment-method._active .pt_status_message');
                $(panel)
                    .removeClass(classes)
                    .addClass(status)
                    .text(msg)
                    .show('fast');

                if (this.payment_info.status_interval) {
                    clearInterval(this.payment_info.status_interval);
                }

                this.payment_info.status_interval = setTimeout(function () {
                    $(panel).hide('fast');
                }, period * 1000);
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

                return pt_iframe;
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
