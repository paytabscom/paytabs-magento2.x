<?php

// declare(strict_types=1);

namespace PayTabs\PayPage\Controller\Paypage;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

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
    private $paytabs;

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
    ) {
        parent::__construct($context);
        $this->_orderFactory = $orderFactory;
        $this->checkoutSession = $checkoutSession;
        $this->pageFactory = $pageFactory;
        $this->jsonResultFactory = $jsonResultFactory;
        $this->orderRepository = $orderRepository;
        $this->quoteRepository = $quoteRepository;
        $this->paytabs = new \PayTabs\PayPage\Gateway\Http\Client\Api;
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
            $result->setData([
                'result' => 'Quote ID is missing!'
            ]);
            return $result;
        }

        // Create PayPage
        $order = $this->getOrder();
        if (!$order) {
            $result->setData([
                'result' => 'Order is missing!'
            ]);
            return $result;
        }

        $paypage = $this->prepare($order);
        if ($paypage && $paypage->response_code == 4012) {
            // Create paypage success
        } else {
            // Create paypage failed, Save the Qoute (user's Cart)
            $quote = $this->quoteRepository->get($quoteId);
            $quote->setIsActive(true)->save();
            $order->cancel()->save();
        }

        $result->setData($paypage);

        return $result;
    }

    function prepare($order)
    {
        $payment = $order->getPayment();
        $paymentMethod = $payment->getMethodInstance();

        $ptApi = $this->paytabs->pt($paymentMethod);

        $values = $this->paytabs->prepare_order($order, $paymentMethod->getCode());

        $res = $ptApi->create_pay_page($values);
        return $res;
    }

    public function getOrder()
    {
        if ($this->checkoutSession->getLastRealOrderId()) {
            $order = $this->_orderFactory->create()->loadByIncrementId($this->checkoutSession->getLastRealOrderId());
            return $order;
        }
        return false;
    }
}
