=== WP-Meetup-Activity ===
Contributors: o-zone
Donate link: http://www.zerozone.it
Tags: meetup, activity, meetup.com, events, group
Requires at least: 3.0
Tested up to: 3.5.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

WP-Meetup-Activity display your groups latest activities (discussions, photos...) and events in a sidebar widget

== Description ==

If you want to show your meetup's groups activities on you wordpress blog, here's the widget right for you.
Just get the API key from Meetup.com and choose which groups to show: you're done !

== Installation ==

Installing the plugin is simple, just:

1. Copy whole `wp-meetup-activity` directory to `/wp-content/plugins/` 
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Add the widget wherever you want
4. Set it up !

== Frequently Asked Questions ==

Q: I need to register to Meetup.com ?
A: You, beacuse you also need you API key

Q: Where i can get an API key ?
A: Go to http://www.meetup.com/meetup_api/key/

Q: If i need help or if i found a bug ?
A: You can write to me an e-mail to o-zone@zerozone.it

Q: This plugin can work on multisite installation (Wordpress MU) ?
A: Yes, it works !

== Changelog ==

= 0.1.7 =
* Removed Facebook support
* Fixed some bugs and minor code cleanups

= 0.1.6 =
* Fixed PHP warnings on wpdb->prepare

= 0.1.5 =
* Fixed a bug with scheduled events (wp-cron)

= 0.1.4 = 
* Added Meetup events support
* Some bugs fixed and minor code cleanups

= 0.1.1 =
* Fixed bug on ranking
* Added DB auto upgrade

= 0.1.0 =
* Added cron fetching (every hour)
* Added ranking feature for threads
* Added some minor new features
* Fixed BBCode strip

= 0.0.2 =
* Strip BBCode tags in titles
* Added "open in new window", "show author" and "show date" options
* Some minor cleanups

= 0.0.1 =
* First release

== Upgrade Notice ==

= 0.1.5 =
Bugfix release: scheduled events should not duplicate anymore

= 0.1.4 =
Added Meetup events support. Can you miss it ? 

= 0.1.1 =
Database should upgrade automatically but please deactivate and reactivate the plugin after upgrade.

= 0.1.0 =
After a successfully upgrade please deactivate and then reactivate the plugin to allow creating of database tables.
