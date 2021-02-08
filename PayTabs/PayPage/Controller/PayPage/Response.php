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
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository

        // \Psr\Log\LoggerInterface $logger
    ) {
        parent::__construct($context);

        $this->pageFactory = $pageFactory;
        $this->_invoiceSender = $invoiceSender;
        $this->quoteRepository = $quoteRepository;

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
            paytabs_error_log("Paytabs: no post back data received in callback");
            return;
        }

        // Get the params that were passed from our Router
        // $pOrderId = $this->getRequest()->getParam('p', null);

        // PT
        // PayTabs "Invoice ID"
        $transactionId = $this->getRequest()->getParam('tranRef', null);
        $pOrderId = $this->getRequest()->getParam('cartId', null);

        $resultRedirect = $this->resultRedirectFactory->create();

        //

        if (!$pOrderId || !$transactionId) {
            paytabs_error_log("Paytabs: OrderId/TransactionId data did not receive in callback");
            return;
        }

        //

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $order = $objectManager->create('Magento\Sales\Model\Order')->loadByIncrementId($pOrderId);

        if (!$order) {
            paytabs_error_log("Paytabs: Order is missing, Order param = [{$pOrderId}]");
            return;
        }

        $payment = $order->getPayment();
        $paymentMethod = $payment->getMethodInstance();
        
        $cart_refill = $paymentMethod->getConfigData('order_failed_reorder') ?? false;

        $ptApi = $this->paytabs->pt($paymentMethod);

        $isValid = $ptApi->is_valid_redirect($_POST);
        if (!$isValid) {
            paytabs_error_log("Paytabs: Response is not valid, Order param = [{$pOrderId}]");
            return;
        }

        $verify_response = $ptApi->verify_response_local($_POST);

        $success = $verify_response->success;
        $res_msg = $verify_response->message;

        if (!$success) {
            paytabs_error_log("Paytabs Response: Payment verify failed [$res_msg] for Order {$pOrderId}");

            $this->messageManager->addErrorMessage('The payment failed - ' . $res_msg);
            $redirect_page = 'checkout/onepage/failure';
            if ($cart_refill) {
                try {
                    // Payment failed, Save the Quote (user's Cart)
                    $quoteId = $order->getQuoteId();
                    $quote = $this->quoteRepository->get($quoteId);
                    $quote->setIsActive(true)->removePayment()->save();
            
                    $redirect_page = 'checkout/cart';
                } catch (\Throwable $th) {
                    paytabs_error_log("Paytabs: load Quote by ID failed!, OrderId = [{$orderId}], QuoteId = [{$quoteId}] ");
                }
            }
            $resultRedirect->setPath($redirect_page);
            return $resultRedirect;
        }

        if (Api::hadPaid($order)) {
            $this->messageManager->addWarningMessage('A previous paid amount detected for this Order, please contact the Administration for more information');
        }

        $this->messageManager->addSuccessMessage('The payment has been completed successfully - ' . $res_msg);
        $resultRedirect->setPath('checkout/onepage/success');

        return $resultRedirect;
    }
}

/**
 * move CRSF verification to Plugin
 * compitable with old Magento version >=2.0 && <2.3
 * compitable with PHP version 5.6
 */
