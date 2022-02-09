<?php

// declare(strict_types=1);

namespace PayTabs\PayPage\Controller\Paypage;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use PayTabs\PayPage\Gateway\Http\Client\Api;
use PayTabs\PayPage\Gateway\Http\PaytabsCore;

use function PayTabs\PayPage\Gateway\Http\paytabs_error_log;

/**
 * Class Index
 */
class Response extends Action
{

    // protected $resultRedirect;
    private $paytabs;

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
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository

        // \Psr\Log\LoggerInterface $logger
    ) {
        parent::__construct($context);

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

        $_p_tran_ref = 'tranRef';
        $_p_cart_id = 'cartId';
        $transactionId = $this->getRequest()->getParam($_p_tran_ref, null);
        $pOrderId = $this->getRequest()->getParam($_p_cart_id, null);


        //

        if (!$pOrderId || !$transactionId) {
            paytabs_error_log("Paytabs: OrderId/TransactionId data did not receive in callback");
            return;
        }

        //

        paytabs_error_log("Return triggered, Order [{$pOrderId}], Transaction [{$transactionId}]", 1);

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
        $verify_response = $ptApi->read_response(false);
        if (!$verify_response) {
            return;
        }

        //

        $cart_refill = (bool) $paymentMethod->getConfigData('order_failed_reorder');
        return $this->pt_handle_return($order, $verify_response, $objectManager, $cart_refill);

        // return $this->pageFactory->create();
    }

    //

    private function pt_handle_return($order, $verify_response, $objectManager, $cart_refill)
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        $success = $verify_response->success;
        $res_msg = $verify_response->message;
        $orderId = @$verify_response->reference_no;
        // $transaction_ref = @$verify_response->transaction_id;

        if ($success) {
            if ($quote = $this->getQuoteFromOrder($order)) {
                $_checkoutSession = $objectManager->create('\Magento\Checkout\Model\Session');
                $_checkoutSession->setLastQuoteId($quote->getId())->setLastSuccessQuoteId($quote->getId());
                $_checkoutSession->setLastOrderId($order->getId())
                    ->setLastRealOrderId($order->getIncrementId())
                    ->setLastOrderStatus($order->getStatus());
            }
            $this->messageManager->addSuccessMessage('The payment has been completed successfully - ' . $res_msg);
            $redirect_page = 'checkout/onepage/success';
            /*
            if (Api::hadPaid($order)) {
                $this->messageManager->addWarningMessage('A previous paid amount detected for this Order, please contact us for more information');
            }
            */
        } else {

            $this->messageManager->addErrorMessage('The payment failed - ' . $res_msg);
            $redirect_page = 'checkout/onepage/failure';

            if ($cart_refill) {
                // Payment failed, Save the Quote (user's Cart)
                if ($quote = $this->getQuoteFromOrder($order)) {
                    $quote->setIsActive(true)->removePayment()->save();

                    $_checkoutSession = $objectManager->create('\Magento\Checkout\Model\Session');
                    $_checkoutSession->replaceQuote($quote);

                    $redirect_page = 'checkout/cart';
                }
            }
        }

        $resultRedirect->setPath($redirect_page);
        return $resultRedirect;
    }

    private function getQuoteFromOrder($order)
    {
        try {
            $quoteId = $order->getQuoteId();
            return $this->quoteRepository->get($quoteId);
        } catch (\Throwable $th) {
            paytabs_error_log("Paytabs: load Quote by ID failed!, Order [{$order->getId()}], QuoteId = [{$quoteId}]");
        }
        return false;
    }
}
