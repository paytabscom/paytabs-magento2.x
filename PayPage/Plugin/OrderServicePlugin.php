<?php

namespace PayTabs\PayPage\Plugin;

class  OrderServicePlugin
{
    /**
     * @param \Magento\Sales\Api\OrderManagementInterface $orderManagementInterface
     * @param \Magento\Sales\Model\Order\Interceptor $order
     * @return $order
     */
    public function afterPlace(\Magento\Sales\Api\OrderManagementInterface $orderManagementInterface, $order)
    {
        $orderId = $order->getId();

        // do something with order object (Interceptor )

        return $order;
    }

    public function aroundPlace(\Magento\Sales\Model\Service\OrderService $subject, \Closure $proceed,  \Magento\Sales\Api\Data\OrderInterface $order)
    {
        $return = $proceed($order);

        $orderId = $order->getEntityId();

        // your custom code

        return $return;
    }
}
