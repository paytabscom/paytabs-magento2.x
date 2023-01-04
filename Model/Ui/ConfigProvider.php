<?php

namespace ClickPay\PayPage\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;

/**
 * Class ConfigProvider
 */
final class ConfigProvider implements ConfigProviderInterface
{
    const CODE_ALL        = 'all';
    const CODE_CREDITCARD = 'creditcard';
    const CODE_STCPAY     = 'stcpay';
    const CODE_APPLEPAY   = 'applepay';
    const CODE_MADA       = 'mada';
    const CODE_SADAD      = 'sadad';
    const CODE_AMEX       = 'amex';

    const CODE_VAULT_ALL = 'ClickPay_all_vault';

    protected $paymentHelper;
    private $assetRepo;

    public function __construct(
        \Magento\Payment\Helper\Data $paymentHelper,
        \Magento\Framework\View\Asset\Repository $assetRepo
    ) {
        $this->paymentHelper = $paymentHelper;
        $this->assetRepo = $assetRepo;
    }


    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        $pt_payments = [
            self::CODE_ALL => [
                'vault_code' => self::CODE_VAULT_ALL,
                'icon' => 'clickpay.png'
            ],
            self::CODE_CREDITCARD => [
                'icon' => 'creditcard.svg'
            ],
            self::CODE_STCPAY => [
                'icon' => 'stcpay.png'
            ],
            self::CODE_APPLEPAY => [
                'icon' => 'applepay.svg'
            ],
            self::CODE_MADA => [
                'icon' => 'mada.svg'
            ],
            self::CODE_SADAD => [
                'icon' => 'sadad.png'
            ],
            self::CODE_AMEX => [
                'icon' => 'amex.png'
            ],

            self::CODE_VAULT_ALL => [
                'vault_code' => self::CODE_VAULT_ALL
            ],
        ];

        $keys_bool = ['iframe_mode', 'payment_preorder', 'exclude_shipping'];
        $keys = ['currency_select'];

        $_icons_path = $this->assetRepo->getUrl("ClickPay_PayPage::images/");

        foreach ($pt_payments as $code => &$values) {
            foreach ($keys_bool as $key) {
                $values[$key] = (bool) $this->paymentHelper->getMethodInstance($code)->getConfigData($key);
            }
            
            foreach ($keys as $key) {
                $values[$key] = $this->paymentHelper->getMethodInstance($code)->getConfigData($key);
            }
            if (isset($values['icon'])) {
                $values['icon'] = $_icons_path . '/' . $values['icon'];
            }
        }

        $logo_animation = $this->assetRepo->getUrl('ClickPay_PayPage::images/logo-animation.gif');

        return [
            'payment' => $pt_payments,
            'pt_icons' => [
                'logo_animation' => $logo_animation
            ],
        ];
    }
}
