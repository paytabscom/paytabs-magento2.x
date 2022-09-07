
define(
    [
        'jquery',
        'Magento_Vault/js/view/payment/method-renderer/vault'
    ],
    function (
        $,
        VaultComponent
    ) {
        'use strict';

        // console.log(this);

        return VaultComponent.extend({
            defaults: {
                template: 'Magento_Vault/payment/form'
            },

            getExpirationDate: function () {
                // console.log(this);
                return this.details.expiryMonth + '/' + this.details.expiryYear;
            },

            getTitle: function () {
                return this.item.title;
            },

            getToken: function () {
                return this.publicHash;
            },

            getMaskedCard: function () {
                return this.details.payment_description.substr(-7);
            },

            getCardType: function () {
                var card_type = '';

                try {
                    var icons = window.checkoutConfig.payment.ccform.icons;
                    for (let icon in icons) {
                        var title = icons[icon].title.replace(/\s+/g, '');
                        if (this.details.card_scheme == title) {
                            card_type = icon;
                            break;
                        }
                    }
                } catch (error) {
                    console.log(error);
                }

                return card_type;
            },

        });
    }
);
