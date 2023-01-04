<?php

// declare(strict_types=1);

namespace ClickPay\PayPage\Controller\PayPage;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Model\Order;
use ClickPay\PayPage\Gateway\Http\ClickPayCore;
use ClickPay\PayPage\Gateway\Http\ClickPayEnum;
use ClickPay\PayPage\Gateway\Http\ClickPayHelper;
use ClickPay\PayPage\Gateway\Http\ClickPayHelpers;
use ClickPay\PayPage\Model\Adminhtml\Source\CurrencySelect;
use ClickPay\PayPage\Model\Adminhtml\Source\EmailConfig;

use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;


/**
 * Class Index
 */
class Callback extends Action
{
    use ClickPayHelpers;

    /**
     * @var PageFactory
     */
    private $pageFactory;
    // protected $resultRedirect;

    private $ClickPay;

    /**
     * @var Magento\Sales\Model\Order\Email\Sender\OrderSender
     */
    private $_orderSender;

    /**
     * @var Magento\Sales\Model\Order\Email\Sender\InvoiceSender
     */
    private $_invoiceSender;

    protected $quoteRepository;


    private $_paymentTokenFactory;


    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    private $_row_details = \Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS;


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
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        PaymentTokenFactoryInterface $paymentTokenFactory,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor

        // \Psr\Log\LoggerInterface $logger
    ) {
        parent::__construct($context);

        $this->pageFactory = $pageFactory;
        $this->_orderSender = $orderSender;
        $this->_invoiceSender = $invoiceSender;
        $this->quoteRepository = $quoteRepository;
        $this->_paymentTokenFactory = $paymentTokenFactory;
        $this->encryptor = $encryptor;

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
            ClickPayHelper::log("ClickPay: no post back data received in callback", 3);
            return;
        }

        // Get the params that were passed from our Router

        $data = ClickPayHelper::read_ipn_response();
        if (!$data) {
            return;
        }
        $_p_tran_ref = 'tran_ref';
        $_p_cart_id = 'cart_id';

        $transactionId = @$data->$_p_tran_ref;
        $pOrderId = @$data->$_p_cart_id;

        //

        if (!$pOrderId || !$transactionId) {
            ClickPayHelper::log("ClickPay: OrderId/TransactionId data did not receive in callback", 3);
            return;
        }

        //

        ClickPayHelper::log("Callback triggered, Order [{$pOrderId}], Transaction [{$transactionId}]", 1);

        //

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $order = $objectManager->create('Magento\Sales\Model\Order')->loadByIncrementId($pOrderId);

        if (!$this->isValidOrder($order)) {
            ClickPayHelper::log("ClickPay: Order is missing, Order [{$pOrderId}]", 3);
            return;
        }

        $payment = $order->getPayment();
        $paymentMethod = $payment->getMethodInstance();

        $ptApi = $this->ClickPay->pt($paymentMethod);

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
        // $cart_refill = (bool) $paymentMethod->getConfigData('order_failed_reorder');
        $use_order_currency = CurrencySelect::UseOrderCurrency($payment);

        //

        $success = $verify_response->success;
        $is_on_hold = $verify_response->is_on_hold;
        $is_pending = $verify_response->is_pending;
        $res_msg = $verify_response->message;
        $orderId = @$verify_response->reference_no;
        $transaction_ref = @$verify_response->transaction_id;
        $pt_prev_tran_ref = @$verify_response->previous_tran_ref;
        $transaction_type = @$verify_response->tran_type;
        $response_code = @$verify_response->response_code;

        //

        $_fail = !($success || $is_on_hold || $is_pending);

        if ($_fail) {
            ClickPayHelper::log("ClickPay Response: Payment verify failed, Order {$orderId}, Message [$res_msg]", 2);

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

        // Success or OnHold  or Pending

        $tranAmount = $verify_response->cart_amount;
        $tranCurrency = $verify_response->cart_currency;

        $_tran_details = [
            'tran_amount'   => $tranAmount,
            'tran_currency' => $tranCurrency,
            'tran_type'     => $transaction_type,
            'response_code' => $response_code
        ];
        if ($pt_prev_tran_ref) {
            $_tran_details['previous_tran'] = $pt_prev_tran_ref;
        }

        $payment
            ->setTransactionId($transaction_ref)
            ->setTransactionAdditionalinfo($this->_row_details, $_tran_details);


        $paymentAmount = $this->getAmount($payment, $tranCurrency, $tranAmount, $use_order_currency);

        if ($is_pending) {
            $payment
                ->setIsTransactionPending(true)
                ->setIsTransactionClosed(false);

            //

            ClickpayHelper::log("Order {$orderId}, On-Hold (Pending), transaction {$transaction_ref}", 1);

            $order->hold();

            // Add Comment to Store Admin
            $order->addStatusHistoryComment("Transaction {$transaction_ref} is Pending, (Reference number: {$response_code}).");

            // Add comment to the Customer
            $order->addCommentToStatusHistory("Payment Reference number: {$response_code}", false, true);

            $order->save();

            return;
        }

        $payment->setAmountAuthorized($payment->getAmountOrdered());
        

        if (ClickPayEnum::TranIsSale($transaction_type)) {

            if ($pt_prev_tran_ref) {
                $payment->setParentTransactionId($pt_prev_tran_ref);
            }

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
            $this->invoiceSend($order, $payment);
        }

        if ($success) {

            if ($paymentSuccess != Order::STATE_PROCESSING) {
                $this->setNewStatus($order, $paymentSuccess);
            }

            //

            $this->pt_manage_tokenize($this->_paymentTokenFactory, $this->encryptor, $payment, $verify_response);

            //
        } elseif ($is_on_hold) {
            $order->hold();

            ClickPayHelper::log("Order {$orderId}, On-Hold, transaction {$transaction_ref}", 1);
            $order->addCommentToStatusHistory("Transaction {$transaction_ref} is On-Hold, Go to ClickPay dashboard to Approve/Decline it");
        }

        //

        $order->save();

        ClickPayHelper::log("Order {$orderId}, Message [$res_msg]", 1);
    }

}
