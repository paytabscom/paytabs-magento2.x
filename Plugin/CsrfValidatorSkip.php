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
        if ($request->getModuleName() == 'paytabs') {
            $arr_actions = ['response', 'callback', 'ipn', 'responsepre'];
            if (in_array($request->getActionName(), $arr_actions)) {
                return; // Skip CSRF check
            }
        }
        $proceed($request, $action); // Proceed Magento 2 core functionalities
    }
}
