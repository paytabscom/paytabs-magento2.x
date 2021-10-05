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
    const CODE_AMEX       = 'amex';
    const CODE_STCPAY     = 'stcpay';
    const CODE_APPLEPAY   = 'applepay';
    const CODE_MADA       = 'mada';
    const CODE_SADAD      = 'sadad';
    const CODE_FAWRY      = 'fawry';
    const CODE_KNPAY        = 'knet';
    const CODE_KNPAY_DEBIT  = 'knetdebit';
    const CODE_KNPAY_CREDIT = 'knetcredit';
    const CODE_AMEX       = 'amex';
    const CODE_VALU       = 'valu';


    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return [
            'payment' => [
                self::CODE_ALL => [],
                self::CODE_CREDITCARD => [],
                self::CODE_STCPAY => [],
                self::CODE_APPLEPAY => [],
                self::CODE_MADA => [],
                self::CODE_SADAD => [],
                self::CODE_AMEX => [],
                self::CODE_KNPAY_DEBIT => [],
                self::CODE_KNPAY_CREDIT => [],
            ]
        ];
    }
}
