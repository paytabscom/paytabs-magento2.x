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
        $tokenDetails = $this->getTokenDetails();

        return substr($tokenDetails['payment_description'], -4);
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
        $card_type = $this->getTokenDetails()['card_scheme'];
        $m_card_type = $this->pt_convertCardType($card_type);

        return $this->getIconForType($m_card_type)['url'];
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

    //

    private function pt_convertCardType($pt_card_type)
    {
        switch ($pt_card_type) {
            case 'Visa':
                return 'VI';

            case 'MasterCard':
                return 'MC';

            case 'AmericanExpress':
                return 'AE';

            case 'JCB':
                return 'JCB';

            case 'Discover':
                return 'DI';

            default:
                return 'OT';
        }
    }
}
