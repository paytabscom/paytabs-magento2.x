<?php

namespace PayTabs\PayPage\Plugin;

class CsrfValidatorSkip
{
    /**
     * @param \Magento\Framework\App\Request\CsrfValidator $subject
     * @param \Closure $proceed
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Framework\App\ActionInterface $action
     */
    public function aroundValidate(
        $subject,
        \Closure $proceed,
        $request,
        $action
    ) {
        // $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $urlInterface = \Magento\Framework\App\ObjectManager::getInstance()
            ->get(\Magento\Framework\UrlInterface::class);

        $currentUrl = $urlInterface->getCurrentUrl();
        $haystack = $currentUrl;
        $needle   = 'paytabs';

        if (strpos($haystack, $needle) !== false) {
            $arr_actions = ['response', 'callback', 'ipn', 'responsepre'];
            if ($this->strposa($haystack, $arr_actions, 1)) {
                return; // Skip CSRF check
            }
        }
        $proceed($request, $action); // Proceed Magento 2 core functionalities
    }

    function strposa($haystack, $needles = array(), $offset = 0)
    {
        $chr = array();
        foreach ($needles as $needle) {
            $res = strpos($haystack, $needle, $offset);
            if ($res !== false) $chr[$needle] = $res;
        }
        if (empty($chr)) return false;
        return min($chr);
    }
}
