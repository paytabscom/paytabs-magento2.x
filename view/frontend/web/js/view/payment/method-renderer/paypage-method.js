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

                /**
                 * Default flow:
                 *  Place the Order -> Collect the payment
                 * Require payment prior order flow:
                 *  Collect the payment -> Place the order
                 */
                this.redirectAfterPlaceOrder = this.isPaymentPreOrder();

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
            isPaymentPreOrder: function (code = null) {
                code = code || this.getCode();

                return typeof window.checkoutConfig.payment[code] !== 'undefined' &&
                    window.checkoutConfig.payment[code]['payment_preorder'] === true;
            },

            isFramed: function () {
                return typeof window.checkoutConfig.payment[this.getCode()] !== 'undefined' &&
                    window.checkoutConfig.payment[this.getCode()]['iframe_mode'] === true;
            },

            //

            /**
             * True if the payment link (PP payment page) has been generated
             * @returns bool
             */
            isPaymentGenerated: function () {
                return this.payment_info &&
                    (this.payment_info.ready
                        && this.payment_info.status == 'generated'
                    );
            },

            /**
             * True if the payment has been completed by the customer
             * It does not tell if the payment succeed or failed
             * @returns bool
             */
            isPaymentDone: function () {
                return this.payment_info &&
                    (this.payment_info.status == 'completed');
            },

            /**
             * The class is external
             * It uses variables from the window object
             */
            paymentObserver: {
                /**
                 * True if the payment has been opened
                 * But not yet completed
                 * @returns bool
                 */
                isWaiting: function () {
                    return (window.pt_waiting_payment == true);
                },

                init: function () {
                    if (!window.pt_waiting_payment_init) {
                        $(window).on("beforeunload", function () {
                            if (window.pt_waiting_payment) {
                                return confirm("There is a waiting payment already opened, kindly complete/close the pending payment request?");
                            }
                        });
                        window.pt_waiting_payment_init = true;
                    }
                    return this;
                },

                confirm: function () {
                    if (this.isWaiting()) {
                        return confirm("There is a waiting payment already opened (" + window.pt_waiting_payment_code + "), kindly complete/close the pending payment request?");
                    }
                    return true;
                },

                set: function (code = null) {
                    window.pt_waiting_payment = true;
                    window.pt_waiting_payment_code = code;
                },

                clear: function () {
                    window.pt_waiting_payment = false;
                },
            },

            /**
             * True if the payment completed and the Place order logic attempt
             * @returns bool
             */
            isPlaceOrderAttempt: function () {
                return this.isPaymentDone() &&
                    (this.payment_info.place_order_attempt);
            },

            /**
             * True if the payment generated but the browser failed tp open the Popup
             * This happens in case (PreOrder = true & iFrame = false)
             * @returns bool
             */
            isPaymentPopupFail: function () {
                return this.isPaymentGenerated() && this.payment_info.popup_fail;
            },

            openPopup: function () {
                let redirectURL = this.payment_info.payment_url;
                if (!redirectURL) {
                    return;
                }

                let handle = window.open(redirectURL, '_blank');
                this.ptStartPaymentListening('popup', handle);

                if (!handle) {
                    console.log('No handle');
                    this.payment_info.popup_fail = true;

                    $('.payment-method._active .pt_popup_warning').show();
                    this.pt_set_status('warning', 'Popup blocked', false, 3);
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
                place_order_attempt: null,
            },

            //

            redirectAfterPlaceOrder: false,

            placeOrder: function (data, event) {
                if (!this.paymentObserver.confirm()) {
                    return;
                }
                this.paymentObserver.clear();

                if (this.isPaymentPreOrder()) {
                    let force = false;

                    if (this.isPaymentDone() && !this.isPlaceOrderAttempt()) {
                        force = true;
                    }

                    if (!force) {
                        console.log('placeOrder: Collect');
                        this.ptPaymentCollect(data, event);
                        return;
                    }

                    console.log('placeOrder: Force');
                    this.payment_info.place_order_attempt = true;
                }

                this._super(data, event);

                this.pt_set_status('info', 'Placing the Order', true, 3);
            },


            afterPlaceOrder: function () {
                let isPreOrder = this.isPaymentPreOrder();
                if (isPreOrder) {
                    return this._super();
                }

                this.payPage();
            },


            ptPaymentCollect: function (data, event) {
                if (!this.isPaymentPreOrder()) {
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

            /**
             * Waiting for the payment to be completed by the customer
             * Trigger ptAsyncPaymentCompleted() once the payment completed.
             * iFrame:
             *  Checks every time the iframe content is changed, until a predefined content is met
             * Popup:
             *  Checks periodically for the handler (the new tab window), until it is closed
             * @param {string} type : 'iframe' or 'popup'
             * @param handler the iframe container or the window handler (popup)
             * @returns void
             */
            ptStartPaymentListening: function (type, handler) {
                if (!type || !handler) {
                    console.log('Listener missing some information');
                    return;
                }
                let page = this;
                let preOrder = page.isPaymentPreOrder();

                page.pendingPaymentHandler.type = type;
                page.pendingPaymentHandler.handler = handler;

                switch (type) {
                    case 'iframe':
                        if (preOrder) {
                            $(handler).on('load', function () {
                                let c = $(this).contents().find('body').html();
                                console.log('iframe ', c);

                                if (c == 'Done - Loading...') {
                                    page.ptAsyncPaymentCompleted();
                                }
                            });
                        }
                        break;

                    case 'popup':
                        $("body").trigger('processStart');
                        page.pendingPaymentHandler.payment_checker = setInterval(function () {
                            console.log('Payment Page not yet closed ...');

                            if (handler.closed) {
                                console.log('Payment Page has been closed, continue...');

                                page.ptAsyncPaymentCompleted();
                            }
                        }, 1000);

                    default:
                        break;
                }

                this.pt_set_status('info', 'Waiting for the payment to complete', false, 18);

                this.payment_info.status = 'waiting_payment';
                if (preOrder) {
                    this.paymentObserver.init().set(this.getTitle());
                }
            },

            /**
             * Triggered after an indicator that the customer completes the payment
             * Continue placing the order routine
             */
            ptAsyncPaymentCompleted: function () {
                // this.redirectAfterPlaceOrder = true;

                this.payment_info.status = 'completed';
                this.payment_info.place_order_attempt = false;
                this.paymentObserver.clear();

                this.pt_set_status('info', 'Payment completed', false, 3);

                this.placeOrder(this.payment_info.data, this.payment_info.event);

                this.displayIframeUI(false);

                if (this.pendingPaymentHandler.payment_checker) {
                    clearInterval(this.pendingPaymentHandler.payment_checker);
                }

                $("body").trigger('processStop');
            },

            /**
             * The main function that communicates with the backend to generate the payment page
             * The option PreOrder affects the logic (change the endpoint and params)
             * Generates the Payment page, and then:
             * 1. PreOrder = false & iFrame = false
             *  Full redirect (normal behavior)
             * 2. PreOrder = false & iFrame = true
             *  Place the Order (Pending payment status) -> display the payment page inside iFrame.
             * 3. PreOrder = true & iFrame = true
             *  Stop the place order routine -> generate payment page -> display the payment page in iFrame
             *  -> Collect the payment -> Continue the place routine
             *  (Backend will check the transaction status (success or fail))
             * 4. PreOrder = true & iFrame = false
             *  Stop the place order routine -> generate payment page
             *  -> Open a Popup (Either new browser tab, or new Window depends on the browser settings)
             *  -> Collect the payment -> Continue the place routine
             *  (Backend will check the transaction status (success or fail))
             */
            payPage: function () {
                var page = this;

                let quoteId = quote.getQuoteId();

                $("body").trigger('processStart');
                this.pt_start_payment_ui(true);
                this.pt_set_status('info', (this.isPlaceOrderAttempt() ? 'Re-' : '') + 'Generating the payment link');

                //

                let isPreOrder = this.isPaymentPreOrder();

                let url = 'paytabs/paypage/create';
                let payload = {
                    quote: quoteId
                };

                if (isPreOrder) {
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
                            let preOrder = page.isPaymentPreOrder();

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
                                    page.ptStartPaymentListening('iframe', handle);
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
                                clickableOverlay: isPreOrder,
                                buttons: [{
                                    text: $.mage.__('Close'),
                                    class: 'action primary accept',

                                    click: function () {
                                        if (isPreOrder) {
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

            /**
             * Do some changes to UI elements once the payment logic started or stopped
             * @param {bool} is_start Payment check started
             */
            pt_start_payment_ui: function (is_start) {
                if (is_start) {
                    $('.payment-method._active .btn_place_order').hide('fast');
                } else {
                    $('.payment-method._active .btn_place_order').show('fast');
                    // this.pt_set_status('', '');
                }
            },

            /**
             * Display a message for a specific duration with specific status
             * @param {string} status css class (info, error, warning, notice, success)
             * @param {string} msg the message
             * @param {bool} append the new message to any existing message
             * @param {number} period numbers of seconds
             */
            pt_set_status: function (status, msg, append = false, period = 5) {
                let classes = 'info error warning notice success';

                let panel = $('.payment-method._active .pt_status_message');
                $(panel)
                    .removeClass(classes)
                    .addClass(status)
                    .show('fast');

                if (this.payment_info.status_interval) {
                    clearInterval(this.payment_info.status_interval);
                    this.payment_info.status_interval = null;
                } else {
                    append = false;
                }

                if (append) {
                    let separator = $(panel).text().trim() != '' ? ', ' : '';
                    $(panel).append(separator + msg)
                } else {
                    $(panel).text(msg)
                }

                this.payment_info.status_interval = setTimeout(function () {
                    $(panel).hide('fast');
                }, period * 1000);
            },

            /**
             * Create a new iFrame element with the payment link as the src
             * @param {url} src the payment page link
             * @returns element iFrame
             */
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

            /**
             * Control the UI elements of the payment method based on the iFrame Show or Hide
             * @param {bool} show_iframe
             */
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

            /**
             * True if the exclude shipping fees is set to true & there is a shipping fees for this quote
             * @returns bool
             */
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
