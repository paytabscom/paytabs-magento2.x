<?php

/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace PayTabs\PayPage\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use PayTabs\PayPage\Gateway\Http\PaytabsEnum;
use PayTabs\PayPage\Gateway\Http\PaytabsHelper;
use PayTabs\PayPage\Model\Adminhtml\Source\CurrencySelect;

class FollowupValidator extends AbstractValidator
{
    const RESULT_CODE = 'RESULT_CODE';

    /**
     * Performs validation of result code
     *
     * @param array $validationSubject
     * @return ResultInterface
     */
    public function validate(array $validationSubject)
    {
        if (!isset($validationSubject['response']) || !is_array($validationSubject['response'])) {
            throw new \InvalidArgumentException('Response does not exist');
        }

        $response = $validationSubject['response'];

        $success = $response['success'];
        // $pending_success = $response['pending_success'];
        $message = $response['message'];

        $_order_id = @$response['cart_id'];
        PaytabsHelper::log("Payment result, Order {$_order_id}, [{$success} {$message}]", 1);

        $is_verify = array_key_exists('is_verify', $response) ? $response['is_verify'] : false;

        if ($is_verify) {
            $on_hold = $response['is_on_hold'];
            $is_pending = $response['is_pending'];

            if ($success || $on_hold /*|| $is_pending*/) {
                $success = $this->_pt_validate($validationSubject, $response);

                if (!$success) {
                    $tran_ref = $response['tran_ref'];

                    // Fraud
                    PaytabsHelper::log("Payment does not match, Order {$_order_id}, [{$message}] [$tran_ref]", 2);

                    $message = 'Unable to process your request';
                }
            }
        }

        return $this->createResult(
            $success,
            [$message]
        );
    }

    private function _pt_validate($buildSubject, $pt_response)
    {
        $paymentDO = $buildSubject['payment'];
        $amount = $buildSubject['amount'];

        $payment = $paymentDO->getPayment();
        $order = $payment->getOrder();

        $paymentMethod = $payment->getMethodInstance();
        $exclude_shipping = (bool) $paymentMethod->getConfigData('exclude_shipping');

        $use_order_currency = CurrencySelect::UseOrderCurrency($payment);

        if ($use_order_currency) {
            $currency = $order->getOrderCurrencyCode();
            $amount = $order->getBaseCurrency()->convert($amount, $currency);
            $amount = $payment->formatAmount($amount, true);

            $shippingAmount = $order->getShippingAmount();
        } else {
            $currency = $order->getBaseCurrencyCode();

            $shippingAmount = $order->getBaseShippingAmount();
        }

        if ($exclude_shipping) {
            $amount -= $shippingAmount;
            $amount = $payment->formatAmount($amount, true);
        }

        // $order_id = $payment->getOrder()->getIncrementId();
        $quote_id = $payment->getOrder()->getQuoteId();

        //

        $_pt_quote_id = $pt_response['cart_id'];
        $_pt_quote_id = substr($_pt_quote_id, 1);

        $_same_id =
            $_pt_quote_id == $quote_id;

        $_same_type = PaytabsEnum::TransAreSame(
            $pt_response['tran_type'],
            $pt_response['pt_type']
        );

        // $pt_response['profileId'];

        $_same_amount =
            $pt_response['cart_amount'] == $amount
            && $pt_response['cart_currency'] == $currency;

        //

        return $_same_id /*&& $_same_type*/ && $_same_amount;
    }
}
