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

use function PayTabs\PayPage\Gateway\Http\paytabs_error_log;

/**
 * Class Index
 */
class Callback extends Action
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
        $this->paytabs = new \PayTabs\PayPage\Gateway\Http\Client\Api;
        new PaytabsCore();
    }

    /**
     * @return ResponseInterface|ResultInterface|Page
     */
    public function execute()
    {
        if (!$this->getRequest()->isPost()) {
            paytabs_error_log("Paytabs: no post back data received in callback");
            return;
        }

        // Get the params that were passed from our Router

        $data = PaytabsHelper::read_ipn_response();
        $_p_tran_ref = 'tran_ref';
        $_p_cart_id = 'cart_id';

        $transactionId = $data->$_p_tran_ref;
        $pOrderId = $data->$_p_cart_id;

        //

        if (!$pOrderId || !$transactionId) {
            paytabs_error_log("Paytabs: OrderId/TransactionId data did not receive in callback");
            return;
        }

        //

        paytabs_error_log("Callback triggered, Order [{$pOrderId}], Transaction [{$transactionId}]", 1);

        //

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $order = $objectManager->create('Magento\Sales\Model\Order')->loadByIncrementId($pOrderId);

        if (!$order) {
            paytabs_error_log("Paytabs: Order is missing, Order [{$pOrderId}]");
            return;
        }

        $payment = $order->getPayment();
        $paymentMethod = $payment->getMethodInstance();

        $ptApi = $this->paytabs->pt($paymentMethod);

        // $verify_response = $ptApi->verify_payment($transactionId);
        $verify_response = $ptApi->read_response(true);
        if (!$verify_response) {
            return;
        }

        //

        return $this->pt_handle_callback($order, $verify_response, $payment);

        // return $this->pageFactory->create();
    }

    //

    private function pt_handle_callback($order, $verify_response, $payment)
    {
        $paymentMethod = $payment->getMethodInstance();

        $paymentSuccess =
            $paymentMethod->getConfigData('order_success_status') ?? Order::STATE_PROCESSING;
        $paymentFailed =
            $paymentMethod->getConfigData('order_failed_status') ?? Order::STATE_CANCELED;

        $sendInvoice = (bool) $paymentMethod->getConfigData('send_invoice');
        $emailConfig = $paymentMethod->getConfigData('email_config');
        $cart_refill = (bool) $paymentMethod->getConfigData('order_failed_reorder');
        $use_order_currency = CurrencySelect::UseOrderCurrency($payment);

        //

        $success = $verify_response->success;
        $res_msg = $verify_response->message;
        $orderId = @$verify_response->reference_no;
        $transaction_ref = @$verify_response->transaction_id;
        $transaction_type = @$verify_response->tran_type;

        //

        if (!$success) {
            paytabs_error_log("Paytabs Response: Payment verify failed, Order {$orderId}, Message [$res_msg]", 2);

            // $payment->deny();
            $payment->cancel();

            $order->addStatusHistoryComment(__('Payment failed: [%1].', $res_msg));

            if ($paymentFailed != Order::STATE_CANCELED) {
                $this->setNewStatus($order, $paymentFailed);
            } else {
                $order->cancel();
            }
            $order->save();

            return;
        }

        // Sucess

        $tranAmount = $verify_response->cart_amount;
        $tranCurrency = $verify_response->cart_currency;

        $payment
            ->setTransactionId($transaction_ref)
            ->setAdditionalInformation("payment_amount", $tranAmount)
            ->setAdditionalInformation("payment_currency", $tranCurrency)
            ->save();

        $payment->setAmountAuthorized($payment->getAmountOrdered());

        $paymentAmount = $this->getAmount($payment, $tranCurrency, $tranAmount, $use_order_currency);

        if (PaytabsEnum::TranIsSale($transaction_type)) {
            // $payment->capture();
            $payment->registerCaptureNotification($paymentAmount, true);
        } else {
            $payment
                ->setIsTransactionClosed(false)
                ->registerAuthorizationNotification($paymentAmount);
        }

        $payment->accept();

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

        paytabs_error_log("Order {$orderId}, Message [$res_msg]", 1);
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

    //

    public function getAmount($payment, $tranCurrency, $tranAmount, $use_order_currency)
    {
        $amount = null;

        $orderCurrency = strtoupper($payment->getOrder()->getOrderCurrencyCode());
        $baseCurrency  = strtoupper($payment->getOrder()->getBaseCurrencyCode());
        $tranCurrency  = strtoupper($tranCurrency);

        if ($use_order_currency) {
            if ($orderCurrency != $tranCurrency) {
                throw Exception('Diff Currency');
            }

            if ($tranCurrency == $baseCurrency) {
                $amount = $tranAmount;
            } else {
                // Convert Amount to Base
                $amount = CurrencySelect::convertOrderToBase($payment, $tranAmount);

                $payment->getOrder()
                    ->addStatusHistoryComment(
                        __(
                            'Transaction amount converted to base currency: (%1) = (%2)',
                            $payment->getOrder()->getOrderCurrency()->format($tranAmount, [], false),
                            $payment->formatPrice($amount)
                        )
                    )
                    ->setIsCustomerNotified(false)
                    ->save();
            }
        } else {
            if ($baseCurrency != $tranCurrency) {
                throw Exception('Diff Currency');
            }
            $amount = $tranAmount;
        }

        return $amount;
    }
}
