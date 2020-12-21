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
use PayTabs\PayPage\Gateway\Http\PaytabsCore2;

use function PayTabs\PayPage\Gateway\Http\paytabs_error_log;

/**
 * Class IPN
 */
class Ipn extends Action
{
    // protected $resultRedirect;
    private $paytabs;

    /**
     * @var Magento\Sales\Model\Order\Email\Sender\InvoiceSender
     */
    private $_invoiceSender;


    /**
     * @param Context $context
     * @param PageFactory $pageFactory
     */
    public function __construct(
        Context $context,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender
        // \Psr\Log\LoggerInterface $logger
    ) {
        parent::__construct($context);

        $this->_invoiceSender = $invoiceSender;
        // $this->_logger = $logger;
        // $this->resultRedirect = $context->getResultFactory();
        $this->paytabs = new \PayTabs\PayPage\Gateway\Http\Client\Api;
        new PaytabsCore2();
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

        $_params = $this->getRequest()->getContent();
        $_params = json_decode($_params);

        $transactionId = $_params->tran_ref;
        $cartId = $_params->cart_id;

        if (!$transactionId || !$cartId) {
            paytabs_error_log("PayTabs (IPN): no TransactionRef/CartId received");
            return;
        }

        //

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $order = $objectManager->create('Magento\Sales\Model\Order')->loadByIncrementId($cartId);
        if (!$order) {
            paytabs_error_log("PayTabs (IPN): Order is missing, Order param = [{$transactionId}, {$cartId}]");
            return;
        }

        $payment = $order->getPayment();
        $paymentMethod = $payment->getMethodInstance();

        $ptApi = $this->paytabs->pt($paymentMethod);
        $verify_response = $ptApi->verify_payment($transactionId);

        $success = $verify_response->success;
        $res_msg = $verify_response->message;
        $tran_type = $verify_response->tran_type;
        $tran_parent = @$verify_response->parentRequest;

        //

        $resultRedirect = $this->resultRedirectFactory->create();

        //

        switch ($tran_type) {
            case 'Auth':
                break;
            case 'Void':
                // $verify_response->parentRequest;
                break;

            case 'Sale':
            case 'Capture':
                // $verify_response->parentRequest;
                $this->handleCapture($order, $verify_response);

                break;

            case 'Refund':
                // $verify_response->parentRequest;
                $this->handleRefund($order, $verify_response);

                break;

            case 'Register':
                break;

            default:
                break;
        }


        return ; //$resultRedirect;
    }


    function handleCapture($order, $verify_response)
    {
        $success = $verify_response->success;
        $res_msg = $verify_response->message;
        $orderId = @$verify_response->reference_no;
        $transaction_ref = @$verify_response->transaction_id;
        $paymentAmount = $verify_response->cart_amount;
        $paymentCurrency = $verify_response->cart_currency;

        $payment = $order->getPayment();
        $paymentMethod = $payment->getMethodInstance();
        $paymentSuccess =
            $paymentMethod->getConfigData('order_success_status') ?? Order::STATE_PROCESSING;
        $paymentFailed =
            $paymentMethod->getConfigData('order_failed_status') ?? Order::STATE_CANCELED;

        $sendInvoice = $paymentMethod->getConfigData('send_invoice') ?? false;


        if (!$success) {
            paytabs_error_log("Paytabs Response: Payment verify failed [$res_msg] for Order {$orderId}");

            // $payment->deny();
            $payment->cancel();

            $order->addStatusHistoryComment(__('Payment failed: [%1].', $res_msg));

            if ($paymentFailed != Order::STATE_CANCELED) {
                $this->setNewStatus($order, $paymentFailed);
            } else {
                $order->cancel();
            }
            $order->save();
        } else {
            // PayTabs "Transaction ID"
            $paymentAmount = $verify_response->cart_amount;
            $paymentCurrency = $verify_response->cart_currency;

            $payment
                ->setTransactionId($transaction_ref)
                ->setLastTransId($transaction_ref)
                ->setIsTransactionClosed(true)
                ->setShouldCloseParentTransaction(true)
                ->setAdditionalInformation("payment_amount", $paymentAmount)
                ->setAdditionalInformation("payment_currency", $paymentCurrency)
                ->save();

            $payment->accept();

            // $payment->capture();
            $payment->registerCaptureNotification($paymentAmount, true)->save();

            if ($sendInvoice) {

                $invoice = $payment->getCreatedInvoice();
                if ($invoice) { //} && !$order->getEmailSent()) {
                    $sent = $this->_invoiceSender->send($invoice);
                    if ($sent) {
                        $order
                            ->addStatusHistoryComment(
                                __('You notified customer about invoice #%1.', $invoice->getIncrementId())
                            )
                            ->setIsCustomerNotified(true)
                            ->save();
                    } else {
                        $order
                            ->addStatusHistoryComment(
                                __('Failed to notify the customer about invoice #%1.', $invoice->getIncrementId())
                            )
                            ->setIsCustomerNotified(false)
                            ->save();
                    }
                }
            }

            if ($paymentSuccess != Order::STATE_PROCESSING) {
                $this->setNewStatus($order, $paymentSuccess);
            }
            $order->save();
        }
    }


    function handleRefund($order, $verify_response)
    {
        $payment = $order->getPayment();
        $invoice = $payment->getCreatedInvoice();
    }

    //

    public function setNewStatus($order, $newStatus)
    {
        $order->setState($newStatus)->setStatus($newStatus);
        $order->addStatusToHistory($newStatus, "Order was set to '$newStatus' as in the admin's configuration.");
    }
}
