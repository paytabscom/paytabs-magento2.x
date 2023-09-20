<?php

namespace PayTabs\PayPage\Block\Widget;

use Magento\Catalog\Model\Product;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Asset\Repository;
use Magento\Payment\Helper\Data;
use PayTabs\PayPage\Gateway\Http\PaytabsHelper;

class ValuInstallments extends Template
{
    /**
     * @var Registry
     */
    protected $coreRegistry;

    protected $assetRepo;
    protected $paymentHelper;

    private $product;

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
        Data $paymentHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->coreRegistry = $registry;

        $this->paymentHelper = $paymentHelper;
        $this->assetRepo = $assetRepo;

        $this->product = $this->getProduct();
    }

    /**
     * @inheritdoc
     */
    protected function _toHtml(): string
    {
        $canShow = $this->canShow();

        if ($canShow) {
            return parent::_toHtml();
        }

        return '';
    }


    function canShow()
    {
        try {
            $payment_method = $this->paymentHelper->getMethodInstance(\PayTabs\PayPage\Model\Ui\ConfigProvider::CODE_VALU);
            $enabled =
                (bool) $payment_method->getConfigData('active')
                && (bool) $payment_method->getConfigData('valu_widget/valu_widget_enable');
            if ($enabled) {
                $threshold = (float) $payment_method->getConfigData('valu_widget/valu_widget_price_threshold');
                $threshold = max(0, $threshold);

                $product_price = (float) $this->product->getPrice();
                if ($product_price > $threshold) {
                    return true;
                }
            }
        } catch (\Throwable $th) {
            PaytabsHelper::log($th->getMessage(), 3);
        }

        return false;
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

        $price = $this->product->getPrice();

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
