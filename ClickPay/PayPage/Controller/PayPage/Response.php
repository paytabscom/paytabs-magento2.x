<?php

// declare(strict_types=1);

namespace ClickPay\PayPage\Controller\Paypage;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Model\Order;
use ClickPay\PayPage\Gateway\Http\Client\Api;
use ClickPay\PayPage\Gateway\Http\ClickpayCore;
use ClickPay\PayPage\Gateway\Http\ClickpayEnum;
use ClickPay\PayPage\Model\Adminhtml\Source\EmailConfig;

use function ClickPay\PayPage\Gateway\Http\clickpay_error_log;

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
    private $clickpay;

    /**
     * @var Magento\Sales\Model\Order\Email\Sender\OrderSender
     */
    private $_orderSender;

    /**
     * @var Magento\Sales\Model\Order\Email\Sender\InvoiceSender
     */
    private $_invoiceSender;

    protected $quoteRepository;


    /**
     * @var \Psr\Log\LoggerInterface
     */
    // protected $_logger;

    /**
     * @param Context $context
     * @param PageFactory $pageFactory
     */
    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository

        // \Psr\Log\LoggerInterface $logger
    ) {
        parent::__construct($context);

        $this->pageFactory = $pageFactory;
        $this->_orderSender = $orderSender;
        $this->_invoiceSender = $invoiceSender;
        $this->quoteRepository = $quoteRepository;

        // $this->_logger = $logger;
        // $this->resultRedirect = $context->getResultFactory();
        $this->clickpay= new \ClickPay\PayPage\Gateway\Http\Client\Api;
        new ClickpayCore();
    }

    /**
     * @return ResponseInterface|ResultInterface|Page
     */
    public function execute()
    {
        if (!$this->getRequest()->isPost()) {
            clickpay_error_log("Clickpay: no post back data received in callback");
            return;
        }

        // Get the params that were passed from our Router
        $pOrderId = $this->getRequest()->getParam('p', null);

        // PT
        // ClickPay "Invoice ID"
        $transactionId = $this->getRequest()->getParam('tranRef', null);

        $resultRedirect = $this->resultRedirectFactory->create();

        //

        if (!$pOrderId || !$transactionId) {
            clickpay_error_log("Clickpay: OrderId/TransactionId data did not receive in callback");
            return;
        }

        //

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $order = $objectManager->create('Magento\Sales\Model\Order')->loadByIncrementId($pOrderId);

        if (!$order) {
            clickpay_error_log("Clickpay: Order is missing, Order param = [{$pOrderId}]");
            return;
        }

        $payment = $order->getPayment();
        $paymentMethod = $payment->getMethodInstance();

        $paymentSuccess =
            $paymentMethod->getConfigData('order_success_status') ?? Order::STATE_PROCESSING;
        $paymentFailed =
            $paymentMethod->getConfigData('order_failed_status') ?? Order::STATE_CANCELED;

        $sendInvoice = (bool) $paymentMethod->getConfigData('send_invoice');
        $emailConfig = $paymentMethod->getConfigData('email_config');
        $cart_refill = (bool) $paymentMethod->getConfigData('order_failed_reorder');

        $ptApi = $this->clickpay->pt($paymentMethod);

        $verify_response = $ptApi->verify_payment($transactionId);

        $success = $verify_response->success;
        $res_msg = $verify_response->message;
        $orderId = @$verify_response->reference_no;
        $transaction_ref = @$verify_response->transaction_id;
        $transaction_type = @$verify_response->tran_type;

        if (!$success) {
            clickpay_error_log("Clickpay Response: Payment verify failed [$res_msg] for Order {$pOrderId}");

            // $payment->deny();
            $payment->cancel();

            $order->addStatusHistoryComment(__('Payment failed: [%1].', $res_msg));

            if ($paymentFailed != Order::STATE_CANCELED) {
                $this->setNewStatus($order, $paymentFailed);
            } else {
                $order->cancel();
            }
            $order->save();

            $redirect_page = 'checkout/onepage/failure';

            if ($cart_refill) {
                try {
                    // Payment failed, Save the Quote (user's Cart)
                    $quoteId = $order->getQuoteId();
                    $quote = $this->quoteRepository->get($quoteId);
                    $quote->setIsActive(true)->removePayment()->save();

                    $_checkoutSession = $objectManager->create('\Magento\Checkout\Model\Session');
                    $_checkoutSession->replaceQuote($quote);

                    $redirect_page = 'checkout/cart';
                } catch (\Throwable $th) {
                    clickpay_error_log("Clickpay: load Quote by ID failed!, OrderId = [{$orderId}], QuoteId = [{$quoteId}] ");
                }
            }

            $this->messageManager->addErrorMessage('The payment failed - ' . $res_msg);
            $resultRedirect->setPath($redirect_page);
            return $resultRedirect;
        }

        if ($pOrderId != $orderId) {
            clickpay_error_log("Clickpay Response: Order reference number is mismatch, Order = [{$pOrderId}], ReferenceId = [{$verify_response->reference_no}] ");
            $this->messageManager->addWarningMessage('Order reference number is mismatch');
            $resultRedirect->setPath('checkout/onepage/failure');
            return $resultRedirect;
        }


        if (Api::hadPaid($order)) {
            $this->messageManager->addWarningMessage('A previous paid amount detected for this Order, please contact us for more information');
        }

        // ClickPay "Transaction ID"
        $paymentAmount = $verify_response->cart_amount;
        $paymentCurrency = $verify_response->cart_currency;

        $payment
            ->setTransactionId($transaction_ref)
            ->setLastTransId($transaction_ref)
            ->setAdditionalInformation("payment_amount", $paymentAmount)
            ->setAdditionalInformation("payment_currency", $paymentCurrency)
            ->save();

        $payment->accept();

        if (ClickpayEnum::TranIsSale($transaction_type)) {
            // $payment->capture();
            $payment->registerCaptureNotification($paymentAmount, true);
        } else {
            $payment
                ->setIsTransactionClosed(false)
                ->registerAuthorizationNotification($paymentAmount);
            // $payment->authorize(false, $paymentAmount);
            // $payment->setAmountAuthorized(11)
        }

        $canSendEmail = EmailConfig::canSendEMail(EmailConfig::EMAIL_PLACE_AFTER_PAYMENT, $emailConfig);
        if ($canSendEmail) {
            $order->setCanSendNewEmailFlag(true);
            $this->_orderSender->send($order);
        }

        if ($sendInvoice) {
            $this->invoice($order, $payment);
        }


        if ($paymentSuccess != Order::STATE_PROCESSING) {
            $this->setNewStatus($order, $paymentSuccess);
        }
        $order->save();

        $this->messageManager->addSuccessMessage('The payment has been completed successfully - ' . $res_msg);
        $resultRedirect->setPath('checkout/onepage/success');

        return $resultRedirect;

        // return $this->pageFactory->create();
    }

    //

    public function setNewStatus($order, $newStatus)
    {
        $order->setState($newStatus)->setStatus($newStatus);
        $order->addStatusToHistory($newStatus, "Order was set to '$newStatus' as in the admin's configuration.");
    }


    private function invoice($order, $payment)
    {
        $canInvoice = $order->canInvoice();
        if (!$canInvoice) return;

        $invoice = $payment->getCreatedInvoice();
        if ($invoice) { //} && !$order->getEmailSent()) {
            $sent = $this->_invoiceSender->send($invoice);
            $invoiceId = $invoice->getIncrementId();
            if ($sent) {
                $order
                    ->addStatusHistoryComment(
                        __('You notified customer about invoice #%1.', $invoiceId)
                    )
                    ->setIsCustomerNotified(true)
                    ->save();
            } else {
                $order
                    ->addStatusHistoryComment(
                        __('Failed to notify the customer about invoice #%1.', $invoiceId)
                    )
                    ->setIsCustomerNotified(false)
                    ->save();
            }
        }
    }
}

/**
 * move CSRF verification to Plugin
 * compitable with old Magento version >=2.0 && <2.3
 * compitable with PHP version 5.6
 */
