<?php

// declare(strict_types=1);

namespace PayTabs\PayPage\Controller\PayPage;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use PayTabs\PayPage\Gateway\Http\PaytabsCore;
use PayTabs\PayPage\Gateway\Http\PaytabsEnum;
use PayTabs\PayPage\Gateway\Http\PaytabsHelper;
use PayTabs\PayPage\Model\Adminhtml\Source\CurrencySelect;
use PayTabs\PayPage\Gateway\Http\PaytabsHelpers;


/**
 * Class IPN
 */
class Ipn extends Action
{
    use PaytabsHelpers;

    // protected $resultRedirect;
    private $paytabs;

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
        $this->paytabs = new \PayTabs\PayPage\Gateway\Http\Client\Api;
        new PaytabsCore();
    }

    /**
     * @return ResponseInterface|ResultInterface|Page
     */
    public function execute()
    {
        if (!$this->getRequest()->isPost()) {
            PaytabsHelper::log("PayTabs (IPN): no post back data received", 3);
            return;
        }

        $data = PaytabsHelper::read_ipn_response();
        if (!$data) {
            return;
        }

        $_p_tran_ref = 'tran_ref';
        $_p_cart_id = 'cart_id';

        $transactionId = @$data->$_p_tran_ref;
        $pOrderId = @$data->$_p_cart_id;

        if (!$pOrderId || !$transactionId) {
            PaytabsHelper::log("PayTabs (IPN): no TransactionRef/CartId received", 3);
            return;
        }

        //

        PaytabsHelper::log("IPN triggered, Order [{$pOrderId}], Transaction [{$transactionId}], Action [{$data->tran_type}]", 1);

        //

        try {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

            $is_quote = substr($pOrderId, 0, 1) === "Q";
            if ($is_quote) {
                $pOrderId = substr($pOrderId, 1);
                $quote = $objectManager->create('Magento\Quote\Api\CartRepositoryInterface')->get($pOrderId);

                $pOrderId = $quote->getReservedOrderId();
            }

            $order = $objectManager->create('Magento\Sales\Model\Order')->loadByIncrementId($pOrderId);
        } catch (\Throwable $th) {
            PaytabsHelper::log("PayTabs (IPN): Order could not be loaded, Order [{$pOrderId}]", 3);
            return;
        }

        if (!$this->isValidOrder($order)) {
            PaytabsHelper::log("PayTabs (IPN): Order is missing, Order [{$pOrderId}]", 3);
            return;
        }

        $payment = $order->getPayment();
        $paymentMethod = $payment->getMethodInstance();

        $ipnAllowed = $paymentMethod->getConfigData('ipn_allow') ?? false;
        if (!$ipnAllowed) {
            PaytabsHelper::log("PayTabs: [{$paymentMethod->getCode()}] IPN is not allowed", 2);
            return;
        }

        $ptApi = $this->paytabs->pt($paymentMethod);

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
            PaytabsHelper::log("Paytabs Response: Payment failed [$pt_message], Order [{$order->getIncrementId()}], Transaction [{$pt_tran_ref}]", 3);
            $order->addStatusHistoryComment(__('Transaction failed: [%1], Transaction [%2], Amount [%3].', $pt_message, $pt_tran_ref, $paymentAmount));

            return;
        }

        PaytabsHelper::log("IPN handeling, Order [{$pt_order_id}], Transaction [{$pt_tran_ref}], Action [{$pt_tran_type}], Message [$pt_message]", 1);

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
            case PaytabsEnum::TRAN_TYPE_AUTH:
            case PaytabsEnum::TRAN_TYPE_SALE:
            case PaytabsEnum::TRAN_TYPE_REGISTER:
                PaytabsHelper::log("IPN does not support creating new Order", 2);

                break;

            case PaytabsEnum::TRAN_TYPE_CAPTURE:

                /**
                 * Check if the trx has been deliveried before
                 * If success: register the transaction
                 * If fail:
                 *  - Do nothing
                 */
                $this->handleCapture($order, $paymentAmount);

                break;

            case PaytabsEnum::TRAN_TYPE_VOID:
            case PaytabsEnum::TRAN_TYPE_RELEASE:

                $this->handleVoid($order, $paymentAmount);

                break;

            case PaytabsEnum::TRAN_TYPE_REFUND:

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
            PaytabsHelper::log("Order {$order->getIncrementId()}, UnHold", 1);
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
