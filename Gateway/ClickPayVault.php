<?php

/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace ClickPay\PayPage\Gateway;

use Magento\Vault\Model\Method\Vault;


class ClickPayVault extends Vault
{
    public function isInitializeNeeded()
    {
        return false;
    }
}
