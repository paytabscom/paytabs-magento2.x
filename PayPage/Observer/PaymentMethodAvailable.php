<?php

/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace PayTabs\PayPage\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class PaymentMethodAvailable implements ObserverInterface
{
    /**
     * payment_method_is_active event handler.
     *
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(Observer $observer)
    {
        $paytabs = new \PayTabs\PayPage\Gateway\Http\Client\Api;
        $code = $observer->getEvent()->getMethodInstance()->getCode();

        if ($paytabs::isPayTabsPayment($code)) {
            $checkResult = $observer->getEvent()->getResult();

            if ($checkResult->getData('is_available')) {
                $isAllowed = $paytabs::paymentAllowed($code);
                $checkResult->setData('is_available', $isAllowed);
            }
        }
    }
}
