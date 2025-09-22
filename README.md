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
    - [x] Checkout Page
    - [ ] Cart
    - [ ] Minicart
- [x] Apple Pay
    - [x] Checkout Page
    - [ ] Cart
    - [ ] Minicart
- [x] Alternative payment methods
    - [X] MB PAY

### Functionality that is currently not supported:
* Adding a new Stored Card from My Account -> Stored Payment Methods

### Configurations that currently are not supported:
* Configuration -> Global Settings -> Default Active Method
* Card Payments -> Display Card Icons

### Payment methods the currently are not supported:
* Klarna (NAS)
* Paypal Payments (NAS)
* MOTO Payments

### Google Pay Payments: New Configuration Options

* Button corner radius

This option sets the `border-radius` property of the button and is measured in pixels. There is no need to specify the
CSS `px` unit in this option input field.

### Apple Pay Payments: New Configuration Options

* Button corner radius

This option sets the `border-radius` property of the button and is measured in pixels. There is no need to specify the
CSS `px` unit in this option input field.

* Button height

This option sets the `height` property of the button and is measured in pixels. There is no need to specify the
CSS `px` unit in this option input field.


### Alternative payments: New Configuration Options

* Enable MB WAY Phone Validation

This option sets strict phone validation for MB WAY phone numbers (must be 9 digits and start with 9).

### Webhook Configuration for MB WAY Payments

MB WAY payments require webhook configuration to properly handle payment status updates.

The extension use default endpoint URL: https://your-domain.com/checkout_com/webhook/callback which is the same for 
CheckoutCom_Magento2 module.

* MB WAY Status Page Text

Text on waiting page while waiting on webhook events is customizable.

* MB WAY Status Polling Interval

Option to adjust interval at which customer waiting page will look for webhook updates. Recommended value is 3-5 seconds.

* Additional success states

By default, the waiting page will result in success page only if webhook with response code '10000' is received. This
config makes it possible to also select "Payment Pending" and "Payment Capture Pending" as a signal to redirect customer to success page
although after this redirect the order still might get automatically cancelled for example because of "Transaction has expired" error.
