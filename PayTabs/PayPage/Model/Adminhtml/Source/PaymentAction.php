<?php

/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace PayTabs\PayPage\Model\Adminhtml\Source;


/**
 * Class PaymentAction
 */
class PaymentAction implements \Magento\Framework\Option\ArrayInterface
{
    const PAYMENT_ACTION_AUTH = 'auth';
    const PAYMENT_ACTION_SALE = 'sale';


    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => 'authorize',
                'label' => __('Authorize')
            ],
            [
                'value' => 'authorize_capture',
                'label' => __('Sale')
            ]
        ];
    }
}
