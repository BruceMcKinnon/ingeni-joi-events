=== Ingeni Joi Events ===

Contributors: Bruce McKinnon
Tags: eventbrite
Requires at least: 5
Tested up to: 5.3
Stable tag: 2020.01

Display a list of event sessions for a specific organiser



== Description ==

* - Loads a list of sessions for a specific event from the Joi Events server.

* - Caches details to reduce load time




== Installation ==

1. Upload the 'ingeni-joi-events' folder to the '/wp-content/plugins/' directory.

2. Activate the plugin through the 'Plugins' menu in WordPress.

3. Obtain the URL for your Joi Events JSON feed.

4. Call functions within the plugin from your theme functions.php



== Frequently Asked Questions ==


= How do a display a list of events? =

Use the Wordpress shortcode

[ingeni-joi-events]

The shortcode may be used from any page or post.


= How do change the look of the list? =

If you wish to apply your own custom CSS to the list, add the 'class' parameter to the shortcode. E.g.,


[ingeni-joi-events class="my_custom_list"]

Add the appropriate CSS to the the CCS file of your Wordpress file.



== Changelog ==

v2020.01 - Initial version
