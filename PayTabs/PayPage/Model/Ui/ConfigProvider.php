<?php

namespace PayTabs\PayPage\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use PayTabs\PayPage\Gateway\Http\Client\ClientMock;

/**
 * Class ConfigProvider
 */
final class ConfigProvider implements ConfigProviderInterface
{
    const CODE_ALL        = 'all';
    const CODE_CREDITCARD = 'creditcard';
    const CODE_STCPAY     = 'stcpay';
    const CODE_APPLEPAY   = 'applepay';
    const CODE_OMANNET    = 'omannet';
    const CODE_MADA       = 'mada';
    const CODE_SADAD      = 'sadad';
    const CODE_ATFAWRY    = 'atfawry';
    const CODE_KNPAY      = 'knet';
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
                self::CODE_OMANNET => [],
                self::CODE_MADA => [],
                self::CODE_SADAD => [],
                self::CODE_ATFAWRY => [],
                self::CODE_KNPAY => [],
                self::CODE_AMEX => [],
                self::CODE_VALU => [],
            ]
        ];
    }
}
