<?php

/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace ClickPay\PayPage\Model\Adminhtml\Source;

use ClickPay\PayPage\Gateway\Http\ClickPayApi;
use ClickPay\PayPage\Gateway\Http\ClickPayCore;

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
        new ClickPayCore();
        $endpoints = ClickPayApi::getEndpoints();

        $endpoints1 = array_map(function ($key, $value) {
            return [
                'value' => $key,
                'label' => $value
            ];
        }, array_keys($endpoints), $endpoints);

        return $endpoints1;
    }
}
