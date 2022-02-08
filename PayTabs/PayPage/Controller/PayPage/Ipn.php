<?php

// declare(strict_types=1);

namespace PayTabs\PayPage\Controller\Paypage;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Model\Order;
use PayTabs\PayPage\Gateway\Http\Client\Api;
use PayTabs\PayPage\Gateway\Http\PaytabsCore;
use PayTabs\PayPage\Gateway\Http\PaytabsEnum;
use PayTabs\PayPage\Gateway\Http\PaytabsHelper;
use PayTabs\PayPage\Model\Adminhtml\Source\CurrencySelect;
use PayTabs\PayPage\Model\Adminhtml\Source\EmailConfig;
use PayTabs\PayPage\Gateway\Http\PaytabsHelpers;

use function PayTabs\PayPage\Gateway\Http\paytabs_error_log;

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

    private $_creditmemoFactory;
    private $_creditmemoService;


    /**
     * @param Context $context
     * @param PageFactory $pageFactory
     */
    public function __construct(
        Context $context,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Magento\Sales\Model\Order\CreditmemoFactory $_creditmemoFactory,
        \Magento\Sales\Model\Service\CreditmemoService $_creditmemoService
        // \Psr\Log\LoggerInterface $logger
    ) {
        parent::__construct($context);

        $this->_invoiceSender = $invoiceSender;
        $this->_creditmemoFactory = $_creditmemoFactory;
        $this->_creditmemoService = $_creditmemoService;

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
            paytabs_error_log("PayTabs (IPN): no post back data received");
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
            paytabs_error_log("PayTabs (IPN): no TransactionRef/CartId received");
            return;
        }

        //

        paytabs_error_log("IPN triggered, Order [{$pOrderId}], Transaction [{$transactionId}], Action [{$data->tran_type}]", 1);

        //

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $order = $objectManager->create('Magento\Sales\Model\Order')->loadByIncrementId($pOrderId);

        if (!$order) {
            paytabs_error_log("PayTabs (IPN): Order is missing, Order [{$pOrderId}]");
            return;
        }

        $payment = $order->getPayment();
        $paymentMethod = $payment->getMethodInstance();

        $ptApi = $this->paytabs->pt($paymentMethod);

        $verify_response = $ptApi->read_response(true);
        if (!$verify_response) {
            return;
        }

        $this->pt_process_ipn($order, $verify_response, $payment);

        return;
    }


    function pt_process_ipn($order, $ipn_data, $payment)
    {
        $pt_success = $ipn_data->success;
        $pt_message = $ipn_data->message;
        $pt_token = @$ipn_data->token;

        $pt_tran_ref = $ipn_data->tran_ref;
        $pt_prev_tran_ref = @$ipn_data->previous_tran_ref;

        $pt_order_id = $ipn_data->cart_id;
        $pt_tran_total = $ipn_data->tran_total;
        $pt_tran_currency = $ipn_data->tran_currency;

        $pt_tran_type = strtolower($ipn_data->tran_type);


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
                 * If fail: cancel the Payment
                 */
                $this->handleCapture($order, $ipn_data);

                break;

            case PaytabsEnum::TRAN_TYPE_VOID:
            case PaytabsEnum::TRAN_TYPE_RELEASE:

                break;

            case PaytabsEnum::TRAN_TYPE_REFUND:

                $this->handleRefund($order, $ipn_data);

                break;

            default:
                break;
        }

        return;
    }


    function handleCapture($order, $ipn_data)
    {
        $pt_success = $ipn_data->success;
        $pt_message = $ipn_data->message;

        $pt_tran_ref = $ipn_data->tran_ref;
        $pt_prev_tran_ref = @$ipn_data->previous_tran_ref;

        $payment = $order->getPayment();
        $paymentMethod = $payment->getMethodInstance();

        $sendInvoice = $paymentMethod->getConfigData('send_invoice') ?? false;
        $use_order_currency = CurrencySelect::UseOrderCurrency($payment);

        $paymentSuccess =
            $paymentMethod->getConfigData('order_success_status') ?? Order::STATE_PROCESSING;
        $paymentFailed =
            $paymentMethod->getConfigData('order_failed_status') ?? Order::STATE_CANCELED;


        $tranAmount = $ipn_data->cart_amount;
        $tranCurrency = $ipn_data->cart_currency;
        $paymentAmount = $this->getAmount($payment, $tranCurrency, $tranAmount, $use_order_currency);

        $payment
            ->setTransactionId($pt_tran_ref)
            ->setParentTransactionId($pt_prev_tran_ref);

        if ($pt_success) {

            $payment
                ->registerCaptureNotification($paymentAmount, true)
                ->save();

            /*if ($paymentSuccess != Order::STATE_PROCESSING) {
                $this->setNewStatus($order, $paymentSuccess);
            }*/
        } else {

            paytabs_error_log("Paytabs Response: Payment failed [$pt_message], Order [{$order->getId()}], Transaction [{$pt_tran_ref}]");

            if ($this->isSameGrandAmount($order, $use_order_currency, $paymentAmount)) {
                // $payment->deny();
                $payment->cancel();
            } else {
                $order->hold();
            }

            $order->addStatusHistoryComment(__('Payment failed: [%1], Transaction [%2].', $pt_message, $pt_tran_ref));

            /*if ($paymentFailed != Order::STATE_CANCELED) {
                $this->setNewStatus($order, $paymentFailed);
            } else {
                $order->cancel();
            }*/
        }

        $order->save();
    }


    function handleRefund($order, $ipn_data)
    {
        $pt_success = $ipn_data->success;
        $pt_message = $ipn_data->message;

        $pt_tran_ref = $ipn_data->tran_ref;
        $pt_prev_tran_ref = @$ipn_data->previous_tran_ref;

        $payment = $order->getPayment();
        $paymentMethod = $payment->getMethodInstance();

        $use_order_currency = CurrencySelect::UseOrderCurrency($payment);


        if ($pt_success) {

            $tranAmount = $ipn_data->cart_amount;
            $tranCurrency = $ipn_data->cart_currency;

            $paymentAmount = $this->getAmount($payment, $tranCurrency, $tranAmount, $use_order_currency);

            $payment
                ->setTransactionId($pt_tran_ref)
                ->setParentTransactionId($pt_prev_tran_ref)
                ->registerRefundNotification($paymentAmount)
                ->save();

            $order->save();
        } else {

            paytabs_error_log("Paytabs Response: Payment verify failed [$pt_message] for Order {$order->getId()}");

            $order->addStatusHistoryComment(__('Payment failed: [%1].', $pt_message));

            $order->save();
        }
    }
}