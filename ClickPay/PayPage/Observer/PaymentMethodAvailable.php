<?php

/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace ClickPay\PayPage\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use ClickPay\PayPage\Gateway\Http\ClickpayCore;
use ClickPay\PayPage\Gateway\Http\ClickpayHelper;

class PaymentMethodAvailable implements ObserverInterface
{
    /**
     * payment_method_is_active event handler.
     *
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(Observer $observer)
    {
        $code = $observer->getEvent()->getMethodInstance()->getCode();

        new ClickpayCore();
        $isClickpay = ClickpayHelper::isClickpayPayment($code);

        if ($isClickpay) {
            $checkResult = $observer->getEvent()->getResult();

            if ($checkResult->getData('is_available')) {
                $currency = $this->getCurrency();
                $isAllowed = ClickpayHelper::paymentAllowed($code, $currency);
                $checkResult->setData('is_available', $isAllowed);
            }
        }
    }

    private function getCurrency()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
        $currencyCode = $storeManager->getStore()->getCurrentCurrency()->getCode();

        return $currencyCode;
    }
}
