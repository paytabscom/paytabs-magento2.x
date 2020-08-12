<?php

namespace PayTabs\PayPage\Gateway\Http\Client;

use PayTabs\PayPage\Gateway\Http\PaytabsApi;
use PayTabs\PayPage\Gateway\Http\PaytabsCore;
use PayTabs\PayPage\Gateway\Http\PaytabsHelper;
use PayTabs\PayPage\Gateway\Http\PaytabsHolder;

class Api
{
    public function pt($paymentMethod)
    {
        // $paymentType = $paymentMethod->getCode();

        $merchant_id = $paymentMethod->getConfigData('merchant_email');
        $merchant_key = $paymentMethod->getConfigData('merchant_secret');

        new PaytabsCore();
        $pt = PaytabsApi::getInstance($merchant_id, $merchant_key);

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

        $hide_personal_info = $paymentMethod->getConfigData('hide_personal_info') == '1';
        $hide_billing = $paymentMethod->getConfigData('hide_billing') == '1';
        $hide_view_invoice = $paymentMethod->getConfigData('hide_view_invoice') == '1';

        $orderId = $order->getIncrementId();

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
        $localeResolver = $objectManager->get('\Magento\Framework\Locale\ResolverInterface');
        $productMetadata = $objectManager->get('Magento\Framework\App\ProductMetadataInterface');
        $versionMagento = $productMetadata->getVersion();

        $currency = $order->getOrderCurrencyCode();
        $baseurl = $storeManager->getStore()->getBaseUrl();
        $returnUrl = $baseurl . "paypage/paypage/response?p=$orderId";

        $lang_code = $localeResolver->getLocale();
        $lang = ($lang_code == 'ar' || substr($lang_code, 0, 3) == 'ar_') ? 'Arabic' : 'English';

        // Compute Prices

        $amount = $order->getGrandTotal();
        $shippingAmount = $order->getShippingAmount();
        $discountAmount = abs($order->getDiscountAmount());
        $taxAmount = $order->getTaxAmount();

        $amount += $discountAmount;
        $otherCharges = $shippingAmount + $taxAmount;

        $amount = number_format((float) $amount, 2, '.', '');


        /** 1.2. Read BillingAddress info */

        $billingAddress = $order->getBillingAddress();
        $firstName = $billingAddress->getFirstname();
        $lastName = $billingAddress->getlastname();

        $email = $billingAddress->getEmail();
        $city = $billingAddress->getCity();

        $postcode = trim($billingAddress->getPostcode());

        $region = $billingAddress->getRegionCode();
        $country_iso2 = $billingAddress->getCountryId();
        $telephone = $billingAddress->getTelephone();
        $streets = $billingAddress->getStreet();

        $cdetails = PaytabsHelper::getCountryDetails($country_iso2);
        $phoneext = $cdetails['phone'];

        $country = PaytabsHelper::countryGetiso3($country_iso2);


        $shippingAddress = $order->getShippingAddress();
        if ($shippingAddress) {
            $s_firstName = $shippingAddress->getFirstname();
            $s_lastName = $shippingAddress->getlastname();
            $s_city = $shippingAddress->getCity();

            $s_postcode = trim($shippingAddress->getPostcode());

            $s_region = $shippingAddress->getRegionCode();
            $s_country_iso2 = $shippingAddress->getCountryId();

            $s_streets = $shippingAddress->getStreet();

            $s_country = PaytabsHelper::countryGetiso3($s_country_iso2);
        } else {
            $s_firstName = $firstName;
            $s_lastName = $lastName;
            $s_city = $city;

            $s_postcode = $postcode;

            $s_region = $region;
            $s_country_iso2 = $country_iso2;

            $s_streets = $streets;

            $s_country = $country;
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
        $systemVersion = "Magento {$versionMagento}";

        // Computed Parameters
        $title = $firstName . " " . $lastName;
        $billing_address = implode(', ', $streets);
        $shipping_address = implode(', ', $s_streets);


        /** 2. Fill post array */

        $pt_holder = new PaytabsHolder();
        $pt_holder
            ->set01PaymentCode($paymentType)
            ->set02ReferenceNum($orderId)
            ->set03InvoiceInfo($title, $lang)
            ->set04Payment($currency, $amount, $otherCharges, $discountAmount)
            ->set05Products($items_arr)
            ->set06CustomerInfo($firstName, $lastName, $phoneext, $telephone, $email)
            ->set07Billing($billing_address, $region, $city, $postcode, $country)
            ->set08Shipping($s_firstName, $s_lastName, $shipping_address, $s_region, $s_city, $s_postcode, $s_country)
            ->set09HideOptions($hide_personal_info, $hide_billing, $hide_view_invoice)
            ->set10URLs($baseurl, $returnUrl)
            ->set11CMSVersion($systemVersion)
            ->set12IPCustomer('');

        if ($paymentType == 'valu') {
            $valu_product_id = $paymentMethod->getConfigData('valu_product_id');
            $pt_holder->set20ValuParams($valu_product_id, 0);
        }

        $post_arr = $pt_holder->pt_build(true);

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
