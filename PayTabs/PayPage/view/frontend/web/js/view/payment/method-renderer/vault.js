
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
                return this.publicHash.substr(-7);
            },

            getCardType: function () {
                return this.details.card_type;
            },

        });
    }
);
