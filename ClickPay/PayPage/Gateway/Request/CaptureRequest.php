<?php

/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace ClickPay\PayPage\Gateway\Request;

use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use ClickPay\PayPage\Gateway\Http\ClickpayCore;
use ClickPay\PayPage\Gateway\Http\ClickpayEnum;
use ClickPay\PayPage\Gateway\Http\ClickpayFollowupHolder;

class CaptureRequest implements BuilderInterface
{
    /**
     * @var ConfigInterface
     */
    private $config;

    private $productMetadata;

    /**
     * @param ConfigInterface $config
     */
    public function __construct(
        ConfigInterface $config,
        \Magento\Framework\App\ProductMetadataInterface $productMetadata
    ) {
        new ClickpayCore();
        $this->config = $config;

        $this->productMetadata = $productMetadata;
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

        $transaction_id = $payment->getParentTransactionId();
        $reason = 'Admin request';

        //

        $versionMagento = $this->productMetadata->getVersion();

        //

        $currency = $payment->getOrder()->getOrderCurrencyCode();
        $order_id = $payment->getOrder()->getIncrementId();

        $pt_holder = new ClickpayFollowupHolder();
        $pt_holder
            ->set02Transaction(ClickpayEnum::TRAN_TYPE_CAPTURE, ClickpayEnum::TRAN_CLASS_ECOM)
            ->set03Cart($order_id, $currency, $amount, $reason)
            ->set30TransactionInfo($transaction_id)
            ->set99PluginInfo('Magento', $versionMagento, CLICKPAY_PAYPAGE_VERSION);

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
