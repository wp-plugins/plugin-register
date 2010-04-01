=== Pluginto run when the  Register ===
Contributors: mrwiblog
Donate link: http://www.stillbreathing.co.uk/donate/
Tags: plugin, register, activation, count, statistics, developer
Requires at least: 2.8
Tested up to: 2.9.2
Stable tag: 0.1

For Wordpress plugin developers: keep a register of when and where your plugins are activated.

== Description ==

If you are a Wordpress plugin developer the chances are your plugins are available for download from the Wordpress plugin repository. As part of that service the nice guys at Wordpress show you how many downloads of your plugin you get per day. Very useful, and if you're like me you check your downloads numbers too often.

However what these stats don't show you is where your plugin is in use - which sites it is actually being activated on. Seeing that would allow you to see exactly which sites are using your plugin, when they installed it, and what version the site is running. That is exactly what Plugin Register does.

By including a small function in your plugin which is registered to be run on activation with the `register_activation_hook()` method, your Plugin Register will be updated with the name and version of your plugin, the site name and URL. A simple call is made to your website to save these details in the Plugin Register table, and you get some great statistics on who is installing your plugins and where.

So, what do you need to put in your plugin? This (remember to change the [PLACEHOLDER TEXT]):

`register_activation_hook( __FILE__, [YOUR UNIQUE PLUGIN SLUG]_plugin_register );
function [YOUR UNIQUE PLUGIN SLUG]_plugin_register() {
	$plugin = "[YOUR PLUGIN NAME]";
	$version = "[YOUR PLUGIN VERSION]";
	$site = get_option( "blogname" );
	$url = get_option( "siteurl" );
	$register_url = "[YOUR WEBSITE ADDRESS]/?plugin=" . urlencode( $plugin ) . "&version=" . urlencode( $version ) . "&site=" . urlencode( $site ) . "&url=" . urlencode( $url );
	wp_remote_fopen( $register_url );
}`

The reports you get include:

* A list of all plugins registered, with how many unique versions and unique sites
* A list of all version of a particular plugin, with the number of unique sites
* A list of all sites which have registered any of your plugins
* Details of what plugins were registered on a particular day
* A search, so you can see what sites have got version X of plugin Foo_Bar installed

== Installation ==

1. Upload `plugin-name.php` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Place `<?php do_action('plugin_name_hook'); ?>` in your templates

== Screenshots ==

Coming soon...

== Frequently Asked Questions ==

= Why did you write this plugin? =

Although the download stats for the Wordpress repository are great, they don't actually tell you where your plugins are installed. Having an automated way of seeing who is activating your plugins - and therefore who is actually using them, not just downloading them - is fantastic to see your Open Source work actually in use.

= Is any personally-identifiable information saved? =

No. The only information saved by Plugin Register is the name and version of the plugin, and the name and URL of the Wordpress site it is installed on. I do not intend to ever get any persons personal information using this plugin.

== Changelog ==

= 0.1 =
* Initial Wordpress plugin repository commit

== Upgrade Notice ==

= 1.0 =
Upgrade notices describe the reason a user should upgrade.  No more than 300 characters.

= 0.5 =
This version fixes a security related bug.  Upgrade immediately.