<?php

/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace PayTabs\PayPage\Gateway\Request;

use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use PayTabs\PayPage\Gateway\Http\PaytabsCore;
use PayTabs\PayPage\Gateway\Http\PaytabsEnum;
use PayTabs\PayPage\Gateway\Http\PaytabsFollowupHolder;
use PayTabs\PayPage\Gateway\Http\PaytabsHelper;
use PayTabs\PayPage\Model\Adminhtml\Source\CurrencySelect;

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
        new PaytabsCore();
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
        $use_order_currency = CurrencySelect::UseOrderCurrency($payment);

        // $preorder = (bool) $paymentMethod->getConfigData('payment_preorder');
        $paymentAction = $paymentMethod->getConfigPaymentAction();

        // $this->config->getValue('merchant_email');

        //

        $admin_request = ($paymentAction == 'authorize');

        //

        if ($use_order_currency) {
            $currency = $payment->getOrder()->getOrderCurrencyCode();
            $amount = $payment->getOrder()->getBaseCurrency()->convert($amount, $currency);
            $amount = $payment->formatAmount($amount, true);
        } else {
            $currency = $payment->getOrder()->getBaseCurrencyCode();
        }

        //

        $order_id = $payment->getOrder()->getIncrementId();

        if ($admin_request) {
            PaytabsHelper::log("Init Capture!, Order [{$order_id}], Amount {$amount} {$currency}", 1);

            //

            $transaction_id = $payment->getParentTransactionId();
            $reason = 'Admin request';

            $order_id .= ' - ' . date('U'); // prevents Duplicate request issue

            //

            $versionMagento = $this->productMetadata->getVersion();

            //

            $pt_holder = new PaytabsFollowupHolder();
            $pt_holder
                ->set02Transaction(PaytabsEnum::TRAN_TYPE_CAPTURE, PaytabsEnum::TRAN_CLASS_ECOM)
                ->set03Cart($order_id, $currency, $amount, $reason)
                ->set30TransactionInfo($transaction_id)
                ->set99PluginInfo('Magento', $versionMagento, PAYTABS_PAYPAGE_VERSION);

            $values = $pt_holder->pt_build();
        } else {
            // Collect the payment before placing the Order (It is Sale not Capture)

            $transaction_registered_done = (bool)$payment->getAdditionalInformation('pt_registered_transaction_done');
            $transaction_registered = $payment->getAdditionalInformation('pt_registered_transaction');

            //

            PaytabsHelper::log("Validate Capture!, Order [{$order_id}], Amount {$amount} {$currency}, Transaction {$transaction_registered}", 1);

            //

            if ($transaction_registered_done) {
                PaytabsHelper::log("Validate Capture!, Transaction [$transaction_registered] already done", 3);
                throw new \InvalidArgumentException('Payment transaction already done');
            }

            if (!$transaction_registered) {
                PaytabsHelper::log("Validate Capture!, tran_ref should be provided", 3);
                throw new \InvalidArgumentException('Payment tran_ref should be provided');
            }

            $values = [
                'tran_ref' => $transaction_registered
            ];
        }

        $req_data = [
            'params' => $values,
            'auth' => [
                'merchant_id'  => $merchant_id,
                'merchant_key' => $merchant_key,
                'endpoint'     => $endpoint,
            ],
            'is_verify' => !$admin_request
        ];

        return $req_data;
    }
}
