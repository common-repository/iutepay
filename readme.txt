=== Iute ===
Contributors: iutecredit
Tags: checkout, iutecredit, loan application, payment
Requires at least: 5.9.2
Tested up to: 6.6.2
Requires PHP: 7.4
Stable tag: 1.0.48
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

"Buy now, pay later" experience, provided by Iute Group AS, for the customers of your WooCommerce based e-shop.

== Description ==
Iute checkout is a WooCommerce plugin from Iute Group AS, which allows you easily integrate Iute into your WooCommerce based e-shop.

Plugin includes:
* Widget to show to customer monthly repayment amount for each e-shop product in both category and product pages. Also widget provides loan calculator functionality.
* Iute Checkout Payment Gateway for WooCommerce (allows to submit a loan application to Iute Group AS on checkout step)
* Ability to manage mappings between Iute Group AS loan products and your e-shop products right in our Wordpress admin console.

== Installation ==
1. Install and activate plugin through the ‘Plugins’ menu in WordPress admin console.
2. Insert API keys on Iute management page in WooCommerce Settings (Payments) section. API keys should be provided by Iute Group AS.
3. Choose your country
4. Start to offer "Buy now, pay later" experience to your customers. 

== Screenshots ==
1. Iute widget on product page
2. Iute calculator modal view
3. Iute as an option on checkout page
4. Iute checkout modal view
5. Iute settings page in WooCommerce Payments

== Changelog ==
= 1.0.48 =
* Support for Woocommerce order emails.
= 1.0.47 =
* Fixed signature when shopping cart has tricky decimal prices.
= 1.0.46 =
* Support of plugin version and platform name in the checkout API.
= 1.0.45 =
* Better Elementor Pro support for promo messages and category pages.
= 1.0.44 =
* Customization of start checkout with iute on single product page.
* Better support for stage and dev environments.
= 1.0.43 =
* Ability to split Thank you modal screen and show separately from Checkout flow but together with Order Received page.
= 1.0.42 =
* Minor bugfixes related empty description, and legacy code.
= 1.0.41 =
* Improved support for products with variants and promo messages.
= 1.0.40 =
* Multi currency support based on https://wordpress.org/plugins/currency-exchange-for-woocommerce/
= 1.0.39 =
* Checkout page stabilisation when checkout has non free shipping or 3rd party shipping extensions.
= 1.0.38 =
* Support for official econt plugin on checkout page.
= 1.0.37 =
* Minor bugfixes.
= 1.0.36 =
* Added compatibility when php and nginx.
= 1.0.35 =
* Compatibility for new php versions.
= 1.0.34 =
* Wordpress compatible version was updated.
= 1.0.33 =
* Fast checkout feature added. Allows customer to just to checkout step right from product page. 
= 1.0.31 =
* start checkout support on promo modal
= 1.0.30 =
* antifraud for checkout page
= 1.0.29 =
* ability to customize promo message
= 1.0.28 =
* validation improvements
= 1.0.27 =
* support more countries on checkout page
= 1.0.26 =
* checkout form validation improvements
= 1.0.25 =
* better shipping address support
= 1.0.24 =
* webhook validation improvements
= 1.0.23 =
* minor fixes
= 1.0.21 =
* Patronymic field for checkout page
= 1.0.20 =
* Better support for webhook and order statuses
= 1.0.19 =
* Webhooks support
= 1.0.19 =
* Ability to hide promo message on category pages
= 1.0.18 =
* Checkout session improvements
= 1.0.17 =
* Better support for multi lang
= 1.0.16 =
* IutePay icon is now shown using add_filter function
= 1.0.15 =
* IutePay icon, fix mime type
= 1.0.14 =
* IutePay icon URL was fixed
= 1.0.13 =
* added icon for IutePay payment method on checkout page
= 1.0.12 =
* promo message renders inside span class=price for category pages, a lot of shops have problems with layout before
= 1.0.11 =
* removed Jetpack Constants as dependency
= 1.0.10 =
* product SKU is not a mandatory field anymore. For product mappings product ID is used instead, if SKU is not defined
* support of Wordpress 6.0.1 is added
