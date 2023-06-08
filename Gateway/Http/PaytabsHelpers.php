<?php

namespace PayTabs\PayPage\Gateway\Http;

use PayTabs\PayPage\Model\Adminhtml\Source\CurrencySelect;


trait PaytabsHelpers
{

    function isValidOrder($order)
    {
        return $order && $order->getId();
    }

    function getAmount($payment, $tranCurrency, $tranAmount, $use_order_currency)
    {
        $amount = null;

        $orderCurrency = strtoupper($payment->getOrder()->getOrderCurrencyCode());
        $baseCurrency  = strtoupper($payment->getOrder()->getBaseCurrencyCode());
        $tranCurrency  = strtoupper($tranCurrency);

        if ($use_order_currency) {
            if ($orderCurrency != $tranCurrency) {
                throw Exception('Diff Currency');
            }

            if ($tranCurrency == $baseCurrency) {
                $amount = $tranAmount;
            } else {
                // Convert Amount to Base
                $amount = CurrencySelect::convertOrderToBase($payment, $tranAmount);

                $payment->getOrder()
                    ->addStatusHistoryComment(
                        __(
                            'Transaction amount converted to base currency: (%1) = (%2)',
                            $payment->getOrder()->getOrderCurrency()->format($tranAmount, [], false),
                            $payment->formatPrice($amount)
                        )
                    )
                    ->setIsCustomerNotified(false)
                    ->save();
            }
        } else {
            if ($baseCurrency != $tranCurrency) {
                throw Exception('Diff Currency');
            }
            $amount = $tranAmount;
        }

        return $amount;
    }

    function isSameGrandAmount($order, $use_order_currency, $amount_to_check)
    {
        if ($use_order_currency) {
            $amount = $order->getGrandTotal();
        } else {
            $amount = $order->getBaseGrandTotal();
        }

        $diff = abs($amount - $amount_to_check);

        return $diff < 0.0001;
    }

    private function invoiceSend($order, $payment)
    {
        $canInvoice = $order->canInvoice();
        if (!$canInvoice) return;

        $invoice = $payment->getCreatedInvoice();
        if ($invoice) { //} && !$order->getEmailSent()) {
            $sent = $this->_invoiceSender->send($invoice);
            $invoiceId = $invoice->getIncrementId();
            if ($sent) {
                $order
                    ->addStatusHistoryComment(
                        __('You notified customer about invoice #%1.', $invoiceId)
                    )
                    ->setIsCustomerNotified(true)
                    ->save();
            } else {
                $order
                    ->addStatusHistoryComment(
                        __('Failed to notify the customer about invoice #%1.', $invoiceId)
                    )
                    ->setIsCustomerNotified(false)
                    ->save();
            }
        }
    }

    function setNewStatus($order, $newStatus, $status_only = false)
    {
        if ($status_only) {
            $order->setStatus($newStatus);
        } else {
            $order->setState($newStatus)->setStatus($newStatus);
        }
        $order->addStatusToHistory($newStatus, "Order was set to '$newStatus' as in the admin's configuration '$status_only'.");
    }


    function getInvoiceByTransaction($order, $transaction_id)
    {
        $invoices = $order->getInvoiceCollection();

        foreach ($invoices as $_invoice) {
            if ($_invoice->getTransactionId() == $transaction_id) {
                return $_invoice;
            }
        }

        return null;
    }


    public function pt_manage_tokenize($tokenFactory, $encryptor, $payment, $response)
    {
        if (!isset($response->token, $response->payment_info, $response->tran_ref)) {
            return;
        }

        $transaction_ref = $response->tran_ref;
        $token_details = $response->payment_info;
        $token_details->tran_ref = $transaction_ref;

        $order = $payment->getOrder();
        $paymentMethod = $payment->getMethodInstance();

        $paymentToken = $this->pt_find_token(
            $response->token,
            $order->getCustomerId(),
            $paymentMethod->getCode(),
            $token_details,
            $tokenFactory,
            $encryptor
        );
        if ($paymentToken) {
            $extensionAttributes = $payment->getExtensionAttributes();
            $extensionAttributes->setVaultPaymentToken($paymentToken);
        }
    }

    private function pt_find_token($token, $customer_id, $payment_code, $token_details, $tokenFactory, $encryptor)
    {
        try {
            $isCard = ($payment_code == 'all') || PaytabsHelper::isCardPayment($payment_code);
            $tokenType = $isCard
                ? \Magento\Vault\Api\Data\PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD
                : \Magento\Vault\Api\Data\PaymentTokenFactoryInterface::TOKEN_TYPE_ACCOUNT;

            $str_token_details = json_encode($token_details);

            $publicHash = "$customer_id $str_token_details";
            $publicHashEncrypted = $encryptor->getHash($publicHash);

            $paymentToken = $tokenFactory->create($tokenType);
            $paymentToken
                ->setGatewayToken($token)
                ->setCustomerId($customer_id)
                ->setPaymentMethodCode($payment_code)
                ->setPublicHash($publicHashEncrypted)
                ->setExpiresAt("{$token_details->expiryYear}-{$token_details->expiryMonth}-01 00:00:00")
                ->setTokenDetails($str_token_details)
                ->save();

            return $paymentToken;
        } catch (\Throwable $th) {
            PaytabsHelper::log('Save payment token: ' . $th->getMessage(), 3);
            return null;
        }
    }

    // Add a prefix to all Keys (array should associative array, like [$k => $v])
    // returns new array
    function pt_add_prefix_to_keys($array, $prefix)
    {
        $new_array = [];
        foreach ($array as $k => $v) {
            $new_array[$prefix . $k] = $v;
        }

        return $new_array;
    }


    // Check if the Admin user created this Order
    public static function is_admin_created($order)
    {
        return empty($order->getRemoteIp());
    }
}
