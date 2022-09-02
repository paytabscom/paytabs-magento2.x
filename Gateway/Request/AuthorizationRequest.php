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
use PayTabs\PayPage\Gateway\Http\PaytabsHelper;
use PayTabs\PayPage\Model\Adminhtml\Source\CurrencySelect;

class AuthorizationRequest implements BuilderInterface
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
        $use_order_currency = CurrencySelect::UseOrderCurrency($payment);

        $preorder = (bool) $paymentMethod->getConfigData('payment_preorder');

        // $this->config->getValue('merchant_email');

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

        if (!$preorder) {
            PaytabsHelper::log("Auth is not working with Default mode!, Order [{$order_id}], Amount {$amount} {$currency}", 3);
            throw new \LogicException('Auth is not working with Default mode!');
        } else {
            // Collect the payment before placing the Order (It is Sale not Capture)

            $transaction_registered = $payment->getAdditionalInformation('pt_registered_transaction');

            //

            PaytabsHelper::log("Validate Auth!, Order [{$order_id}], Amount {$amount} {$currency}, Transaction {$transaction_registered}", 1);

            //

            if (!$transaction_registered) {
                PaytabsHelper::log("Validate Auth!, tran_ref should be provided", 3);
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
            'is_verify' => $preorder
        ];

        return $req_data;

        /** @var PaymentDataObjectInterface $payment */
        // $payment = $buildSubject['payment'];
        // $order = $payment->getOrder();
        // $address = $order->getShippingAddress();

        // $transactionResult = $payment->getAdditionalInformation('transaction_result');

        // $values = $this->prepare_order($order, 'creditcard');

        // return $values;

        // return [
        //     'TXN_TYPE' => 'A',
        //     'INVOICE' => $order->getOrderIncrementId(),
        //     'AMOUNT' => $order->getGrandTotalAmount(),
        //     'CURRENCY' => $order->getCurrencyCode(),
        //     'EMAIL' => $address->getEmail(),
        //     'MERCHANT_KEY' => $this->config->getValue(
        //         'merchant_gateway_key',
        //         $order->getStoreId()
        //     )
        // ];
    }
}
