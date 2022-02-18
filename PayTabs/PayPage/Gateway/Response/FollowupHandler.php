<?php

/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace PayTabs\PayPage\Gateway\Response;

use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;

class FollowupHandler implements HandlerInterface
{
    const TXN_ID = 'TXN_ID';

    /**
     * Handles transaction id
     *
     * @param array $handlingSubject
     * @param array $response
     * @return void
     */
    public function handle(array $handlingSubject, array $response)
    {
        if (
            !isset($handlingSubject['payment'])
            || !$handlingSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        /** @var PaymentDataObjectInterface $paymentDO */
        $paymentDO = $handlingSubject['payment'];

        $payment = $paymentDO->getPayment();

        /** @var $payment \Magento\Sales\Model\Order\Payment */
        // $payment->setTransactionId($response[self::TXN_ID]);

        $pt_tran_amount = array_key_exists('cart_amount', $response) ? $response['cart_amount'] : 'NA';
        $pt_tran_currency = array_key_exists('cart_currency', $response) ? $response['cart_currency'] : 'NA';

        $tran_ref = $response['tran_ref'];
        $payment
            ->setTransactionId($tran_ref)
            ->setTransactionAdditionalinfo(
                \Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS,
                [
                    'tran_amount'   => $pt_tran_amount,
                    'tran_currency' => $pt_tran_currency,
                    'amount' => $handlingSubject['amount']
                ]
            );
    }
}
