<?php

/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace ClickPay\PayPage\Model\Adminhtml\Source;


/**
 * Class PaymentAction
 */
class PaymentActionSale implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => 'authorize_capture',
                'label' => __('Sale')
            ]
        ];
    }
}
