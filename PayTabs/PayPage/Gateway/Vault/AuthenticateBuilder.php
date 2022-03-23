<?php

/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace PayTabs\PayPage\Gateway\Vault;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\ConfigInterface;


/**
 * Class AuthenticateBuilder
 */
class AuthenticateBuilder implements BuilderInterface
{

    public function __construct(
        ConfigInterface $config
    ) {
        $this->config = $config;
    }


    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        $profile_id = $this->config->getValue('profile_id');
        $server_key = $this->config->getValue('server_key');
        $endpoint = $this->config->getValue('endpoint');
        // $order_currency = $this->config->getValue('currency_select');

        // $use_order_currency = CurrencySelect::UseOrderCurrency($payment);

        //

        $req_data = [
            'auth' => [
                'endpoint' => $endpoint,
                'merchant_id'  => $profile_id,
                'merchant_key' => $server_key,
                // 'currency_select' => $currency_select,
            ]
        ];

        return $req_data;
    }
}
