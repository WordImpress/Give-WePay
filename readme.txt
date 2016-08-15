=== Give - WePay Gateway ===
Contributors: wordimpress
Tags: donations, donation, ecommerce, e-commerce, fundraising, fundraiser, wepay, we pay, gateway
Requires at least: 4.0
Tested up to: 4.6
Stable tag: 1.3
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

WePay Gateway Add-on for Give

== Description ==

This plugin requires the Give plugin activated to function properly. When activated, it adds a payment gateway for wepay.com.

== Installation ==

= Minimum Requirements =

* WordPress 4.0 or greater
* PHP version 5.3 or greater
* MySQL version 5.0 or greater
* Some payment gateways require fsockopen support (for IPN access)

= Automatic installation =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don't need to leave your web browser. To do an automatic install of the Give WePay, log in to your account, navigate to the Plugins menu and click Add New.

In the search field type "Give" and click Search Plugins. Once you have found the plugin you can view details about it such as the the point release, rating and description. Most importantly of course, you can install it by simply clicking "Install Now".

= Manual installation =

The manual installation method involves downloading our donation plugin and uploading it to your server via your favorite FTP application. The WordPress codex contains [instructions on how to do this here](http://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

= Updating =

Automatic updates should work like a charm; as always though, ensure you backup your site just in case.

== Changelog ==

= 1.3 =
* New: A separate field set for staging credentials added to the settings panel
* Tweak: Remove is_admin call around licensing integration
* Fix: Remove reliance on a set API version. Newly created WePay apps will now work with the Add-on as expected.
* Fix: Several typo fixes and text updates

= 1.2 =
* Fix: Bug where the "Fee Payer" would be inappropriately set to "Donor" when actually set to "Recipient"
* Fix: Issue where duplicate admin notices displayed when preapproving payments
* Fix: PHP Notice with WePay and constant CURL_SSLVERSION_TLSv1_2
* Updated: Reorganized and optimized code within the plugin in preparation of Give Recurring release
* Updated: WePay PHP SDK updated to the latest version (3.0)
* Updated: WePay PHP SDK also now included .pem file for better cURL compatibility

= 1.1 =
* Important updates to ensure Add-on is compatible with the latest WePay API version
* Fix: "Error: currency parameter is required"
* Fix: Ensure if the donation level name is passed to WePay in addition to the form name. For example, "Help Shelter the Homeless - Shelter for a Month" rather than just "Help Shelter the Homeless".
* Updated: Text and language improvements

= 1.0 =
* Initial plugin release. Yippee!

