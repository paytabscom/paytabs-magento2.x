<?php

// declare(strict_types=1);

namespace PayTabs\PayPage\Controller\PayPage;

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
class Create extends Action
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
        $result = $this->jsonResultFactory->create();

        // Get the params that were passed from our Router
        $quoteId = $this->getRequest()->getParam('quote', null);
        if (!$quoteId) {
            PaytabsHelper::log("Paytabs: Quote ID is missing!", 3);
            $result->setData([
                'result' => 'Quote ID is missing!'
            ]);
            return $result;
        }

        // Create PayPage
        $order = $this->getOrder();
        if (!$order) {
            PaytabsHelper::log("Paytabs: Order is missing!, Quote [{$quoteId}]", 3);
            $result->setData([
                'result' => 'Order is missing!'
            ]);
            return $result;
        }

        $paypage = $this->prepare($order);

        if ($paypage->success) {
            // Create paypage success
            PaytabsHelper::log("Paytabs: create paypage success!, Order [{$order->getIncrementId()}]", 1);

            // Remove sensetive information
            $res = new stdClass();
            $res->success = true;
            $res->payment_url = $paypage->payment_url;
            $res->tran_ref = $paypage->tran_ref;

            $paypage = $res;

            $payment = $order->getPayment();
            $paymentMethod = $payment->getMethodInstance();
            $order_status = $paymentMethod->getConfigData('order_statuses/order_status');
            if ($order->getStatus() != $order_status) {
                $this->setNewStatus($order, $order_status, true);
                $order->save();
            }
        } else {
            PaytabsHelper::log("Paytabs: create paypage failed!, Order [{$order->getIncrementId()}] - " . json_encode($paypage), 3);

            try {
                // Create paypage failed, Save the Quote (user's Cart)
                $quote = $this->quoteRepository->get($quoteId);
                $quote->setIsActive(true)->removePayment()->save();
            } catch (\Throwable $th) {
                PaytabsHelper::log("Paytabs: load Quote by ID failed!, QuoteId [{$quoteId}]", 3);
            }
            $order->cancel()->save();
        }

        if (Api::hadPaid($order)) {
            $paypage->had_paid = true;
            $paypage->order_id = $order->getId();
        }

        $result->setData($paypage);

        return $result;
    }


    function prepare($order)
    {
        $payment = $order->getPayment();
        $paymentMethod = $payment->getMethodInstance();

        $ptApi = $this->paytabs->pt($paymentMethod);

        $isTokenise = $payment->getAdditionalInformation(VaultConfigProvider::IS_ACTIVE_CODE);
        $isLoggedIn = $this->_customerSession->isLoggedIn();
        $values = $this->paytabs->prepare_order($order, $paymentMethod, $isTokenise, false, $isLoggedIn);

        $res = $ptApi->create_pay_page($values);

        return $res;
    }


    public function getOrder()
    {
        $lastRealOrderId = $this->checkoutSession->getLastRealOrderId();
        if ($lastRealOrderId) {
            $order = $this->_orderFactory->create()->loadByIncrementId($lastRealOrderId);
            return $order;
        }
        return false;
    }
}
