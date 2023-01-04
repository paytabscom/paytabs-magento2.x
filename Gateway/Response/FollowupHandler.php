<?php

/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace ClickPay\PayPage\Gateway\Response;

use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use ClickPay\PayPage\Gateway\Http\ClickPayEnum;
use ClickPay\PayPage\Gateway\Http\ClickPayHelpers;

class FollowupHandler implements HandlerInterface
{
    use ClickpayHelpers;


    public function __construct(
        \Magento\Vault\Api\Data\PaymentTokenFactoryInterface $paymentTokenFactory,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor

        // \Psr\Log\LoggerInterface $logger
    ) {
        $this->_paymentTokenFactory = $paymentTokenFactory;
        $this->encryptor = $encryptor;
    }

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
        

        $pt_tran_amount = array_key_exists('cart_amount', $response) ? $response['cart_amount'] : 'NA';
        $pt_tran_currency = array_key_exists('cart_currency', $response) ? $response['cart_currency'] : 'NA';

        $pt_tran_type = $response['tran_type'];
        $isAuth = ClickPayEnum::TranIsAuth($pt_tran_type);

        $tran_ref = $response['tran_ref'];
        $transaction_info = [
            'tran_amount'   => $pt_tran_amount,
            'tran_currency' => $pt_tran_currency,
        ];

        if (array_key_exists('amount', $handlingSubject)) {
            $transaction_info['amount'] = $handlingSubject['amount'];
        }

         //

         $is_verify = array_key_exists('is_verify', $response) ? $response['is_verify'] : false;

         if ($is_verify) {
             $on_hold = $response['is_on_hold'];
             $is_pending = $response['is_pending'];
 
             /*if ($on_hold || $is_pending) {
                 $order = $payment->getOrder();
                 $order->hold()->save();
             }*/
 
             if ($on_hold) {
                 $payment->setIsFraudDetected(true);
             }
         }
 
         //
 
         $this->pt_manage_tokenize($this->_paymentTokenFactory, $this->encryptor, $payment, (object) $response);
 
         //
         

        $payment
            ->setTransactionId($tran_ref)
            ->setIsTransactionClosed(!$isAuth)
            ->setTransactionAdditionalinfo(
                \Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS,
                 $transaction_info
            );
    }
}
