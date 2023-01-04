<?php

/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace ClickPay\PayPage\Gateway\Http\Client;

use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger;
use ClickPay\PayPage\Gateway\Http\ClickpayApi;
use ClickPay\PayPage\Gateway\Http\ClickpayEnum;

class ClientAuth implements ClientInterface
{
    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param Logger $logger
     */
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Places request to gateway. Returns result as ENV array
     *
     * @param TransferInterface $transferObject
     * @return array
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $req_data = $transferObject->getBody();
        $values = $req_data['params'];
        $auth = $req_data['auth'];
        $is_verify = $req_data['is_verify'];

        $ptApi = ClickpayApi::getInstance($auth['endpoint'], $auth['merchant_id'], $auth['merchant_key']);

        if ($is_verify) {
            $tran_ref = $values['tran_ref'];
            $response = $ptApi->verify_payment($tran_ref);
        } else {
            $response = $ptApi->request_followup($values);
        }

        $response->pt_type = ClickpayEnum::TRAN_TYPE_AUTH;
        $response->is_verify = $is_verify;

        $this->logger->debug([
            'request' => $transferObject->getBody(),
            'response' => (array) $response
        ]);

        return (array) $response;
    }
}