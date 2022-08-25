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

        $is_verify = $response['is_verify'];

        if ($success && $is_verify) {
            $success = $this->_pt_validate($validationSubject, $response);
            if (!$success) {
                // Fraud
                PaytabsHelper::log("Payment result, Order {$_order_id}, [{$success} {$message}]", 2);

                $message = 'Unable to process your request';
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

        $use_order_currency = CurrencySelect::UseOrderCurrency($payment);

        if ($use_order_currency) {
            $currency = $payment->getOrder()->getOrderCurrencyCode();
            $amount = $payment->getOrder()->getBaseCurrency()->convert($amount, $currency);
            $amount = $payment->formatAmount($amount, true);
        } else {
            $currency = $payment->getOrder()->getBaseCurrencyCode();
        }

        // $order_id = $payment->getOrder()->getIncrementId();
        $quote_id = $payment->getOrder()->getQuoteId();

        //

        $_same_id =
            $pt_response['cart_id'] == $quote_id;

        $_same_type = PaytabsEnum::TransAreSame(
            $pt_response['tran_type'],
            $pt_response['pt_type']
        );

        // $pt_response['profileId'];

        $_same_amount =
            $pt_response['cart_amount'] == $amount
            && $pt_response['cart_currency'] == $currency;

        //

        return $_same_id && $_same_type && $_same_amount;
    }
}
