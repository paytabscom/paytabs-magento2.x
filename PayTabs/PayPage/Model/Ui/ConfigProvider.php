<?php

namespace PayTabs\PayPage\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use PayTabs\PayPage\Gateway\Http\Client\ClientMock;

/**
 * Class ConfigProvider
 */
final class ConfigProvider implements ConfigProviderInterface
{
    const CODE_CREDITCARD = 'creditcard';
    const CODE_STCPAY = 'stcpay';
    const CODE_APPLEPAY = 'applepay';
    const CODE_OMANNET = 'omannet';
    const CODE_MADA = 'mada';
    const CODE_SADAD = 'sadad';
    const CODE_ATFAWRY = 'atfawry';
    const CODE_KNPAY = 'knpay';
    const CODE_AMEX = 'amex';
    const CODE_VALU = 'valu';

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return [
            'payment' => [
                self::CODE_CREDITCARD => [
                    'transactionResults' => [
                        ClientMock::SUCCESS => __('Success'),
                        ClientMock::FAILURE => __('Fraud')
                    ]
                ],
                self::CODE_STCPAY => [
                    'transactionResults' => [
                        ClientMock::SUCCESS => __('Success'),
                        ClientMock::FAILURE => __('Fraud')
                    ]
                ],
                self::CODE_APPLEPAY => [
                    'transactionResults' => [
                        ClientMock::SUCCESS => __('Success'),
                        ClientMock::FAILURE => __('Fraud')
                    ]
                ],
                self::CODE_OMANNET => [
                    'transactionResults' => [
                        ClientMock::SUCCESS => __('Success'),
                        ClientMock::FAILURE => __('Fraud')
                    ]
                ],
                self::CODE_MADA => [
                    'transactionResults' => [
                        ClientMock::SUCCESS => __('Success'),
                        ClientMock::FAILURE => __('Fraud')
                    ]
                ],
                self::CODE_SADAD => [
                    'transactionResults' => [
                        ClientMock::SUCCESS => __('Success'),
                        ClientMock::FAILURE => __('Fraud')
                    ]
                ],
                self::CODE_ATFAWRY => [
                    'transactionResults' => [
                        ClientMock::SUCCESS => __('Success'),
                        ClientMock::FAILURE => __('Fraud')
                    ]
                ],
                self::CODE_KNPAY => [
                    'transactionResults' => [
                        ClientMock::SUCCESS => __('Success'),
                        ClientMock::FAILURE => __('Fraud')
                    ]
                ],
                self::CODE_AMEX => [
                    'transactionResults' => [
                        ClientMock::SUCCESS => __('Success'),
                        ClientMock::FAILURE => __('Fraud')
                    ]
                ],
                self::CODE_VALU => [
                    'transactionResults' => [
                        ClientMock::SUCCESS => __('Success'),
                        ClientMock::FAILURE => __('Fraud')
                    ]
                ],
            ]
        ];
    }
}
