<?php

/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace ClickPay\PayPage\Gateway\Vault;

use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger;
use ClickPay\PayPage\Gateway\Http\ClickPayApi;


class Client implements ClientInterface
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

        $ptApi = ClickPayApi::getInstance($auth['endpoint'], $auth['merchant_id'], $auth['merchant_key']);

        $response = $ptApi->create_pay_page($values);

        $this->logger->debug([
            'request' => $transferObject->getBody(),
            'response' => (array) $response
        ]);

        return (array) $response;
    }
}
