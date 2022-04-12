<?php

namespace PayTabs\PayPage\Model\Adminhtml\Source;


class ModuleVersion extends \Magento\Config\Block\System\Config\Form\Field
{

    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        return PAYTABS_PAYPAGE_VERSION;
    }
}
