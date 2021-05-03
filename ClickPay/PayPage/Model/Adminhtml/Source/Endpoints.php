<?php

/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace ClickPay\PayPage\Model\Adminhtml\Source;

use ClickPay\PayPage\Gateway\Http\ClickpayApi;
use ClickPay\PayPage\Gateway\Http\ClickpayCore;

/**
 * Class Endpoints
 */
class Endpoints implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        new ClickpayCore();
        $endpoints = ClickpayApi::getEndpoints();

        $endpoints1 = array_map(function ($key, $value) {
            return [
                'value' => $key,
                'label' => $value
            ];
        }, array_keys($endpoints), $endpoints);

        return $endpoints1;
    }
}
