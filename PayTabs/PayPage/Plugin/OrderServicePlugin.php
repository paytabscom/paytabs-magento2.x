<?php

namespace PayTabs\PayPage\Plugin;

use PayTabs\PayPage\Gateway\Http\PaytabsCore;
use PayTabs\PayPage\Gateway\Http\PaytabsHelper;
use PayTabs\PayPage\Model\Adminhtml\Source\EmailConfig;

use function PayTabs\PayPage\Gateway\Http\paytabs_error_log;

class OrderServicePlugin
{
    /**
     * @param \Magento\Sales\Api\OrderManagementInterface $orderManagementInterface
     * @param \Magento\Sales\Model\Order\Interceptor $order
     * @return $order
     */
    public function afterPlace(\Magento\Sales\Api\OrderManagementInterface $orderManagementInterface, $order)
    {
        // $orderId = $order->getId();

        // do something with order object (Interceptor )

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

            new PaytabsCore();
            $isPaytabs = PaytabsHelper::isPayTabsPayment($paymentCode);
            if (!$isPaytabs) return;

            $email_config = $paymentMethod->getConfigData('email_config');

            $can_send = EmailConfig::canSendEMail(EmailConfig::EMAIL_PLACE_AFTER_PLACE_ORDER, $email_config);

            if (!$can_send) {
                $order->setCanSendNewEmailFlag(false);
            }
        } catch (\Throwable $th) {
            paytabs_error_log('PayTabs: Handle email configuration failed');
        }
    }
}
