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

## Feature Coverage

- [x] Card Payments (Multiple iframes)
    - [X] Vault
- [x] Google Pay
- [x] MB PAY

### Functionality that is currently not supported:
* Adding a new Stored Card from My Account -> Stored Payment Methods

### Configurations that currently are not supported:
* Configuration -> Global Settings -> Default Active Method
* Card Payments -> Display Card Icons

### Payment methods the currently are not supported:
* Apple Pay Payments
* Klarna (NAS)
* Paypal Payments (NAS)
* MOTO Payments

### Supported alternative payment methods:
* MB PAY

### Google Pay Payments: New Configuration Options

* Button corner radius

This option sets the `border-radius` property of the button and is measured in pixels. There is no need to specify the
CSS `px` unit in this option input field.

### Alternative payments: New Configuration Options

* Enable MB WAY Phone Validation

This option sets strict phone validation for MB WAY phone numbers (must be 9 digits and start with 9).
