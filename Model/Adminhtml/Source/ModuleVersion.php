<?php

namespace PayTabs\PayPage\Model\Adminhtml\Source;

use PayTabs\PayPage\Gateway\Http\PaytabsCore;

class ModuleVersion extends \Magento\Config\Block\System\Config\Form\Field
{

    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        return PaytabsCore::getVersion();
    }
}
