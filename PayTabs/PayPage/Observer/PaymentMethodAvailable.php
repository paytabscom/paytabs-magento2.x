<?php

/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace PayTabs\PayPage\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use PayTabs\PayPage\Gateway\Http\PaytabsCore;
use PayTabs\PayPage\Gateway\Http\PaytabsHelper;

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

        new PaytabsCore();
        $isPaytabs = PaytabsHelper::isPayTabsPayment($code);

        if ($isPaytabs) {
            $checkResult = $observer->getEvent()->getResult();

            if ($checkResult->getData('is_available')) {
                $currency = $this->getCurrency();
                $isAllowed = PaytabsHelper::paymentAllowed($code, $currency);
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
