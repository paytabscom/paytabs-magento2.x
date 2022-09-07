# PayTabs - Magento

The Official Magento2 plugin for PayTabs

---

## Installation

### Install using FTP method

*Note: Delete any previous PayTabs plugin.*

1. Download the latest release of the plugin
2. Upload the content of the folder to magento2 installation directory: `app/code/PayTabs/PayPage`
3. Run the following Magento commands:
   1. `php bin/magento setup:upgrade`
   2. `php bin/magento setup:static-content:deploy`
   3. `php bin/magento cache:clean`

### Install using `Composer`

1. `composer require paytabs/magento2`
2. `php bin/magento setup:upgrade`
3. `php bin/magento setup:static-content:deploy`
4. `php bin/magento cache:clean`

---

## Activating the Plugin

By default and after installing the module, it will be activated.
To Disable/Enable the module:

### Enable

`php bin/magento module:enable PayTabs_PayPage`

### Disable

`php bin/magento module:disable PayTabs_PayPage`

---

## Configure the Plugin

1. Navigate to `"Magento admin panel" >> Stores >> Configuration`
2. Open `"Sales >> Payment Methods`
3. Select the preferred payment method from the available list of PayTabs payment methods
4. Enable the `Payment Gateway`
5. Enter the primary credentials:
   - **Profile ID**: Enter the Profile ID of your PayTabs account
   - **Server Key**: `Merchant’s Dashboard >> Developers >> Key management >> Server Key`
6. Click `Save Config`

---

## Log Access

### PayTabs custome log

1. Access `debug_paytabs.log` file found at: `/var/log/debug_paytabs.log`

---

Done
