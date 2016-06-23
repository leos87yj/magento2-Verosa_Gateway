magento2-Verosa Pay
======================

Verosa Payment gateway Magento2 extension



Install
=======

1. Go to Magento2 root folder


2. Enter following commands to enable module:

    ```bash
    php bin/magento module:enable Verosa_Pay --clear-static-content
    php bin/magento setup:upgrade
    php bin/magento setup:di:compile
    ```
3. Enable and configure Verosa Payment in Magento Admin under Stores/Configuration/Payment Methods/Verosa

