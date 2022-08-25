<?php

// declare(strict_types=1);

namespace PayTabs\PayPage\Controller\PayPage;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Sales\Model\Order;
use PayTabs\PayPage\Gateway\Http\PaytabsCore;
use PayTabs\PayPage\Gateway\Http\PaytabsEnum;
use PayTabs\PayPage\Gateway\Http\PaytabsHelper;
use PayTabs\PayPage\Gateway\Http\PaytabsHelpers;
use PayTabs\PayPage\Model\Adminhtml\Source\CurrencySelect;
use PayTabs\PayPage\Model\Adminhtml\Source\EmailConfig;

use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;


/**
 * Class Index
 */
class Callbackpre extends Action
{
    use PaytabsHelpers;

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
        $this->paytabs = new \PayTabs\PayPage\Gateway\Http\Client\Api;
        new PaytabsCore();
    }

    /**
     * @return ResponseInterface|ResultInterface|Page
     */
    public function execute()
    {
        if (!$this->getRequest()->isPost()) {
            PaytabsHelper::log("Paytabs: no post back data received in callback", 3);
            return;
        }

        // Get the params that were passed from our Router

        $data = PaytabsHelper::read_ipn_response();
        if (!$data) {
            return;
        }
        $_p_tran_ref = 'tran_ref';
        $_p_cart_id = 'cart_id';

        $transactionId = @$data->$_p_tran_ref;
        $pOrderId = @$data->$_p_cart_id;

        //

        if (!$pOrderId || !$transactionId) {
            PaytabsHelper::log("Paytabs: OrderId/TransactionId data not received in callback", 3);
            return;
        }

        //

        PaytabsHelper::log("Callback triggered, Quote [{$pOrderId}], Transaction [{$transactionId}]", 1);

        //

        // $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        // $order = $objectManager->create('Magento\Sales\Model\Order')->loadByIncrementId($pOrderId);

        $order = $this->quoteRepository->get($pOrderId);

        /*if (!$this->isValidOrder($order)) {
            PaytabsHelper::log("Paytabs: Order is missing, Order [{$pOrderId}]", 3);
            return;
        }*/

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

        $payment
            ->setTransactionId($transaction_ref)
            ->setAdditionalInformation(
                'pt_registered_transaction',
                $transaction_ref
            )
            ->save();

        PaytabsHelper::log("Quote {$orderId}, Message [$res_msg]", 1);
    }
}
