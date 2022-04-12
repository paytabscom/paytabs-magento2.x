<?php

// declare(strict_types=1);

namespace ClickPay\PayPage\Controller\PayPage;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use ClickPay\PayPage\Gateway\Http\ClickPayCore;
use ClickPay\PayPage\Gateway\Http\ClickPayEnum;
use ClickPay\PayPage\Gateway\Http\ClickPayHelper;
use ClickPay\PayPage\Model\Adminhtml\Source\CurrencySelect;
use ClickPay\PayPage\Gateway\Http\ClickPayHelpers;


/**
 * Class IPN
 */
class Ipn extends Action
{
    use ClickPayHelpers;

    // protected $resultRedirect;
    private $ClickPay;

    /**
     * @var Magento\Sales\Model\Order\Email\Sender\InvoiceSender
     */
    private $_invoiceSender;

    // private $_creditmemoFactory;
    // private $_creditmemoService;

    private $_row_details = \Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS;

    /**
     * @param Context $context
     * @param PageFactory $pageFactory
     */
    public function __construct(
        Context $context,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender
        // \Magento\Sales\Model\Order\CreditmemoFactory $_creditmemoFactory,
        // \Magento\Sales\Model\Service\CreditmemoService $_creditmemoService
        // \Psr\Log\LoggerInterface $logger
    ) {
        parent::__construct($context);

        $this->_invoiceSender = $invoiceSender;
        // $this->_creditmemoFactory = $_creditmemoFactory;
        // $this->_creditmemoService = $_creditmemoService;

        // $this->_logger = $logger;
        // $this->resultRedirect = $context->getResultFactory();
        $this->ClickPay = new \ClickPay\PayPage\Gateway\Http\Client\Api;
        new ClickPayCore();
    }

    /**
     * @return ResponseInterface|ResultInterface|Page
     */
    public function execute()
    {
        if (!$this->getRequest()->isPost()) {
            ClickPayHelper::log("ClickPay (IPN): no post back data received", 3);
            return;
        }

        $data = ClickPayHelper::read_ipn_response();
        if (!$data) {
            return;
        }

        $_p_tran_ref = 'tran_ref';
        $_p_cart_id = 'cart_id';

        $transactionId = @$data->$_p_tran_ref;
        $pOrderId = @$data->$_p_cart_id;

        if (!$pOrderId || !$transactionId) {
            ClickPayHelper::log("ClickPay (IPN): no TransactionRef/CartId received", 3);
            return;
        }

        //

        ClickPayHelper::log("IPN triggered, Order [{$pOrderId}], Transaction [{$transactionId}], Action [{$data->tran_type}]", 1);

        //

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $order = $objectManager->create('Magento\Sales\Model\Order')->loadByIncrementId($pOrderId);

        if (!$this->isValidOrder($order)) {
            ClickPayHelper::log("ClickPay (IPN): Order is missing, Order [{$pOrderId}]", 3);
            return;
        }

        $payment = $order->getPayment();
        $paymentMethod = $payment->getMethodInstance();

        $ipnAllowed = $paymentMethod->getConfigData('ipn_allow') ?? false;
        if (!$ipnAllowed) {
            ClickPayHelper::log("ClickPay: [{$paymentMethod->getCode()}] IPN is not allowed", 2);
            return;
        }

        $ptApi = $this->ClickPay->pt($paymentMethod);

        $verify_response = $ptApi->read_response(true);
        if (!$verify_response) {
            return;
        }

        $this->pt_process_ipn($order, $verify_response);

        $order->save();

        return;
    }


    function pt_process_ipn($order, $ipn_data)
    {
        $pt_success = $ipn_data->success;
        $pt_message = $ipn_data->message;
        // $pt_token = @$ipn_data->token;

        $pt_tran_ref = $ipn_data->tran_ref;
        $pt_prev_tran_ref = @$ipn_data->previous_tran_ref;

        $pt_order_id = $ipn_data->cart_id;
        $pt_tran_total = $ipn_data->tran_total;
        $pt_tran_currency = $ipn_data->tran_currency;

        $pt_tran_type = strtolower($ipn_data->tran_type);

        //

        $payment = $order->getPayment();
        $use_order_currency = CurrencySelect::UseOrderCurrency($payment);

        $paymentAmount = $this->getAmount($payment, $pt_tran_currency, $pt_tran_total, $use_order_currency);


        if (!$pt_success) {
            ClickPayHelper::log("ClickPay Response: Payment failed [$pt_message], Order [{$order->getIncrementId()}], Transaction [{$pt_tran_ref}]", 3);
            $order->addStatusHistoryComment(__('Transaction failed: [%1], Transaction [%2], Amount [%3].', $pt_message, $pt_tran_ref, $paymentAmount));

            return;
        }

        ClickPayHelper::log("IPN handeling, Order [{$pt_order_id}], Transaction [{$pt_tran_ref}], Action [{$pt_tran_type}], Message [$pt_message]", 1);

        //

        $payment->setTransactionAdditionalinfo($this->_row_details, [
            'tran_amount'   => $pt_tran_total,
            'tran_currency' => $pt_tran_currency,
            'amount' => $paymentAmount
        ]);

        $payment
            ->setTransactionId($pt_tran_ref)
            ->setParentTransactionId($pt_prev_tran_ref);

        //

        switch ($pt_tran_type) {
            case ClickPayEnum::TRAN_TYPE_AUTH:
            case ClickPayEnum::TRAN_TYPE_SALE:
            case ClickPayEnum::TRAN_TYPE_REGISTER:
                ClickPayHelper::log("IPN does not support creating new Order", 2);

                break;

            case ClickPayEnum::TRAN_TYPE_CAPTURE:

                /**
                 * Check if the trx has been deliveried before
                 * If success: register the transaction
                 * If fail:
                 *  - Do nothing
                 */
                $this->handleCapture($order, $paymentAmount);

                break;

            case ClickPayEnum::TRAN_TYPE_VOID:
            case ClickPayEnum::TRAN_TYPE_RELEASE:

                $this->handleVoid($order, $paymentAmount);

                break;

            case ClickPayEnum::TRAN_TYPE_REFUND:

                $this->handleRefund($order, $paymentAmount);

                break;

            default:
                break;
        }

        return;
    }


    function handleCapture($order, $paymentAmount)
    {
        $payment = $order->getPayment();

        $payment
            ->registerCaptureNotification($paymentAmount, true);

        if ($order->canUnHold()) {
            $order->unhold();

            $order->addCommentToStatusHistory("UnHold from online");
            ClickPayHelper::log("Order {$order->getIncrementId()}, UnHold", 1);
        }

        /*
        if ($this->isSameGrandAmount($order, $use_order_currency, $paymentAmount)) {
            // $payment->deny();
            // $payment->cancel();
        } else {
            // $order->hold();
        }*/
    }


    function handleRefund($order, $paymentAmount)
    {
        $payment = $order->getPayment();

        $payment
            ->registerRefundNotification($paymentAmount);
    }


    function handleVoid($order, $paymentAmount)
    {
        $payment = $order->getPayment();

        $payment
            ->registerVoidNotification($paymentAmount);
    }
}
