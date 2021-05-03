<?php

// declare(strict_types=1);

namespace ClickPay\PayPage\Controller\Paypage;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use ClickPay\PayPage\Gateway\Http\Client\Api;
use ClickPay\PayPage\Gateway\Http\ClickpayCore;

use function ClickPay\PayPage\Gateway\Http\clickpay_error_log;

/**
 * Class Index
 */
class Create extends Action
{
    /**
     * @var PageFactory
     */
    private $pageFactory;
    private $jsonResultFactory;
    protected $orderRepository;
    protected $quoteRepository;
    private $clickpay;

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
        \Magento\Checkout\Model\Session $checkoutSession
        // \Psr\Log\LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->_orderFactory = $orderFactory;
        $this->checkoutSession = $checkoutSession;
        $this->pageFactory = $pageFactory;
        $this->jsonResultFactory = $jsonResultFactory;
        $this->orderRepository = $orderRepository;
        $this->quoteRepository = $quoteRepository;
        // $this->_logger = $logger;
        $this->clickpay = new \ClickPay\PayPage\Gateway\Http\Client\Api;
        new ClickpayCore();
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
            clickpay_error_log("Clickpay: Quote ID is missing!");
            $result->setData([
                'result' => 'Quote ID is missing!'
            ]);
            return $result;
        }

        // Create PayPage
        $order = $this->getOrder();
        if (!$order) {
            clickpay_error_log("Clickpay: Order is missing!, Quote = [{$quoteId}]");
            $result->setData([
                'result' => 'Order is missing!'
            ]);
            return $result;
        }

        $paypage = $this->prepare($order);
        if ($paypage->success) {
            // Create paypage success
        } else {
            clickpay_error_log("Clickpay: create paypage failed!, Order = [{$order->getIncrementId()}] - " . json_encode($paypage));

            try {
                // Create paypage failed, Save the Quote (user's Cart)
                $quote = $this->quoteRepository->get($quoteId);
                $quote->setIsActive(true)->removePayment()->save();
            } catch (\Throwable $th) {
                clickpay_error_log("Clickpay: load Quote by ID failed!, QuoteId = [{$quoteId}] ");
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

        $ptApi = $this->clickpay->pt($paymentMethod);

        $values = $this->clickpay->prepare_order($order, $paymentMethod);

        $res = $ptApi->create_pay_page($values);

        $framed_mode = $paymentMethod->getConfigData('iframe_mode');
        $res->framed_mode = $framed_mode;

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
