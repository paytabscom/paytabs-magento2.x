<?php

namespace ClickPay\PayPage\Gateway\Http;

use ClickPay\PayPage\Model\Adminhtml\Source\CurrencySelect;


trait ClickPayHelpers
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

    function setNewStatus($order, $newStatus)
    {
        $order->setState($newStatus)->setStatus($newStatus);
        $order->addStatusToHistory($newStatus, "Order was set to '$newStatus' as in the admin's configuration.");
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
}
