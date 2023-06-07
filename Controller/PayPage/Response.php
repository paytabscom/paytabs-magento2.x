<?php

// declare(strict_types=1);

namespace PayTabs\PayPage\Controller\PayPage;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use PayTabs\PayPage\Gateway\Http\PaytabsCore;
use PayTabs\PayPage\Gateway\Http\PaytabsHelper;
use PayTabs\PayPage\Gateway\Http\PaytabsHelpers;

/**
 * Class Index
 */
class Response extends Action
{
    use PaytabsHelpers;

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
            PaytabsHelper::log("Paytabs: no post back data received in callback", 3);
            return;
        }

        // Get the params that were passed from our Router

        $_p_tran_ref = 'tranRef';
        $_p_cart_id = 'cartId';
        $transactionId = $this->getRequest()->getParam($_p_tran_ref, null);
        $pOrderId = $this->getRequest()->getParam($_p_cart_id, null);


        //

        if (!$pOrderId || !$transactionId) {
            PaytabsHelper::log("Paytabs: OrderId/TransactionId data did not receive in callback", 3);
            return;
        }

        //

        PaytabsHelper::log("Return triggered, Order [{$pOrderId}], Transaction [{$transactionId}]", 1);

        //

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $order = $objectManager->create('Magento\Sales\Model\Order')->loadByIncrementId($pOrderId);

        if (!$this->isValidOrder($order)) {
            PaytabsHelper::log("Paytabs: Order is missing, Order [{$pOrderId}]", 3);
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

        $cart_refill = (bool) $paymentMethod->getConfigData('order_statuses/order_failed_reorder');
        return $this->pt_handle_return($order, $verify_response, $objectManager, $cart_refill);

        // return $this->pageFactory->create();
    }

    //

    private function pt_handle_return($order, $verify_response, $objectManager, $cart_refill)
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        $success = $verify_response->success;
        $is_on_hold = $verify_response->is_on_hold;
        $res_msg = $verify_response->message;
        $orderId = @$verify_response->reference_no;
        // $transaction_ref = @$verify_response->transaction_id;

        if ($success) {
            $this->messageManager->addSuccessMessage('The payment has been completed successfully - ' . $res_msg);
            $redirect_page = 'checkout/onepage/success';
            /*
            if (Api::hadPaid($order)) {
                $this->messageManager->addWarningMessage('A previous paid amount detected for this Order, please contact us for more information');
            }
            */
        } else if ($is_on_hold) {
            $this->messageManager->addWarningMessage('The payment is pending - ' . $res_msg);
            $redirect_page = 'checkout/onepage/success';
        } else {

            $this->messageManager->addErrorMessage('The payment failed - ' . $res_msg);
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
                    PaytabsHelper::log("Paytabs: load Quote by ID failed!, Order [{$orderId}], QuoteId = [{$quoteId}]", 3);
                }
            }
        }

        $resultRedirect->setPath($redirect_page);
        return $resultRedirect;
    }
}
