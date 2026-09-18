# Nexi Checkout Payment Module for Prestashop

Enhance your Prestashop store with the Nexi Checkout, a comprehensive solution for seamless payment integration.

---

## Requirements

- **Prestashop Version**: Compatible with Prestashop **9.0 and above**.

> **Note:** For Prestashop versions **8.2.x**  (e.g., 8.2.7), please use the [Nexi Checkout Payment Module version 8.2.x](https://github.com/Nets-eCom/prestashop-easy-checkout/tree/8.2.x).

> **Note:** For Prestashop versions **1.7.x** and **8.0.x**  (e.g., 1.7.8.11, 8.1.7), please use the [Nets Easy Payment Module for Prestashop 1.7](https://github.com/Nets-eCom/Prestashop1.7_NetsEasy).

---

## Key Features

### Shop Features

- Seamless checkout experience with multiple payment options
- Hosted and Embedded checkout flows
- Payment method splitting (show one combined Nexi option or separate payment options)

### Administration Features

- Quick setup and flexible configuration
- Intuitive order management with synchronized payment status via webhooks
- Refund and capture capabilities with item-level control
- Multi-store support
- Automatic webhook synchronisation of payment events
- Full capture of up to 20 reserved Nexi payments in one bulk action

## Customer Service

Nexi provides support for both test and live accounts. For assistance, visit our [Support Page](https://developer.nexigroup.com/nexi-checkout/en-EU/support/).

---

## For Developers

### Documentation
For complete documentation, visit our [GitHub docs](https://github.com/Nets-eCom/prestashop-easy-checkout/tree/main/docs).

### Setup
In order to setup plugin from source code you need to:
- Run `composer install`
- In `/frontend` dir run `npm ci` & `npm run build` to build frontend and admin assets

### API

- Access the [Nexi API Reference](https://developer.nexigroup.com/nexi-checkout/en-EU/api/).
