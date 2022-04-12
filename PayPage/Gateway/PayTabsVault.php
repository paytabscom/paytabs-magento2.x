<?php

/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace PayTabs\PayPage\Gateway;

use Magento\Vault\Model\Method\Vault;


class PayTabsVault extends Vault
{
    public function isInitializeNeeded()
    {
        return false;
    }
}
