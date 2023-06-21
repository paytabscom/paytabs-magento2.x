<?php

namespace ClickPay\PayPage\Plugin;

use Exception;
use ClickPay\PayPage\Gateway\Http\ClickPayCore;
use ClickPay\PayPage\Gateway\Http\ClickPayHelper;
use ClickPay\PayPage\Gateway\Http\ClickPayHelpers;
use ClickPay\PayPage\Model\Adminhtml\Source\EmailConfig;


class OrderServicePlugin
{

    use ClickPayHelpers;

    /**
     * @param \Magento\Sales\Api\OrderManagementInterface $orderManagementInterface
     * @param \Magento\Sales\Model\Order\Interceptor $order
     * @return $order
     */
    public function afterPlace(\Magento\Sales\Api\OrderManagementInterface $orderManagementInterface, $order)
    {
        if ($this->is_admin_created($order)) {
            try {
                $payment = $order->getPayment();
                $paymentMethod = $payment->getMethodInstance();

                if (ClickPayHelper::isClickPayPayment($paymentMethod->getCode())) {
                    $isGenerateEnabled = (bool) $paymentMethod->getConfigData('payment_link/pl_enabled');
                    $isVisibleToCustomer = (bool) $paymentMethod->getConfigData('payment_link/pl_customer_view');

                    if ($isGenerateEnabled) {
                        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
                        $baseurl = $storeManager->getStore()->getBaseUrl();

                        $pay_url = "{$baseurl}clickpay/paypage/pay?order={$order->getId()}";
                        $comment = "The payment link: <strong>{$pay_url}</strong>";

                        $order
                        ->addCommentToStatusHistory($comment, false, $isVisibleToCustomer)
                        ->save();

                        $payment
                            ->setAdditionalInformation(
                                'pt_paylink_enabled',
                                true
                            )->save();
                        }
                }
            } catch (Exception $ex) {
                ClickPayHelper::log('Clickpay: Handle Admin create order failed, ' . $ex->getMessage(), 3);
            }
        }

        return $order;
    }

    public function aroundPlace(\Magento\Sales\Model\Service\OrderService $subject, \Closure $proceed,  \Magento\Sales\Api\Data\OrderInterface $order)
    {
        $this->pt_handleEmailConfig($order);

        $return = $proceed($order);

        // $orderId = $order->getEntityId();

        // your custom code

        return $return;
    }


    //

    /**
     * Handle "Order Confirmation email" after new Order placement
     * @param \Magento\Sales\Model\Order\Interceptor $order
     * @return void
     */
    private function pt_handleEmailConfig($order)
    {
        try {
            $payment = $order->getPayment();
            $paymentMethod = $payment->getMethodInstance();
            $paymentCode = $paymentMethod->getCode();

            new ClickPayCore();
            $isClickPay = ClickPayHelper::isClickPayPayment($paymentCode);
            if (!$isClickPay) return;

            $email_config = $paymentMethod->getConfigData('email_config');

            $can_send = EmailConfig::canSendEMail(EmailConfig::EMAIL_PLACE_AFTER_PLACE_ORDER, $email_config);

            if (!$can_send) {
                $order->setCanSendNewEmailFlag(false);
            }
        } catch (\Throwable $th) {
            ClickPayHelper::log('ClickPay: Handle email configuration failed', 3);
        }
    }
}
