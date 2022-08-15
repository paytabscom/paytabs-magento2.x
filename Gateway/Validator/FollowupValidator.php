<?php

/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace PayTabs\PayPage\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use PayTabs\PayPage\Gateway\Http\PaytabsHelper;


class FollowupValidator extends AbstractValidator
{
    const RESULT_CODE = 'RESULT_CODE';

    /**
     * Performs validation of result code
     *
     * @param array $validationSubject
     * @return ResultInterface
     */
    public function validate(array $validationSubject)
    {
        if (!isset($validationSubject['response']) || !is_array($validationSubject['response'])) {
            throw new \InvalidArgumentException('Response does not exist');
        }

        $response = $validationSubject['response'];

        $success = $response['success'];
        // $pending_success = $response['pending_success'];
        $message = $response['message'];

        $_order_id = @$response['cart_id'];
        PaytabsHelper::log("Payment result, Order {$_order_id}, [{$success} {$message}]", 1);

        return $this->createResult(
            $success,
            [$message]
        );
    }
}
