<?php

/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace PayTabs\PayPage\Gateway\Request;

use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use PayTabs\PayPage\Gateway\Http\PaytabsCore2;
use PayTabs\PayPage\Gateway\Http\PaytabsRefundHolder;

class RefundRequest implements BuilderInterface
{
    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @param ConfigInterface $config
     */
    public function __construct(ConfigInterface $config)
    {
        new PaytabsCore2();
        $this->config = $config;
    }

    /**
     * Builds ENV request
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        if (
            !isset($buildSubject['payment'])
            || !$buildSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        /** @var PaymentDataObjectInterface $paymentDO */
        $paymentDO = $buildSubject['payment'];
        $amount = $buildSubject['amount'];

        // $order = $paymentDO->getOrder();
        $payment = $paymentDO->getPayment();

        if (!$payment instanceof OrderPaymentInterface) {
            throw new \LogicException('Order payment should be provided.');
        }

        $paymentMethod = $payment->getMethodInstance();
        // PT
        $merchant_id = $paymentMethod->getConfigData('profile_id');
        $merchant_key = $paymentMethod->getConfigData('server_key');
        $endpoint = $paymentMethod->getConfigData('endpoint');

        // $this->config->getValue('merchant_email');

        $transaction_id = $payment->getLastTransId();
        $reason = 'Admin request';

        //

        $currency = $payment->getOrder()->getOrderCurrencyCode();
        $order_id = $payment->getOrder()->getIncrementId();

        $pt_holder = new PaytabsRefundHolder();
        $pt_holder
            ->set01RefundInfo($amount, $currency)
            ->set02Transaction($order_id, $transaction_id, $reason);

        $values = $pt_holder->pt_build();

        $req_data = [
            'params' => $values,
            'auth' => [
                'merchant_id'  => $merchant_id,
                'merchant_key' => $merchant_key,
                'endpoint'     => $endpoint,
            ]
        ];

        return $req_data;
    }
}
