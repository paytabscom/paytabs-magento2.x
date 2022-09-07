<?php

/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace PayTabs\PayPage\Model;



/**
 * Pay In Store payment method model
 */
class All extends \Magento\Payment\Model\Method\Adapter
{

    /**
     * Payment code
     *
     * @var string
     */
    // protected $_code = 'all';

    /**
     * Availability option
     *
     * @var bool
     */
    // protected $_isOffline = false;

    //

    public function getConfigPaymentAction()
    {
        return parent::getConfigPaymentAction();
    }

    public function isInitializeNeeded()
    {
        $preorder = (bool) $this->getConfigData('payment_preorder');
        if ($preorder) {
            return false;
        }

        return parent::isInitializeNeeded();
    }

    public function initialize($paymentAction, $stateObject)
    {
        return parent::initialize($paymentAction, $stateObject);
    }
}
