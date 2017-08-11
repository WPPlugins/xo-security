=== XO Security ===
Contributors: ishitaka
Tags: security, login, pingback, xmlrpc, admin, json, rest, nginx
Requires at least: 4.0
Tested up to: 4.8
Stable tag: 1.6.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

XO Security is a plugin to enhance login related security.

== Description ==

Enhanced security features such as record login log, change login page URL, limit login attempts, disable XML-RPC pingback, disable REST API.  
This plugin does not write to .htaccess files. Nginx also works.

= Functions =

* Record login log.
* Limit login attempts.
* Login Alert.
* Change login page URL.
* Block access to wp-admin.
* Disable XML-RPC.
* Disable XML-RPC Pingback.
* Disable REST API.
* Change REST API URL prefix.
* Disable author archive page.
* Remove comment author class of comments list.
* WordPress multisite support.

== Installation ==

1. Upload the `XO-Security` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the Plugins menu in WordPress.
3. Go to "Settings" -> "XO Security" and customize behaviour as needed.

== Screenshots ==

1. Login log page.
2. Setting status page.
3. Login setting page.

== Frequently Asked Questions ==

= Login page cannot be displayed. =

Please initialize the settings.

* In wp_options table, the value of the option_name field (column) is to remove the record of "xo_security_options".
* If you have set the login page, please delete the file.

== Changelog ==

= 1.6.2 =

* Fixed sort bug in login log.
* Improve setting page.

= 1.6.1 =

* Fixed bug that login log was not displayed on multisite.
* Tested on WordPress 4.8.

= 1.6.0 =

* Change the IP address acquired the HTTP_X_FORWARDED_FOR via a proxy server.
* Added login limit with language settings.

= 1.5.3 =

* Fixed XSS vulnerability - Thanks to pluginvulnerabilities.com

= 1.5.2 =

* Improve setting page.
* Tested on PHP 7.

= 1.5.1 =

* Supported disable the REST API to WordPress 4.7.
* Tested on WordPress 4.7.

= 1.5.0 =

* Added support for WordPress multisite.
* Tested on WordPress 4.6.

= 1.4.0 =

* Added dashboard widget.

= 1.3.0 =

* Added option in login alert administrators only.
* Tested on WordPress 4.5.

= 1.2.0 =

* Added Login Alert.

= 1.1.0 =

* Added option to disable the REST API.
* Added option to change the REST API URL prefix.
* Change UserAgent white list & UserAgent black list option from the settings page to define().

= 1.0.0 =

* Initial release.
