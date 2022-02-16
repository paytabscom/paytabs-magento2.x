<?php

namespace PayTabs\PayPage\Model\Adminhtml\Source;


class IpnUrl extends \Magento\Config\Block\System\Config\Form\Field
{

    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
        $baseurl = $storeManager->getStore()->getBaseUrl();
        $ipnUrl = $baseurl . "paytabs/paypage/ipn";

        return $ipnUrl;
    }
}
