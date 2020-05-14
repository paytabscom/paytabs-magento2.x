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

/**
 * Class Index
 */
class Response extends Action
{
    /**
     * @var PageFactory
     */
    private $pageFactory;
    // protected $resultRedirect;
    private $paytabs;

    /**
     * @var Magento\Sales\Model\Order\Email\Sender\OrderSender
     */
    private $_orderSender;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * @param Context $context
     * @param PageFactory $pageFactory
     */
    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Psr\Log\LoggerInterface $logger
    ) {
        parent::__construct($context);

        $this->pageFactory = $pageFactory;
        $this->_orderSender = $orderSender;
        $this->_logger = $logger;
        // $this->resultRedirect = $context->getResultFactory();
        $this->paytabs = new \PayTabs\PayPage\Gateway\Http\Client\Api;
    }

    /**
     * @return ResponseInterface|ResultInterface|Page
     */
    public function execute()
    {
        if (!$this->getRequest()->isPost()) {
            $this->_logger->addError("Paytabs: no post back data received in callback");
            return;
        }

        // Get the params that were passed from our Router
        $orderId = $this->getRequest()->getParam('p', null);

        // PayTabs "Invoice ID"
        $transactionId = $this->getRequest()->getParam('payment_reference', null);

        $resultRedirect = $this->resultRedirectFactory->create();

        //

        if (!$orderId || !$transactionId) {
            $this->_logger->addError("Paytabs: OrderId/TransactionId data did not receive in callback");
            return;
        }

        //

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $order = $objectManager->create('Magento\Sales\Model\Order')->loadByIncrementId($orderId);

        if (!$order) {
            $this->_logger->addError("Paytabs: Order is missing, Order param = [{$orderId}]");
            return;
        }

        $payment = $order->getPayment();
        $paymentMethod = $payment->getMethodInstance();

        $paymentSuccess = $paymentMethod->getConfigData('order_success_status');
        if (!$paymentSuccess) $paymentSuccess = Order::STATE_PROCESSING;
        $paymentFailed = $paymentMethod->getConfigData('order_failed_status');
        if (!$paymentFailed) $paymentFailed = Order::STATE_CANCELED;

        $sendInvoice = $paymentMethod->getConfigData('send_invoice');
        if (!$sendInvoice) $sendInvoice = false;

        $ptApi = $this->paytabs->pt($paymentMethod);

        $verify_response = $ptApi->verify_payment($transactionId);
        if (!$verify_response) {
            return;
        }

        // $orderId = $verify_response->reference_no;
        if ($orderId != $verify_response->reference_no) {
            $this->_logger->addError("Paytabs Response: Order reference number is mismatch, Order = [{$orderId}], ReferenceId = [{$verify_response->reference_no}] ");
            $this->messageManager->addWarningMessage('Order reference number is mismatch');
            $resultRedirect->setPath('checkout/onepage/failure');
            return $resultRedirect;
        }

        //if get response successful
        $success = ($verify_response->response_code == 100);
        $res_msg = $verify_response->result;

        $verifyPayment = $success;

        if ($verifyPayment) {
            if (Api::hadPaid($order)) {
                $this->messageManager->addWarningMessage('A previous paid amount detected for this Order, please contact us for more information');
            }

            // PayTabs "Transaction ID"
            $txnId = $verify_response->transaction_id;
            $paymentAmount = $verify_response->amount;
            $paymentCurrency = $verify_response->currency;

            $payment
                ->setTransactionId($txnId)
                ->setLastTransId($txnId)
                ->setCcTransId($txnId)
                ->setIsTransactionClosed(false)
                ->setShouldCloseParentTransaction(true)
                ->setAdditionalInformation("payment_amount", $paymentAmount)
                ->setAdditionalInformation("payment_currency", $paymentCurrency)
                ->save();

            if ($sendInvoice) {
                $payment->registerCaptureNotification($paymentAmount, true)->save();

                $invoice = $payment->getCreatedInvoice();
                if ($invoice && !$order->getEmailSent()) {
                    $this->_orderSender->send($order);
                    $order->addStatusHistoryComment(
                        __('You notified customer about invoice #%1.', $invoice->getIncrementId())
                    )
                        ->setIsCustomerNotified(true)
                        ->save();
                }
            }

            $transType = \Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE;
            $transaction = $payment->addTransaction($transType, null, false);
            $transaction
                ->setIsClosed(true)
                ->setParentTxnId(null)
                ->save();


            // $orderState = Order::STATE_PROCESSING;
            $this->setNewStatus($order, $paymentSuccess);

            $this->messageManager->addSuccessMessage($res_msg);
            $resultRedirect->setPath('checkout/onepage/success');
        } else {
            $this->_logger->addError("Paytabs Response: Payment verify failed [$res_msg] for Order {$orderId}");
            $payment->setIsTransactionPending(true);
            $payment->setIsFraudDetected(true);

            // $orderState = Order::STATE_CANCELED;
            $this->setNewStatus($order, $paymentFailed);

            $this->messageManager->addErrorMessage($res_msg);
            $resultRedirect->setPath('checkout/onepage/failure');
        }

        return $resultRedirect;

        // return $this->pageFactory->create();
    }

    //

    public function setNewStatus($order, $newStatus)
    {
        if ($newStatus == Order::STATE_CANCELED) {
            $order->cancel();
        } else {
            $order->setState($newStatus)->setStatus($newStatus);
            $order->addStatusToHistory($newStatus, "Order was set to '$newStatus' as in the admin's configuration.");
        }
        $order->save();
    }
}

/**
 * move CRSF verification to Plugin
 * compitable with old Magento version >=2.0 && <2.3
 * compitable with PHP version 5.6
 */
