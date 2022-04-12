<?php

/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace ClickPay\PayPage\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use ClickPay\PayPage\Gateway\Http\ClickPayCore;
use ClickPay\PayPage\Gateway\Http\ClickPayHelper;
use ClickPay\PayPage\Model\Adminhtml\Source\CurrencySelect;

class PaymentMethodAvailable implements ObserverInterface
{
    /**
     * payment_method_is_active event handler.
     *
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(Observer $observer)
    {
        $paymentMethod = $observer->getEvent()->getMethodInstance();
        $code = $paymentMethod->getCode();

        new ClickPayCore();
        $isClickPay = ClickPayHelper::isClickPayPayment($code);

        if ($isClickPay) {
            $checkResult = $observer->getEvent()->getResult();

            if ($checkResult->getData('is_available')) {
                $use_order_currency = CurrencySelect::IsOrderCurrency($paymentMethod);
                $currency = $this->getCurrency($use_order_currency);
                $isAllowed = ClickPayHelper::paymentAllowed($code, $currency);
                $checkResult->setData('is_available', $isAllowed);
            }
        }
    }

    private function getCurrency($use_order_currency)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');

        if ($use_order_currency) {
            $currencyCode = $storeManager->getStore()->getCurrentCurrency()->getCode();
        } else {
            $currencyCode = $storeManager->getStore()->getBaseCurrency()->getCode();
        }

        return $currencyCode;
    }
}
