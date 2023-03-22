<?php

// declare(strict_types=1);

namespace PayTabs\PayPage\Controller\PayPage;

use DateTime;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use PayTabs\PayPage\Gateway\Http\Client\Api;
use PayTabs\PayPage\Gateway\Http\PaytabsCore;
use PayTabs\PayPage\Gateway\Http\PaytabsHelper;
use Magento\Vault\Model\Ui\VaultConfigProvider;
use PayTabs\PayPage\Gateway\Http\PaytabsHelpers;
use stdClass;

/**
 * Class Index
 */
class Pay extends Action
{
    use PaytabsHelpers;

    /**
     * @var PageFactory
     */
    private $pageFactory;

    private $jsonResultFactory;
    protected $orderRepository;
    protected $_orderFactory;
    protected $quoteRepository;
    protected $checkoutSession;
    protected $_customerSession;

    private $paytabs;

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
        \Magento\Framework\Controller\Result\JsonFactory $jsonResultFactory,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession
        // \Psr\Log\LoggerInterface $logger
    ) {
        parent::__construct($context);

        $this->_orderFactory = $orderFactory;
        $this->pageFactory = $pageFactory;
        $this->jsonResultFactory = $jsonResultFactory;
        $this->orderRepository = $orderRepository;
        $this->quoteRepository = $quoteRepository;

        $this->checkoutSession = $checkoutSession;
        $this->_customerSession = $customerSession;

        // $this->_logger = $logger;
        $this->paytabs = new \PayTabs\PayPage\Gateway\Http\Client\Api;
        new PaytabsCore();
    }

    /**
     * @return ResponseInterface|ResultInterface|Page
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('');

        // Get the params that were passed from our Router
        $orderId = $this->getRequest()->getParam('order', null);
        if (!$orderId) {
            PaytabsHelper::log("Paytabs: Order ID is missing!", 3);
            return $resultRedirect;
        }

        $order = $this->getOrder($orderId);
        if (!$order) {
            PaytabsHelper::log("Paytabs: Order is missing!, ID [{$orderId}]", 3);
            return $resultRedirect;
        }

        $isLoggedIn = $this->_customerSession->isLoggedIn();
        if (!$isLoggedIn) {
            PaytabsHelper::log("Paytabs - Payment link: Customer is not logged in, Order [{$orderId}]", 2);
            $this->messageManager->addWarningMessage('Please Login and try again');
            return $resultRedirect;
        }

        if ($order->getCustomerID() != $this->_customerSession->getCustomerId()) {
            PaytabsHelper::log("Paytabs - Payment link: Order is not for the Customer!, Order [{$orderId}], Customer [{$order->getCustomerID()}]", 3);
            return $resultRedirect;
        }

        $payment = $order->getPayment();
        $paymentMethod = $payment->getMethodInstance();
        if (!PaytabsHelper::isPayTabsPayment($paymentMethod->getCode())) {
            PaytabsHelper::log("Paytabs - Payment link: Order not linked to PayTabs!, Order [{$orderId}]", 3);
            return $resultRedirect;
        }

        $isGenerateEnabled = (bool) $paymentMethod->getConfigData('payment_link/pl_enabled');
        if (!$isGenerateEnabled) {
            PaytabsHelper::log("Paytabs - Payment link: Disabled!, Order [{$orderId}]", 2);
            $this->messageManager->addWarningMessage('Please Contact the Site administrator, Payment links are disabled');
            return $resultRedirect;
        }

        $allowed_states = [
            \Magento\Sales\Model\Order::STATE_NEW,
            \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT
        ];

        $isCancelAllowed = (bool) $paymentMethod->getConfigData('payment_link/pl_allow_on_cancelled');
        if ($isCancelAllowed) {
            $allowed_states[] = \Magento\Sales\Model\Order::STATE_CANCELED;
        }

        if (!in_array($order->getState(), $allowed_states)) {
            PaytabsHelper::log("Paytabs - Payment link: Order state not in the list!, Order [{$orderId}] [{$order->getState()}]", 2);
            $this->messageManager->addWarningMessage('Please check your Order status again');
            return $resultRedirect;
        }

        if ($order->getState() == \Magento\Sales\Model\Order::STATE_CANCELED) {
            $cancelledDays = (int) $paymentMethod->getConfigData('payment_link/pl_allow_on_cancelled_since');

            $createdAt = new DateTime($order->getCreatedAt());
            $interval = (new DateTime())->diff($createdAt)->days;

            if ($cancelledDays != 0 && $interval > $cancelledDays) {
                PaytabsHelper::log("Paytabs - Payment link: Order created since {$interval} days!, Order [{$orderId}], [{$cancelledDays}]", 2);
                $this->messageManager->addWarningMessage('The Order exceeds the time allowed');
                return $resultRedirect;
            }
        }


        $paypage = $this->prepare($order);

        if (!$paypage) {
            PaytabsHelper::log("Paytabs - Payment link: generating the payment page failed!, Order [{$orderId}]" . json_encode($paypage), 3);
            $this->messageManager->addWarningMessage('Something went wrong, kindly try again');
            return $resultRedirect;
        }

        if ($paypage->success) {
            // Create paypage success
            PaytabsHelper::log("Paytabs: create paypage success!, Order [{$order->getIncrementId()}]", 1);

            // Remove sensetive information
            /*$res = new stdClass();
            $res->success = true;
            $res->payment_url = $paypage->payment_url;
            $res->tran_ref = $paypage->tran_ref;

            $paypage = $res;*/

            $resultRedirect->setPath($paypage->payment_url);
        } else {
            PaytabsHelper::log("Paytabs: create paypage failed!, Order [{$order->getIncrementId()}] - " . json_encode($paypage), 3);
        }

        return $resultRedirect;
    }


    function prepare($order)
    {
        $payment = $order->getPayment();
        $paymentMethod = $payment->getMethodInstance();

        $ptApi = $this->paytabs->pt($paymentMethod);

        $values = $this->paytabs->prepare_order($order, $paymentMethod, false, false, true);

        $res = $ptApi->create_pay_page($values);

        return $res;
    }


    public function getOrder($orderId)
    {
        if ($orderId) {
            try {
                $order = $this->orderRepository->get($orderId);
                if ($this->isValidOrder($order)) {
                    return $order;
                }
            } catch (\Throwable $th) {
            }
        }
        return false;
    }
}
