<?php

/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace PayTabs\PayPage\Model\Adminhtml\Source;


/**
 * Class EmailCOnfig
 */
class EmailConfig implements \Magento\Framework\Option\ArrayInterface
{
    // Email send Options

    const EMAIL_NEW_ORDER_SYSTEM = 'system';
    const EMAIL_NEW_ORDER_NO = 'no';
    const EMAIL_NEW_ORDER_AFTER_PAYMENT = 'after_payment_success';


    // Email send Places

    const EMAIL_PLACE_AFTER_PLACE_ORDER = 'after_place_order';
    const EMAIL_PLACE_AFTER_PAYMENT = 'after_payment';

    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => $this::EMAIL_NEW_ORDER_SYSTEM,
                'label' => 'System default'
            ],
            [
                'value' => $this::EMAIL_NEW_ORDER_NO,
                'label' => 'Do not send Order confirmation email'
            ],
            [
                'value' => $this::EMAIL_NEW_ORDER_AFTER_PAYMENT,
                'label' => 'Send Order confirmation email, if payment succeed'
            ]
        ];
    }

    //

    /**
     * Check whether the system Can send "New Order Confirmation email" in specific location in the code
     * @param \PayTabs\PayPage\Model\Adminhtml\Source\EmailConfig::EmailSendPlaces $when
     * @param \PayTabs\PayPage\Model\Adminhtml\Source\EmailConfig::EmailSendOptions $email_config
     * @return bool
     */
    static function canSendEMail($when, $email_config)
    {
        switch ($when) {
            case EmailConfig::EMAIL_PLACE_AFTER_PLACE_ORDER:
                $places = [EmailConfig::EMAIL_NEW_ORDER_NO, EmailConfig::EMAIL_NEW_ORDER_AFTER_PAYMENT];
                $donot_send = in_array($email_config, $places);

                return !$donot_send;

            case EmailConfig::EMAIL_PLACE_AFTER_PAYMENT:
                $places = [EmailConfig::EMAIL_NEW_ORDER_AFTER_PAYMENT];
                $can_send = in_array($email_config, $places);

                return $can_send;
        }
    }
}
