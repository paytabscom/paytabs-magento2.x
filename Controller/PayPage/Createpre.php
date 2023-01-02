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
use stdClass;

/**
 * Class Index
 */
class Createpre extends Action
{
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
     * @var \Magento\Quote\Model\QuoteIdMaskFactory
     */
    protected $quoteIdMaskFactory;

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
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Quote\Model\QuoteIdMaskFactory $quoteIdMaskFactory
        // \Psr\Log\LoggerInterface $logger
    ) {
        parent::__construct($context);

        $this->_orderFactory = $orderFactory;

        $this->pageFactory = $pageFactory;
        $this->jsonResultFactory = $jsonResultFactory;
        $this->orderRepository = $orderRepository;
        $this->quoteRepository = $quoteRepository;
        // $this->_logger = $logger;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;

        $this->checkoutSession = $checkoutSession;
        $this->_customerSession = $customerSession;

        $this->paytabs = new \PayTabs\PayPage\Gateway\Http\Client\Api;
        new PaytabsCore();
    }

    /**
     * @return ResponseInterface|ResultInterface|Page
     */
    public function execute()
    {
        $result = $this->jsonResultFactory->create();

        $quoteId = $this->getRequest()->getPostValue('quote', null);
        $isTokenise = (bool) $this->getRequest()->getPostValue('vault', null);
        $methodCode = $this->getRequest()->getPostValue('method', null);

        if (!$quoteId) {
            PaytabsHelper::log("Paytabs: Quote ID is missing!", 3);
            $result->setData([
                'result' => 'Quote ID is missing!'
            ]);
            return $result;
        }

        try {
            $isLoggedIn = $this->_customerSession->isLoggedIn();
            if (!$isLoggedIn) {
                $quoteIdMask = $this->quoteIdMaskFactory->create()->load($quoteId, 'masked_id');
                $quote = $this->quoteRepository->getActive($quoteIdMask->getQuoteId());
            } else {
                $quote = $this->quoteRepository->getActive($quoteId);
            }
        } catch (\Throwable $th) {
            $quote = null;
        }

        if (!$quote) {
            PaytabsHelper::log("Paytabs: Quote is missing!, Quote [{$quoteId}]", 3);
            $result->setData([
                'result' => 'Quote is missing!'
            ]);
            return $result;
        }

        $paypage = $this->prepare($quote, $isTokenise, $methodCode, $isLoggedIn);

        if ($paypage->success) {
            // Create paypage success
            $tran_ref = $paypage->tran_ref;
            PaytabsHelper::log("Create paypage success!, Quote [{$quoteId}] [{$tran_ref}]", 1);

            // Remove sensetive information
            $res = new stdClass();
            $res->success = true;
            $res->payment_url = $paypage->payment_url;
            $res->tran_ref = $tran_ref;

            $quote
                ->getPayment()
                ->setAdditionalInformation(
                    'pt_registered_transaction',
                    $tran_ref
                )
                ->save();
        } else {
            PaytabsHelper::log("Create paypage failed!, Order [{$quoteId}] - " . json_encode($paypage), 3);

            $res = $paypage;
        }

        /*if (Api::hadPaid($order)) {
            $paypage->had_paid = true;
            $paypage->order_id = $order->getId();
        }*/

        $result->setData($res);

        return $result;
    }


    function prepare($quote, $isTokenise, $methodCode, $isLoggedIn)
    {
        $paymentMethod = $this->_confirmPaymentMethod($quote, $methodCode);

        if (!$paymentMethod) {
            $res = new stdClass();
            $res->result = "Quote [" . $quote->getId() . "] payment method is missing!";
            $res->success = false;

            return $res;
        }

        $ptApi = $this->paytabs->pt($paymentMethod);

        // $isTokenise = $payment->getAdditionalInformation(VaultConfigProvider::IS_ACTIVE_CODE);
        // $a = $payment->getAdditionalInformation('pt_registered_transaction');
        $values = $this->paytabs->prepare_order($quote, $paymentMethod, $isTokenise, true, $isLoggedIn);

        $res = $ptApi->create_pay_page($values);

        return $res;
    }


    private function _confirmPaymentMethod($quote, $method_code)
    {
        $paymentMethod = null;

        try {
            $payment = $quote->getPayment();
            $paymentMethod = $payment->getMethodInstance();
        } catch (\Throwable $th) {
            PaytabsHelper::log("Quote [{$quote->getId()}] payment method is missing!, ({$th->getMessage()})", 2);
            try {
                if (PaytabsHelper::isPayTabsPayment($method_code)) {
                    $payment->setMethod($method_code);
                    $paymentMethod = $payment->getMethodInstance();
                }
            } catch (\Throwable $th) {
                PaytabsHelper::log("Quote [{$quote->getId()}] not able to set payment method!, ({$th->getMessage()})", 2);
            }
        }

        return $paymentMethod;
    }
}
