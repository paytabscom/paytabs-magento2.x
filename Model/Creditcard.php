<?php

/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace PayTabs\PayPage\Model;



/**
 * Pay In Store payment method model
 */
class Creditcard extends \Magento\Payment\Model\Method\AbstractMethod
{

    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'creditcard';

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isOffline = false;
}
