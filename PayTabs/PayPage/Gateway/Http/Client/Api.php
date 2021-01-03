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

        // PT
        $merchant_id = $paymentMethod->getConfigData('profile_id');
        $merchant_key = $paymentMethod->getConfigData('server_key');
        $endpoint = $paymentMethod->getConfigData('endpoint');

        new PaytabsCore2();
        $pt = PaytabsApi::getInstance($endpoint, $merchant_id, $merchant_key);

        return $pt;
    }

    /**
     * Extract required parameters from the Order, to Pass to create_page API
     * -Client information
     * -Shipping address
     * -Products
     * @return Array of values to pass to create_paypage API
     */
    public function prepare_order($order, $paymentMethod)
    {
        /** 1. Read required Params */

        $paymentType = $paymentMethod->getCode(); //'creditcard';

        $hide_shipping = $paymentMethod->getConfigData('hide_shipping') == '1';
        $framed_mode = $paymentMethod->getConfigData('iframe_mode') == '1';
        $test_local_mode = false;

        $orderId = $order->getIncrementId();

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
        $localeResolver = $objectManager->get('\Magento\Framework\Locale\ResolverInterface');
        // $productMetadata = $objectManager->get('Magento\Framework\App\ProductMetadataInterface');
        // $versionMagento = $productMetadata->getVersion();

        $currency = $order->getOrderCurrencyCode();
        $baseurl = $storeManager->getStore()->getBaseUrl();
        $returnUrl = "{$baseurl}paypage/paypage/response";
        $callbackUrl = "{$baseurl}paypage/paypage/ipn";

        if ($test_local_mode) {
            // PayTabs' server does not post data to local addresses on callback_url
            $public_ip = 'https://72bdf9d9dc04.ngrok.io/magento241/'; // Public IP address
            $callbackUrl = "{$public_ip}paypage/paypage/ipn";
        }

        $lang_code = $localeResolver->getLocale();
        // $lang = ($lang_code == 'ar' || substr($lang_code, 0, 3) == 'ar_') ? 'Arabic' : 'English';

        // Compute Prices

        $amount = $order->getGrandTotal();
        // $discountAmount = abs($order->getDiscountAmount());
        // $shippingAmount = $order->getShippingAmount();
        // $taxAmount = $order->getTaxAmount();

        // $amount += $discountAmount;
        // $otherCharges = $shippingAmount + $taxAmount;

        $amount = number_format((float) $amount, 2, '.', '');


        /** 1.2. Read BillingAddress info */

        $billingAddress = $order->getBillingAddress();
        // $firstName = $billingAddress->getFirstname();
        // $lastName = $billingAddress->getlastname();

        // $email = $billingAddress->getEmail();
        // $city = $billingAddress->getCity();

        $postcode = trim($billingAddress->getPostcode());

        // $region = $billingAddress->getRegionCode();
        $country_iso2 = $billingAddress->getCountryId();
        $country = PaytabsHelper::countryGetiso3($country_iso2);

        // $telephone = $billingAddress->getTelephone();
        $streets = $billingAddress->getStreet();
        $billing_address = implode(', ', $streets);

        // $cdetails = PaytabsHelper::getCountryDetails($country_iso2);
        // $phoneext = $cdetails['phone'];

        // $country = PaytabsHelper::countryGetiso3($country_iso2);

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
            $s_country = PaytabsHelper::countryGetiso3($s_country_iso2);

            $s_streets = $shippingAddress->getStreet();
            $shipping_address = implode(', ', $s_streets);
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

        // Computed Parameters
        // $title = $firstName . " " . $lastName;


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
            ->set06HideShipping($hide_shipping)
            ->set07URLs($returnUrl, $callbackUrl)
            ->set08Lang($lang_code)
            ->set09Framed($framed_mode);

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
