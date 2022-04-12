<?php

// declare(strict_types=1);

namespace ClickPay\PayPage\Controller\PayPage;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Encryption\EncryptorInterface;
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
        EncryptorInterface $encryptor

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
        $res_msg = $verify_response->message;
        $orderId = @$verify_response->reference_no;
        $transaction_ref = @$verify_response->transaction_id;
        $transaction_type = @$verify_response->tran_type;

        //

        $_fail = !($success || $is_on_hold);

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

        // Success or OnHold

        $tranAmount = $verify_response->cart_amount;
        $tranCurrency = $verify_response->cart_currency;

        $payment
            ->setTransactionId($transaction_ref)
            ->setAdditionalInformation("payment_amount", $tranAmount)
            ->setAdditionalInformation("payment_currency", $tranCurrency)
            ->save();

        $payment->setAmountAuthorized($payment->getAmountOrdered());

        $paymentAmount = $this->getAmount($payment, $tranCurrency, $tranAmount, $use_order_currency);

        if (ClickPayEnum::TranIsSale($transaction_type)) {
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

            if (isset($verify_response->token, $verify_response->payment_info)) {
                $token_details = $verify_response->payment_info;
                $token_details->tran_ref = $transaction_ref;

                $paymentToken = $this->pt_find_token($verify_response->token, $order->getCustomerId(), $paymentMethod->getCode(), $token_details);
                if ($paymentToken) {
                    $extensionAttributes = $payment->getExtensionAttributes();
                    $extensionAttributes->setVaultPaymentToken($paymentToken);
                }
            }
        } elseif ($is_on_hold) {
            $order->hold();

            ClickPayHelper::log("Order {$orderId}, On-Hold", 1);
            $order->addCommentToStatusHistory("Transaction {$transaction_ref} is On-Hold, Go to ClickPay dashboard to Approve/Decline it");
        }

        //

        $order->save();

        ClickPayHelper::log("Order {$orderId}, Message [$res_msg]", 1);
    }


    public function pt_find_token($token, $customer_id, $payment_code, $token_details)
    {
        try {
            $isCard = ($payment_code == 'all') || ClickPayHelper::isCardPayment($payment_code);
            $tokenType = $isCard ? PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD : PaymentTokenFactoryInterface::TOKEN_TYPE_ACCOUNT;

            $str_token_details = json_encode($token_details);

            $publicHash = "$customer_id $str_token_details";
            $publicHashEncrypted = $this->encryptor->getHash($publicHash);

            $paymentToken = $this->_paymentTokenFactory->create($tokenType);
            $paymentToken
                ->setGatewayToken($token)
                ->setCustomerId($customer_id)
                ->setPaymentMethodCode($payment_code)
                ->setPublicHash($publicHashEncrypted)
                ->setExpiresAt("{$token_details->expiryYear}-{$token_details->expiryMonth}-01 00:00:00")
                ->setTokenDetails($str_token_details)
                ->save();

            return $paymentToken;
        } catch (\Throwable $th) {
            ClickPayHelper::log('Save payment token: ' . $th->getMessage(), 3);
            return null;
        }
    }
}
