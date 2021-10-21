=== Easy Digital Downloads - PayFast Integration ===
Contributors: andrewza, jarrydlong
Tags: payfast, edd, payment
Donate link: https://arctek.co.za/downloads/easy-digital-downloads-payfast/
Requires at least: 5.0
Tested up to: 5.8.1
Requires PHP: 7.2
Stable tag: trunk
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept once off and recurring payments through Easy Digital Downloads using South Africa\'s most popular payment gateway, PayFast.

== Description ==
Accept once off and recurring payments through Easy Digital Downloads using South Africa\'s most popular payment gateway, PayFast.

== Installation ==
1. Upload the plugin files to the ‘/wp-content/plugins’ directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the ‘Plugins’ screen in WordPress.
3. Configure Easy Digital Downloads and set PayFast as your primary gateway.

== Changelog ==
= 1.1.0 - 21-10-2021 =
* BUG FIX: Fixed logic around signature generator for PayFast.
* BUG FIX: Fixed errors when a products description has special characters in the name such as dashes, ampersands etc.
* ENHANCEMENT: Fixed admin notice showing on all WordPress dashboard pages. It only shows on EDD setting pages now.
* ENHANCEMENT: Allow R0 signup fees. Such as R0 now and set a monthly subscription for R100. Requires below filter.
* ENHANCEMENT: Filter added to automatically set signup fee to product price, similar to how other payment gateways work in EDD. `edd_pf_automatic_signup_fee` see documentation.
* ENHANCEMENT: Support translation of strings.

== Upgrade Notice ==
= 1.1.0 =
Please upgrade to the latest version for bug fixes.