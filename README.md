# ClickPay - Magento

The Official Magento2 plugin for ClickPay

- - -

## Installation

### Install using FTP method

*Note: Delete any previous ClickPay plugin.*

1. Download the latest release of the plugin
2. Upload the content of the folder to magento2 installation directory: `app/code/ClickPay/PayPage`
3. Run the following Magento commands:
   1. `php bin/magento setup:upgrade`
   2. `php bin/magento setup:static-content:deploy`
   3. `php bin/magento cache:clean`

- - -
### Install using `Composer`

1. `composer require clickpay/magento2`
2. `php bin/magento setup:upgrade`
3. `php bin/magento setup:static-content:deploy`
4. `php bin/magento cache:clean`

---

## Activating the Plugin

By default and after installing the module, it will be activated.
To Disable/Enable the module:

### Enable

`php bin/magento module:enable ClickPay_PayPage`

### Disable

`php bin/magento module:disable ClickPay_PayPage`

- - -

## Configure the Plugin

1. Navigate to `"Magento admin panel" >> Stores >> Configuration`
2. Open `"Sales >> Payment Methods`
3. Select the preferred payment method from the available list of ClickPay payment methods
4. Enable the `Payment Gateway`
5. Enter the primary credentials:
   - **Profile ID**: Enter the Profile ID of your ClickPay account
   - **Server Key**: `Merchant’s Dashboard >> Developers >> Key management >> Server Key`
6. Click `Save Config`

- - -

## Log Access

### ClickPay custome log

1. Access `debug_Clickpay.log` file found at: `/var/log/debug_Clickpay.log`

- - -

Done
