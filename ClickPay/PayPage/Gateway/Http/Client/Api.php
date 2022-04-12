<?php

namespace ClickPay\PayPage\Gateway\Http\Client;

use ClickPay\PayPage\Gateway\Http\ClickPayApi;
use ClickPay\PayPage\Gateway\Http\ClickPayCore;
use ClickPay\PayPage\Gateway\Http\ClickPayEnum;
use ClickPay\PayPage\Gateway\Http\ClickPayRequestHolder;
use ClickPay\PayPage\Model\Adminhtml\Source\CurrencySelect;

class Api
{
    public function pt($paymentMethod)
    {
        // $paymentType = $paymentMethod->getCode();

        // PT
        $merchant_id = $paymentMethod->getConfigData('profile_id');
        $merchant_key = $paymentMethod->getConfigData('server_key');
        $endpoint = $paymentMethod->getConfigData('endpoint');

        new ClickPayCore();
        $pt = ClickPayApi::getInstance($endpoint, $merchant_id, $merchant_key);

        return $pt;
    }

    /**
     * Extract required parameters from the Order, to Pass to create_page API
     * -Client information
     * -Shipping address
     * -Products
     * @return Array of values to pass to create_paypage API
     */
    public function prepare_order($order, $paymentMethod, $isTokenise)
    {
        /** 1. Read required Params */

        $paymentType = $paymentMethod->getCode(); //'creditcard';

        $hide_shipping = (bool) $paymentMethod->getConfigData('hide_shipping');
        $framed_mode = (bool) $paymentMethod->getConfigData('iframe_mode');
        $payment_action = $paymentMethod->getConfigData('payment_action');

        $use_order_currency = CurrencySelect::IsOrderCurrency($paymentMethod);

        $allow_associated_methods = (bool) $paymentMethod->getConfigData('allow_associated_methods');

        $orderId = $order->getIncrementId();

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
        $localeResolver = $objectManager->get('\Magento\Framework\Locale\ResolverInterface');
        $productMetadata = $objectManager->get('Magento\Framework\App\ProductMetadataInterface');
        $versionMagento = $productMetadata->getVersion();

        if ($use_order_currency) {
            $currency = $order->getOrderCurrencyCode();
            $amount = $order->getGrandTotal();
        } else {
            $currency = $order->getBaseCurrencyCode();
            $amount = $order->getBaseGrandTotal();
        }

        $baseurl = $storeManager->getStore()->getBaseUrl();
        $returnUrl = $baseurl . "ClickPay/paypage/response";
        $callbackUrl = $baseurl . "ClickPay/paypage/callback";

        $lang_code = $localeResolver->getLocale();
        $lang = ($lang_code == 'ar' || substr($lang_code, 0, 3) == 'ar_') ? 'ar' : 'en';

        // Compute Prices

        $amount = number_format((float) $amount, 3, '.', '');
        // $amount = $order->getPayment()->formatAmount($amount, true);

        // $discountAmount = abs($order->getDiscountAmount());
        // $shippingAmount = $order->getShippingAmount();
        // $taxAmount = $order->getTaxAmount();

        // $amount += $discountAmount;
        // $otherCharges = $shippingAmount + $taxAmount;


        /** 1.2. Read BillingAddress info */

        $billingAddress = $order->getBillingAddress();
        // $firstName = $billingAddress->getFirstname();
        // $lastName = $billingAddress->getlastname();

        // $email = $billingAddress->getEmail();
        // $city = $billingAddress->getCity();

        $postcode = trim($billingAddress->getPostcode());

        // $region = $billingAddress->getRegionCode();
        $country_iso2 = $billingAddress->getCountryId();

        // $telephone = $billingAddress->getTelephone();
        $streets = $billingAddress->getStreet();
        $billing_address = implode(', ', $streets);

        $hasShipping = false;
        $shippingAddress = $order->getShippingAddress();
        if ($shippingAddress) {
            $hasShipping = true;
            // $s_firstName = $shippingAddress->getFirstname();
            // $s_lastName = $shippingAddress->getlastname();
            // $s_city = $shippingAddress->getCity();

            $s_postcode = trim($shippingAddress->getPostcode());

            // $s_region = $shippingAddress->getRegionCode();
            $s_country_iso2 = $shippingAddress->getCountryId();

            $s_streets = $shippingAddress->getStreet();
            $shipping_address = implode(', ', $s_streets);
        }

        /** 1.3. Read Products */

        // $items = $order->getAllItems();
        // $items = $order->getItems();
        $items = $order->getAllVisibleItems();

        $items_arr = array_map(function ($p) {
            $q = (int)$p->getQtyOrdered();
            return "{$p->getName()} ({$q})";
        }, $items);

        $cart_desc = implode(', ', $items_arr);


        // System Parameters
        // $systemVersion = "Magento {$versionMagento}";


        $tran_type = ClickPayEnum::TRAN_TYPE_SALE;
        switch ($payment_action) {
            case 'authorize':
                $tran_type = ClickPayEnum::TRAN_TYPE_AUTH;
                break;

            case 'authorize_capture':
                $tran_type = ClickPayEnum::TRAN_TYPE_SALE;
                break;
        }

        /** 2. Fill post array */

        $pt_holder = new ClickPayRequestHolder();
        $pt_holder
            ->set01PaymentCode($paymentType, $allow_associated_methods, $currency)
            ->set02Transaction($tran_type, ClickPayEnum::TRAN_CLASS_ECOM)
            ->set03Cart($orderId, $currency, $amount, $cart_desc)
            ->set04CustomerDetails(
                $billingAddress->getName(),
                $billingAddress->getEmail(),
                $billingAddress->getTelephone(),
                $billing_address,
                $billingAddress->getCity(),
                $billingAddress->getRegionCode(),
                $country_iso2,
                $postcode,
                null
            );

        if ($hasShipping) {
            $pt_holder->set05ShippingDetails(
                false,
                $shippingAddress->getName(),
                $shippingAddress->getEmail(),
                $shippingAddress->getTelephone(),
                $shipping_address,
                $shippingAddress->getCity(),
                $shippingAddress->getRegionCode(),
                $s_country_iso2,
                $s_postcode,
                null
            );
        } else if (!$hide_shipping) {
            $pt_holder->set05ShippingDetails(true);
        }

        $pt_holder
            ->set06HideShipping($hide_shipping)
            ->set07URLs($returnUrl, $callbackUrl)
            ->set08Lang($lang)
            ->set09Framed($framed_mode, 'top')
            ->set10Tokenise($isTokenise)
            ->set99PluginInfo('Magento', $versionMagento, ClickPay_PAYPAGE_VERSION);

        $post_arr = $pt_holder->pt_build();

        //

        return $post_arr;
    }

    /**
     * check if the Order is paid and complete
     * sometimes and for some reason, create_paypage been called twice, after the User paid for the Order
     * @return true if the Order has been paid before, false otherwise
     */
    public static function hadPaid($order)
    {
        $lastTransId = $order->getPayment()->getLastTransId();
        $amountPaid = $order->getPayment()->getAmountPaid();
        $info = $order->getPayment()->getAdditionalInformation();

        $payment_amount = 0;
        if ($info && isset($info['payment_amount'])) {
            $payment_amount = $info['payment_amount'];
        }

        if ($lastTransId && floor($amountPaid) == floor($payment_amount)) {
            return true;
        }
        return false;
    }
}
