# CheckoutCom_Magento2 Payment Compatability with Hyvä Checkout

## Installation

1. Install [CheckoutCom_Magento2](https://www.checkout.com/docs/payments/accept-payments/connect-to-an-ecommerce-platform/magento-2)
2. Install Compatability Module by running
```bash
composer require magebitcom/magento2-hyva-checkout-checkoutcom-payment
```
3. Enable Module
```bash
bin/magento module:enable Magebit_CheckoutComPayment && bin/magento setup:upgrade
```

## Features

### Functionality that is currently not supported:
* Adding a new Stored Card from My Account -> Stored Payment Methods

### Configurations that currently are not supported:
* Configuration -> Global Settings -> Default Active Method
* Card Payments -> Display Card Icons

### Payment methods the currently are not supported:
* Alternative Payments
* Apple Pay Payments
* Google Pay Payments
* Klarna (NAS)
* Paypal Payments (NAS)
* MOTO Payments