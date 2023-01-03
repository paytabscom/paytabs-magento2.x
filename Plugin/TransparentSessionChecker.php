<?php

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace PayTabs\PayPage\Plugin;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Session\SessionStartChecker;

/**
 * Intended to preserve session cookie after submitting POST form from PayTabs to Magento controller.
 */
class TransparentSessionChecker
{
    /**
     * @var string[]
     */
    private $disableSessionUrls = [
        'paytabs/paypage/response'
    ];

    /**
     * @var Http
     */
    private $request;

    /**
     * @param Http $request
     */
    public function __construct(
        Http $request
    ) {
        $this->request = $request;
    }


    /**
     * Prevents session starting while instantiating PayTabs transparent redirect controller.
     *
     * @param SessionStartChecker $subject
     * @param bool $result
     * @return bool
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterCheck(SessionStartChecker $subject, bool $result): bool
    {
        if ($result === false) {
            return false;
        }

        foreach ($this->disableSessionUrls as $url) {
            if (strpos((string)$this->request->getPathInfo(), $url) !== false) {
                // is Guest & Refill option is enabled
                $isGuest = $this->request->getParam('g', false);
                $tranStatus = $this->request->getParam('respStatus', '');
                if (!$isGuest || ($isGuest && $tranStatus == 'A')) {
                    return false;
                }
            }
        }

        return true;
    }
}
