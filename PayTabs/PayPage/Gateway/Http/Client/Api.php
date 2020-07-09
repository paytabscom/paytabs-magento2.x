<?php

namespace PayTabs\PayPage\Gateway\Http\Client;

use PayTabs\PayPage\Gateway\Http\PaytabsApi;
use PayTabs\PayPage\Gateway\Http\PaytabsCore2;
use PayTabs\PayPage\Gateway\Http\PaytabsHelper;
use PayTabs\PayPage\Gateway\Http\PaytabsHolder2;

class Api
{
    public function pt($paymentMethod)
    {
        // $paymentType = $paymentMethod->getCode();

        $profileId = $paymentMethod->getConfigData('profile_id');
        $serverKey = $paymentMethod->getConfigData('server_key');

        new PaytabsCore2();
        $pt = PaytabsApi::getInstance($profileId, $serverKey);

        return $pt;
    }

    /**
     * Extract required parameters from the Order, to Pass to create_page API
     * -Client information
     * -Shipping address
     * -Products
     * @return Array of values to pass to create_paypage API
     */
    public function prepare_order($order, $paymentType)
    {
        /** 1. Read required Params */

        $orderId = $order->getIncrementId();

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
        $localeResolver = $objectManager->get('\Magento\Framework\Locale\ResolverInterface');
        // $productMetadata = $objectManager->get('Magento\Framework\App\ProductMetadataInterface');
        // $versionMagento = $productMetadata->getVersion();

        $currency = $order->getOrderCurrencyCode();
        $baseurl = $storeManager->getStore()->getBaseUrl();
        $returnUrl = $baseurl . "paypage/paypage/response?p=$orderId";

        $lang_code = $localeResolver->getLocale();

        // Compute Prices

        $amount = $order->getGrandTotal();
        $discountAmount = abs($order->getDiscountAmount());
        // $shippingAmount = $order->getShippingAmount();
        // $taxAmount = $order->getTaxAmount();
        // $otherCharges = $shippingAmount + $taxAmount;

        $amount += $discountAmount;
        $amount = number_format((float) $amount, 2, '.', '');


        /** 1.2. Read BillingAddress info */

        $billingAddress = $order->getBillingAddress();

        $postcode = trim($billingAddress->getPostcode());

        $country_iso2 = $billingAddress->getCountryId();
        $country = PaytabsHelper::countryGetiso3($country_iso2);

        $streets = $billingAddress->getStreet();
        $billing_address = array_reduce($streets, function ($acc, $street) {
            return $acc . ', ' . $street;
        }, '');


        $hasShipping = false;
        $shippingAddress = $order->getShippingAddress();
        if ($shippingAddress) {
            $hasShipping = true;

            $s_postcode = trim($shippingAddress->getPostcode());

            $s_country_iso2 = $shippingAddress->getCountryId();
            $s_country = PaytabsHelper::countryGetiso3($s_country_iso2);

            $s_streets = $shippingAddress->getStreet();
            $shipping_address = array_reduce($s_streets, function ($acc, $street) {
                return $acc . ', ' . $street;
            }, '');
        }

        /** 1.3. Read Products */

        // $items = $order->getAllItems();
        // $items = $order->getItems();
        $items = $order->getAllVisibleItems();

        $items_arr = array_map(function ($p) {
            return [
                'name' => $p->getName(),
                'quantity' => $p->getQtyOrdered(),
                'price' => $p->getPrice()
            ];
        }, $items);


        // System Parameters
        // $systemVersion = "Magento {$versionMagento}";


        /** 2. Fill post array */

        $pt_holder = new PaytabsHolder2();
        $pt_holder
            ->set01PaymentCode($paymentType)
            ->set02Transaction('sale', 'ecom')
            ->set03Cart($orderId, $currency, $amount, json_encode($items_arr))
            ->set04CustomerDetails(
                $billingAddress->getName(),
                $billingAddress->getEmail(),
                $billingAddress->getTelephone(),
                $billing_address,
                $billingAddress->getCity(),
                $billingAddress->getRegionCode(),
                $country,
                $postcode,
                null
            );

        if ($hasShipping) {
            $pt_holder->set05ShippingDetails(
                $shippingAddress->getName(),
                $shippingAddress->getEmail(),
                $shippingAddress->getTelephone(),
                $shipping_address,
                $shippingAddress->getCity(),
                $shippingAddress->getRegionCode(),
                $s_country,
                $s_postcode,
                null
            );
        }

        $pt_holder
            ->set06HideShipping(false)
            ->set07URLs($returnUrl, null)
            ->set08Lang($lang_code);

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
