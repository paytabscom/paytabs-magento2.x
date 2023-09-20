<?php

namespace PayTabs\PayPage\Block\Widget;

use Magento\Catalog\Model\Product;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Asset\Repository;
use Magento\Widget\Block\BlockInterface;

class ValuInstallments extends Template
{
    /**
     * @var Registry
     */
    protected $coreRegistry;

    protected $assetRepo;


    /**
     * View constructor.
     * @param Template\Context $context
     * @param Registry $registry
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        Registry $registry,
        Repository $assetRepo,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->coreRegistry = $registry;

        $this->assetRepo = $assetRepo;
    }

    /**
     * @inheritdoc
     */
    protected function _toHtml(): string
    {
        $enabled = true;
        $meetTheCondition = true;

        if ($enabled) {
            if ($meetTheCondition) {
                return parent::_toHtml();
            }
        }

        return 'paytabs';
    }

    /**
     * Retrieve current product model
     *
     * @return Product
     */
    public function getProduct(): Product
    {
        return $this->coreRegistry->registry('product');
    }

    function getValUDetails()
    {
        // Call external API to check the installment plans

        $price = $this->getProduct()->getPrice();

        $installment_amount = round($price / 3, 2);
        $msg = "Pay 3 interest-free payments of EGP $installment_amount.";

        return $msg;
    }

    function getValULogo()
    {
        $_icons_path = $this->assetRepo->getUrl("PayTabs_PayPage::images/");
        return $_icons_path . '/valu.png';
    }
}
