<?php

/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace ClickPay\PayPage\Gateway\Vault;

use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use ClickPay\PayPage\Gateway\Http\ClickPayCore;
use ClickPay\PayPage\Gateway\Http\ClickPayEnum;
use ClickPay\PayPage\Gateway\Http\ClickPayTokenHolder;
use ClickPay\PayPage\Model\Adminhtml\Source\CurrencySelect;

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
        $action = null,
        \Magento\Framework\App\ProductMetadataInterface $productMetadata
    ) {
        new ClickPayCore();
        $this->config = $config;

        if ($action != null) {
            $this->_action = $action;
        } else {
            $this->_action = ClickPayEnum::TRAN_TYPE_SALE;
        }

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

        $pt_holder = new ClickPayTokenHolder();
        $pt_holder
            ->set02Transaction($this->_action, ClickPayEnum::TRAN_CLASS_RECURRING)
            ->set03Cart($order_id, $currency, $amount, $cart_desc)
            ->set20Token($tran_ref, $token)
            ->set99PluginInfo('Magento', $versionMagento, ClickPay_PAYPAGE_VERSION);

        $values = $pt_holder->pt_build();

        $req_data = [
            'params' => $values
        ];

        return $req_data;
    }
}
