<?php

namespace PayTabs\PayPage\Model\Ui;

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
    const CODE_OMANNET    = 'omannet';
    const CODE_MADA       = 'mada';
    const CODE_SADAD      = 'sadad';
    const CODE_FAWRY      = 'fawry';
    const CODE_KNPAY        = 'knet';
    const CODE_KNPAY_DEBIT  = 'knetdebit';
    const CODE_KNPAY_CREDIT = 'knetcredit';
    const CODE_AMEX       = 'amex';
    const CODE_VALU       = 'valu';
    const CODE_MEEZA      = 'meeza';
    const CODE_MEEZAQR    = 'meezaqr';
    const CODE_UNIONPAY   = 'unionpay';
    const CODE_PAYPAL     = 'paypal';
    const CODE_NBE_INSTALLMENT = 'installment';
    const CODE_URPAY      = 'urpay';
    const CODE_FORSA      = 'forsa';
    const CODE_AMAN       = 'aman';
    const CODE_TOUCHPOINTS = 'touchpoints';
    const CODE_TABBY      = 'tabby';

    const CODE_VAULT_ALL = 'paytabs_all_vault';

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
                'icon' => 'paytabs.png'
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
            self::CODE_OMANNET => [
                'icon' => 'omannet.png'
            ],
            self::CODE_MADA => [
                'icon' => 'mada.svg'
            ],
            self::CODE_SADAD => [
                'icon' => 'sadad.png'
            ],
            self::CODE_FAWRY => [
                'icon' => 'fawry.png'
            ],
            self::CODE_KNPAY => [
                'icon' => 'knet.svg'
            ],
            self::CODE_KNPAY_DEBIT => [],
            self::CODE_KNPAY_CREDIT => [],
            self::CODE_AMEX => [
                'icon' => 'amex.png'
            ],
            self::CODE_VALU => [
                'icon' => 'valu.png'
            ],
            self::CODE_MEEZA => [
                'icon' => 'meeza.png'
            ],
            self::CODE_MEEZAQR => [
                'icon' => 'meezaqr.png'
            ],
            self::CODE_UNIONPAY => [
                'icon' => 'unionpay.png'
            ],
            self::CODE_PAYPAL => [
                'icon' => 'paypal.svg'
            ],
            self::CODE_NBE_INSTALLMENT => [
                'icon' => 'nbe-installment.png'
            ],
            self::CODE_URPAY => [
                'icon' => 'urpay.svg'
            ],
            self::CODE_FORSA => [
                'icon' => 'forsa.png'
            ],
            self::CODE_AMAN => [
                'icon' => 'aman.svg'
            ],
            self::CODE_TOUCHPOINTS => [
                'icon' => 'touchpoints_adcb.svg'
            ],
            self::CODE_TABBY => [
                'icon' => 'tabby.svg'
            ],

            self::CODE_VAULT_ALL => [
                'vault_code' => self::CODE_VAULT_ALL
            ],
        ];

        $keys_bool = ['iframe_mode', 'payment_preorder', 'exclude_shipping'];
        $keys = ['currency_select'];

        $_icons_path = $this->assetRepo->getUrl("PayTabs_PayPage::images/");

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

        $logo_animation = $this->assetRepo->getUrl('PayTabs_PayPage::images/logo-animation.gif');

        return [
            'payment' => $pt_payments,
            'pt_icons' => [
                'logo_animation' => $logo_animation
            ]
        ];
    }
}
