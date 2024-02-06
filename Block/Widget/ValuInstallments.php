<?php

namespace PayTabs\PayPage\Block\Widget;

use Magento\Catalog\Model\Product;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Asset\Repository;
use Magento\Payment\Helper\Data;
use PayTabs\PayPage\Gateway\Http\Client\Api as PaytabsApi;
use PayTabs\PayPage\Gateway\Http\PaytabsHelper;
use PayTabs\PayPage\Model\Adminhtml\Source\CurrencySelect;
use PayTabs\PayPage\Observer\PaymentMethodAvailable;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use PayTabs\PayPage\Gateway\Http\PaytabsCore;

class ValuInstallments extends Template
{
    const Currency = 'EGP';

    private $static_content;

    /**
     * @var Registry
     */
    protected $coreRegistry;
    protected $assetRepo;
    protected $paymentHelper;
    protected $priceCurrency;
    private $product;

    private $payment_method;

    public $_valu_text;

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
        PriceCurrencyInterface $priceCurrency,
        Data $paymentHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        new PaytabsCore;

        $this->coreRegistry = $registry;

        $this->paymentHelper = $paymentHelper;
        $this->assetRepo = $assetRepo;

        $this->priceCurrency = $priceCurrency;

        $this->product = $this->getProduct();

        $this->payment_method = $this->_getPaymentMethod();

        $this->static_content = (bool)$this->payment_method->getConfigData('valu_widget/valu_widget_static_content');
    }

    /**
     * @inheritdoc
     */
    protected function _toHtml(): string
    {
        $canShow = $this->canShow();

        if ($canShow) {
            $isFetched = $this->getValUDetails();
            if ($isFetched) {
                return parent::_toHtml();
            }
        }

        return '';
    }

    /**
     * Check if the Widget is eligible to be displayed.
     * Conditions:
     *  - Enable valU payment method
     *  - Enable the widget
     *  - Currency match (Base or Order)
     *  - Product price > threshold
     */
    function canShow()
    {
        try {
            $payment_method = $this->payment_method;
            $enabled =
                (bool) $payment_method->getConfigData('active')
                && (bool) $payment_method->getConfigData('valu_widget/valu_widget_enable');
            if ($enabled) {
                if ($this->_isCurrencyAvailable($payment_method)) {
                    $threshold = (float) $payment_method->getConfigData('valu_widget/valu_widget_price_threshold');
                    $threshold = max(0, $threshold);

                    $product_price = $this->_getProductPrice();

                    if ($product_price >= $threshold) {
                        return true;
                    }

                    // ToDo:
                    // Get the final product price, confirm on the price and use the threshold
                    $zero_price_enabled = (bool) $payment_method->getConfigData('valu_widget/valu_widget_on_price_zero');
                    if ($product_price == 0 && $zero_price_enabled) {
                        return true;
                    }
                }
            }
        } catch (\Throwable $th) {
            PaytabsHelper::log("valU widget, Show error: " . $th->getMessage(), 3);
        }

        return false;
    }

    /**
     * Check if the Currency matches the required widget currency.
     * True if:
     *  - Base currency = EGP
     *  - OR valU method enable Order Currency option && Current currency is EGP
     */
    function _isCurrencyAvailable($payment_method)
    {
        $use_order_currency = CurrencySelect::IsOrderCurrency($payment_method);
        $currencyCode = PaymentMethodAvailable::getCurrency($use_order_currency);

        if ($currencyCode == ValuInstallments::Currency) {
            return true;
        }

        return false;
    }

    //

    private function getValUDetails()
    {
        if ($this->static_content) {
            return $this->_getValUDetails_static();
        } else {
            return $this->_getValUDetails_live();
        }
    }

    function getValULogo()
    {
        $_icons_path = $this->assetRepo->getUrl("PayTabs_PayPage::images/");
        return $_icons_path . '/valu_long.png';
    }

    //

    private function _getValUDetails_static()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $localeResolver = $objectManager->get('\Magento\Framework\Locale\ResolverInterface');
        $lang_code = $localeResolver->getLocale();
        $lang = ($lang_code == 'ar' || substr($lang_code, 0, 3) == 'ar_') ? 'ar' : 'en';

        if ($lang == 'ar') {
            $msg = "! اشترى اﻻن وقم بالدفع على مدار 60 شهر";
        } else {
            $msg = "Buy Now & Pay Later up to 60 Months!";
        }

        $this->_valu_text = $msg;
        return true;
    }

    private function _getValUDetails_live()
    {
        $price = $this->_getProductPrice();

        PaytabsHelper::log("valU inqiry, Product: {$this->product->getId()}, {$price}", 1);
        $details = $this->_callValUAPI($price, ValuInstallments::Currency);

        if (!$details || !$details->success) {
            $_err_msg = json_encode($details);
            PaytabsHelper::log("valU Details error: [{$_err_msg}]", 3);
            return false;
        }

        $installments_count = 3;
        $valu_plan = $this->_getValUPlan($details, $installments_count);

        if (!$valu_plan) {
            return false;
        }

        try {
            $installment_amount = $valu_plan->emi;

            $calculated_installment = round($price / $installments_count, 2);
            $is_free_interest = $calculated_installment >= $installment_amount;

            $txt_free = $is_free_interest ? "interest-free" : "";

            $msg = "Pay {$installments_count} {$txt_free} payments of " . ValuInstallments::Currency . " $installment_amount.";

            $this->_valu_text = $msg;
            return true;
        } catch (\Throwable $th) {
            PaytabsHelper::log("valU widget error: " . $th->getMessage(), 3);
        }

        return false;
    }

    private function _callValUAPI($price, $currency)
    {
        $paytabs = new PaytabsApi;
        $ptApi = $paytabs->pt($this->payment_method);

        $phone_number = $this->payment_method->getConfigData('valu_widget/valu_widget_phone_number');
        if (empty($phone_number)) {
            PaytabsHelper::log("valU phone number is not set, {$this->product->getId()}", 2);
            return false;
        }

        $params = [
            'cart_amount' => $price,
            'cart_currency' => $currency,
            'customer_details' => [
                'phone' => $phone_number,
            ],
        ];

        $res = $ptApi->inqiry_valu($params);

        return $res;
    }

    private function _getValUPlan($details, $installments_count)
    {
        try {
            $plansList = $details->valuResponse->productList[0]->tenureList;
            foreach ($plansList as $plan) {
                if ($plan->tenorMonth == $installments_count) {
                    return $plan;
                }
            }
        } catch (\Throwable $th) {
            PaytabsHelper::log("valU Plan error: " . $th->getMessage(), 3);
        }

        $_log = json_encode($plansList);
        PaytabsHelper::log("valU Plan error: No Plan selected, [{$_log}]", 2);

        return false;
    }

    //

    /**
     * Retrieve current product model
     *
     * @return Product
     */
    public function getProduct(): Product
    {
        return $this->coreRegistry->registry('product');
    }

    private function _getProductPrice()
    {
        // Base price in Base currency
        $price = (float) $this->product->getPrice(); //->getPrice('final_price')->getValue();

        // $price = (float) $this->product->getFinalPrice();
        // $currencyCode = $this->product->getStore()->getCurrentCurrencyCode();

        $use_order_currency = CurrencySelect::IsOrderCurrency($this->payment_method);
        if ($use_order_currency) {
            $convertedPrice = $this->priceCurrency->convertAndRound($price, ValuInstallments::Currency);
        } else {
            $convertedPrice = $price;
        }

        return $convertedPrice;
    }


    private function _getPaymentMethod()
    {
        $payment_method = $this->paymentHelper->getMethodInstance(\PayTabs\PayPage\Model\Ui\ConfigProvider::CODE_VALU);

        return $payment_method;
    }
}
