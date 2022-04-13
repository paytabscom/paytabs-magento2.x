<?php

/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace PayTabs\PayPage\Model\Adminhtml\Source;

use PayTabs\PayPage\Gateway\Http\PaytabsApi;
use PayTabs\PayPage\Gateway\Http\PaytabsCore;

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
        new PaytabsCore();
        $endpoints = PaytabsApi::getEndpoints();

        $endpoints1 = array_map(function ($key, $value) {
            return [
                'value' => $key,
                'label' => $value
            ];
        }, array_keys($endpoints), $endpoints);

        return $endpoints1;
    }
}
