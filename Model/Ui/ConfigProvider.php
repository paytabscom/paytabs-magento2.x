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
            ],
            self::CODE_CREDITCARD => [],
            self::CODE_STCPAY => [],
            self::CODE_APPLEPAY => [],
            self::CODE_OMANNET => [],
            self::CODE_MADA => [],
            self::CODE_SADAD => [],
            self::CODE_FAWRY => [],
            self::CODE_KNPAY => [],
            self::CODE_KNPAY_DEBIT => [],
            self::CODE_KNPAY_CREDIT => [],
            self::CODE_AMEX => [],
            self::CODE_VALU => [],
            self::CODE_MEEZA => [],
            self::CODE_MEEZAQR => [],
            self::CODE_UNIONPAY => [],

            self::CODE_VAULT_ALL => [
                'vault_code' => self::CODE_VAULT_ALL
            ],
        ];

        $keys = ['iframe_mode', 'payment_preorder'];

        foreach ($pt_payments as $code => &$values) {
            foreach ($keys as $key) {
                $values[$key] = (bool) $this->paymentHelper->getMethodInstance($code)->getConfigData($key);
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
