<?php

// declare(strict_types=1);

namespace ClickPay\PayPage\Controller\PayPage;

use DateTime;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Page;
use ClickPay\PayPage\Gateway\Http\ClickPayCore;
use ClickPay\PayPage\Gateway\Http\ClickPayHelper;
use ClickPay\PayPage\Gateway\Http\ClickPayHelpers;

class Pay extends Action
{
    use ClickPayHelpers;

    protected $_customerSession;

    private $clickpay;

   
    /**
     * @param Context $context
     */
    public function __construct(
        Context $context,
       
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Customer\Model\Session $customerSession
    ) {
        parent::__construct($context);

        $this->orderRepository = $orderRepository;
        $this->_customerSession = $customerSession;

        $this->clickpay = new \ClickPay\PayPage\Gateway\Http\Client\Api;
        new ClickPayCore();
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
            ClickPayHelper::log("Clickpay: Order ID is missing!", 3);
            return $resultRedirect;
        }

        $order = $this->getOrder($orderId);

        $passed = $this->_validate($order, $orderId);
        if (!$passed) {
            return $resultRedirect;
        }

        //

        $paypage = $this->prepare($order);

        if (!$paypage) {
            ClickPayHelper::log("Clickpay - Payment link: generating the payment page failed!, Order [{$orderId}]" . json_encode($paypage), 3);
            $this->messageManager->addWarningMessage('Something went wrong, kindly try again');
            return $resultRedirect;
        }

        if ($paypage->success) {
            // Create paypage success
            ClickPayHelper::log("Clickpay: create paypage success!, Order [{$order->getIncrementId()}]", 1);

            // Remove sensetive information
            /*$res = new stdClass();
            $res->success = true;
            $res->payment_url = $paypage->payment_url;
            $res->tran_ref = $paypage->tran_ref;

            $paypage = $res;*/

            $resultRedirect->setPath($paypage->payment_url);
        } else {
            ClickPayHelper::log("Clickpay: create paypage failed!, Order [{$order->getIncrementId()}] - " . json_encode($paypage), 3);
        }

        return $resultRedirect;
    }


    private function _validate($order, $orderId)
    {
        if (!$order) {
            ClickPayHelper::log("Clickpay: Order is missing!, ID [{$orderId}]", 3);
            return false;
        }

        $isLoggedIn = $this->_customerSession->isLoggedIn();
        if (!$isLoggedIn) {
            ClickPayHelper::log("Clickpay - Payment link: Customer is not logged in, Order [{$orderId}]", 2);
            $this->messageManager->addWarningMessage('Please Login and try again');
            return false;
        }

        if ($order->getCustomerID() != $this->_customerSession->getCustomerId()) {
            ClickPayHelper::log("Clickpay - Payment link: Order is not for the Customer!, Order [{$orderId}], Customer [{$order->getCustomerID()}]", 3);
            return false;
        }

        $payment = $order->getPayment();
        $paymentMethod = $payment->getMethodInstance();
        if (!ClickPayHelper::isClickPayPayment($paymentMethod->getCode())) {
            ClickPayHelper::log("Clickpay - Payment link: Order not linked to Clickpay!, Order [{$orderId}]", 3);
            return false;
        }

        $isGenerateEnabled = (bool) $paymentMethod->getConfigData('payment_link/pl_enabled');
        if (!$isGenerateEnabled) {
            ClickPayHelper::log("Clickpay - Payment link: Disabled!, Order [{$orderId}]", 2);
            $this->messageManager->addWarningMessage('Please Contact the Site administrator, Payment links are disabled');
            return false;
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
            ClickPayHelper::log("Clickpay - Payment link: Order state not in the list!, Order [{$orderId}] [{$order->getState()}]", 2);
            $this->messageManager->addWarningMessage('Please check your Order status again');
            return false;
        }

        $allowedInterval = max((int) $paymentMethod->getConfigData('payment_link/pl_allow_interval'), 0);
        if ($allowedInterval > 0) {
            $diff = (new DateTime())->diff(new DateTime($order->getCreatedAt()));
            $intervalInHours = ($diff->days * 24) + $diff->h;
            $allowedInHours = $allowedInterval * 24;

            if ($intervalInHours > $allowedInHours) {
                ClickPayHelper::log("Clickpay - Payment link: Order created since {$intervalInHours} hours!, Order [{$orderId}], [Allowed {$allowedInterval} days]", 2);
                $this->messageManager->addWarningMessage('The Order exceeds the time allowed');
                return false;
            }
        }

        $isFlaggedOrderOnly = (bool) $paymentMethod->getConfigData('payment_link/pl_flagged_order_only');
        if ($isFlaggedOrderOnly) {
            $orderIsFlagged = (bool) $order->getPayment()->getAdditionalInformation('pt_paylink_enabled');
            if (!$orderIsFlagged) {
                ClickPayHelper::log("Clickpay - Payment link: Order is not Flagged!, Order [{$orderId}]", 2);
                $this->messageManager->addWarningMessage('The Order does not accept Re-Pay');
                return false;
            }
        }

        return true;
    }


    private function prepare($order)
    {
        $payment = $order->getPayment();
        $paymentMethod = $payment->getMethodInstance();

        $ptApi = $this->clickpay->pt($paymentMethod);

        $values = $this->clickpay->prepare_order($order, $paymentMethod, false, false, true);

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