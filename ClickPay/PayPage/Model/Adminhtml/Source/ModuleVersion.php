<?php

namespace ClickPay\PayPage\Model\Adminhtml\Source;


class ModuleVersion extends \Magento\Config\Block\System\Config\Form\Field
{

    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        return ClickPay_PAYPAGE_VERSION;
    }
}
