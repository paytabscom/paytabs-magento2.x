<?php

namespace PayTabs\PayPage\Block;

use Magento\Vault\Block\AbstractCardRenderer;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use PayTabs\PayPage\Model\Ui\ConfigProvider;


class CardRenderer extends AbstractCardRenderer
{
    /**
     * Can render specified token
     *
     * @param PaymentTokenInterface $token
     * @return boolean
     */
    public function canRender(PaymentTokenInterface $token)
    {
        return $token->getPaymentMethodCode() === ConfigProvider::CODE_ALL;
    }

    /**
     * @return string
     */
    public function getNumberLast4Digits()
    {
        $jsonDetails = json_decode($this->getToken()->getTokenDetails() ?: '{}');

        return substr($jsonDetails->payment_description, -4);
    }

    /**
     * @return string
     */
    public function getExpDate()
    {
        return substr($this->getToken()->getExpiresAt(), 0, 10);
    }

    /**
     * @return string
     */
    public function getIconUrl()
    {
        return null; // $this->getIconForType($this->getTokenDetails()['type'])['url'];
    }

    /**
     * @return int
     */
    public function getIconHeight()
    {
        return null; // $this->getIconForType($this->getTokenDetails()['type'])['height'];
    }

    /**
     * @return int
     */
    public function getIconWidth()
    {
        return null; // $this->getIconForType($this->getTokenDetails()['type'])['width'];
    }
}
