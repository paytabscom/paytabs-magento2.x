<?php

/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace PayTabs\PayPage\Gateway\Vault;

use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use PayTabs\PayPage\Gateway\Http\PaytabsCore;
use PayTabs\PayPage\Gateway\Http\PaytabsEnum;
use PayTabs\PayPage\Gateway\Http\PaytabsHelper;
use PayTabs\PayPage\Gateway\Http\PaytabsTokenHolder;
use PayTabs\PayPage\Model\Adminhtml\Source\CurrencySelect;

class AuthorizationRequest implements BuilderInterface
{
    /**
     * @var ConfigInterface
     */
    private $config;

    private $_action;

    private $productMetadata;

    /**
     * @param ConfigInterface $config
     */
    public function __construct(
        ConfigInterface $config,
        $action,
        \Magento\Framework\App\ProductMetadataInterface $productMetadata
    ) {
        new PaytabsCore();
        $this->config = $config;

        $this->_action = $action;

        $this->productMetadata = $productMetadata;
    }

    /**
     * Builds ENV request
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        if (
            !isset($buildSubject['payment'])
            || !$buildSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        /** @var PaymentDataObjectInterface $paymentDO */
        $paymentDO = $buildSubject['payment'];
        $amount = $buildSubject['amount'];

        // $order = $paymentDO->getOrder();
        $payment = $paymentDO->getPayment();

        if (!$payment instanceof OrderPaymentInterface) {
            throw new \LogicException('Order payment should be provided.');
        }

        // $paymentMethod = $payment->getMethodInstance();
        // PT
        // ToDo: fix reading the main method currency option
        $use_order_currency = CurrencySelect::UseOrderCurrency($payment);

        //

        $versionMagento = $this->productMetadata->getVersion();

        //

        if ($use_order_currency) {
            $currency = $payment->getOrder()->getOrderCurrencyCode();
            $amount = $payment->getOrder()->getBaseCurrency()->convert($amount, $currency);
            $amount = $payment->formatAmount($amount, true);
        } else {
            $currency = $payment->getOrder()->getBaseCurrencyCode();
        }

        $order = $payment->getOrder();
        $order_id = $payment->getOrder()->getIncrementId();

        //

        PaytabsHelper::log("Init Vault!, Order [{$order_id}], Amount {$amount} {$currency}", 1);

        //

        $items = $order->getAllVisibleItems();

        $items_arr = array_map(function ($p) {
            $q = (int)$p->getQtyOrdered();
            return "{$p->getName()} ({$q})";
        }, $items);

        $cart_desc = implode(', ', $items_arr);

        //

        $extensionAttributes = $payment->getExtensionAttributes();
        $paymentToken = $extensionAttributes->getVaultPaymentToken();

        $token = $paymentToken->getGatewayToken();
        $token_details = json_decode($paymentToken->getTokenDetails());
        $tran_ref = $token_details->tran_ref;

        //

        $pt_holder = new PaytabsTokenHolder();
        $pt_holder
            ->set02Transaction($this->_action, PaytabsEnum::TRAN_CLASS_RECURRING)
            ->set03Cart($order_id, $currency, $amount, $cart_desc)
            ->set20Token($tran_ref, $token)
            ->set99PluginInfo('Magento', $versionMagento, PAYTABS_PAYPAGE_VERSION);

        $values = $pt_holder->pt_build();

        $req_data = [
            'params' => $values
        ];

        return $req_data;
    }
}
