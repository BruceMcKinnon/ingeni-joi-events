=== Ingeni Joi Events ===

Contributors: Bruce McKinnon
Tags: eventbrite
Requires at least: 5
Tested up to: 5.3
Stable tag: 2020.13

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

[joi-events-list-all]

The shortcode may be used from any page or post.


= How do change the look of the list? =

If you wish to apply your own custom CSS to the list, add the 'class' parameter to the shortcode. E.g.,


[joi-events-list-all class="my_custom_list"]

Add the appropriate CSS to the the CCS file of your Wordpress file.


To hide labels of a particular colour, use the hidelabelcolors parameter with HTML hex colours separated by a comma.

[joi-events-list-all hidelabelcolors="#xxxxxx,#yyyyyy,#zzzzzz"]




== How do you change the date format? ==


To set the day header format, specify a valid PPHP date() format.

For example: [joi-events-list-all dayheaderformat="D j M Y"]

displays the date as Fri 13 March 2020

The default is 'l j M' which displays Friday 13 March. For the full range of PHP date format, see https://www.php.net/manual/en/function.date.php




== How do you hide certain track labels ==

There are two ways to do this. You can either use the hidetracknames option.

For example: [joi-events-list-all hidetracknames="Workshops,Workouts"]

This will hide the headings for all tracks title 'Workshops' or 'Workouts'.

The other way is to use CSS. Each track wrapper is given its own ID, which can be targeted with CSS.





== Changelog ==

v2020.01 - Initial version
v2020.02 - Minor date formatting change
v2020.03 - Added track nesting, and the hidelabelcolors option for the shortcode
v2020.04 - Fixed update URL
v2020.05 - Re-did track nesting code. Added the groupcolors option to the shortcode
v2020.06 - Added extra trapping in case the 'color' field is not included in labels.
		- Extra performer info included, plus social icons.
v2020.07 - Does a sort of the days session to make sure all sessions of the same track are together.
v2020.08 - Updating code hooked in wrong location!
v2020.09 - Support the display of multiple performers.
		- Better parsing of session labels, ignoring track labels.
v2020.10  - Added support for CSS3 sticky positioning on h3 headers.
v2020.11  - Added support for the dayheaderformat parameter
v2020.12 - Re-release of v2020.11 - no code changes just version change.
v2020.13 - Added the hidetracknames parameter - supply a comma delimited list of track names to hide.
		- Added id selectors to each track wrapper.
		- Added some extra error checks for empty arrays.
