<?php

namespace ClickPay\PayPage\Model\Adminhtml\Source;

use ClickPay\PayPage\Gateway\Http\ClickPayCore;

class ModuleVersion extends \Magento\Config\Block\System\Config\Form\Field
{

    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        return ClickPayCore::getVersion();
    }
}
