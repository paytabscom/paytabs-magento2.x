<?php

/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace PayTabs\PayPage\Model;



/**
 * Pay In Store payment method model
 */
class Stcpay extends \Magento\Payment\Model\Method\AbstractMethod
{

    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'stcpay';

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isOffline = false;
}
