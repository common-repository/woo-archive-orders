=== WooCommerce Archive Orders ===
Contributors: bastho,
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=RR4ACWX2S39AN
Tags: woocommerce, orders, archive, performances
Requires at least: 5.0
Tested up to: 5.1.1
Stable tag: stable
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Let old Woocommerce orders be archived.

== Description ==

Automatically archive old orders. Old statuses are kept to allow revert. It simply lighten the "all orders" view.

== Installation ==

1. Upload `woo-archive-orders` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress admin
3. You can edit defaults settings in Woocommerce » Settings » Advanced » Archives

== Frequently asked questions ==

= Are archived order still accessible? =

**Yes**. They're all listed in the "archived" section, but you can still see there previous status.

= Wich order statuses are supported? =

**All**. Every order statuses (even custom ones) are cloned to an "archived" version. For examplce: «Completed» and «Completed (archived)»

= Can I choose max age for orders? =

**Yes.** Out of the box, you have to archive manually your orders, but you can set a max age in Woocommerce » Settings » Advanced » Archives. Every order older than your setting will be archived.


== Changelog ==

= 1.0 =

- Initial release
