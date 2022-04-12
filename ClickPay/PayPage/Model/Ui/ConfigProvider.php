<?php

namespace ClickPay\PayPage\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;

/**
 * Class ConfigProvider
 */
final class ConfigProvider implements ConfigProviderInterface
{
    const CODE_ALL        = 'all';
    const CODE_CREDITCARD = 'creditcard';
    const CODE_STCPAY     = 'stcpay';
    const CODE_APPLEPAY   = 'applepay';
    const CODE_MADA       = 'mada';
    const CODE_SADAD      = 'sadad';
    const CODE_AMEX       = 'amex';

    const CODE_VAULT_ALL = 'ClickPay_all_vault';


    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return [
            'payment' => [
                self::CODE_ALL => [
                    'vault_code' => self::CODE_VAULT_ALL
                ],
                self::CODE_CREDITCARD => [],
                self::CODE_STCPAY => [],
                self::CODE_APPLEPAY => [],
                self::CODE_MADA => [],
                self::CODE_SADAD => [],
                self::CODE_AMEX => [],

                self::CODE_VAULT_ALL => [
                    'vault_code' => self::CODE_VAULT_ALL
                ],
            ]
        ];
    }
}
