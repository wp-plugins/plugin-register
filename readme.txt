=== Plugin Register ===
Contributors: mrwiblog
Donate link: http://www.stillbreathing.co.uk/donate/
Tags: plugin, register, activation, count, statistics, developer
Requires at least: 2.8
Tested up to: 2.9.2
Stable tag: 0.3

For Wordpress plugin developers: keep a register of when and where your plugins are activated.

== Description ==

If you are a Wordpress plugin developer the chances are your plugins are available for download from the Wordpress plugin repository. As part of that service the nice guys at Wordpress show you how many downloads of your plugin you get per day. Very useful, and if you're like me you check your downloads numbers too often.

However what these stats don't show you is where your plugin is in use - which sites it is actually being activated on. Seeing that information would allow you to see exactly which sites are using your plugin, when they installed it, and what version the site is running. That is exactly what Plugin Register does.

By including the Plugin_Register class and calling it with some simple code in your plugin which is registered to be run on activation with the `register_activation_hook()` method, your Plugin Register will be updated with the name and version of your plugin, the site name and URL. A simple call is made to your website to save these details in the Plugin Register table, and you get some great statistics on who is installing your plugins and where.

So, what do you need to put in your plugin? This example code hows you everything you need:

`// include the Plugin_Register class
require_once( "plugin-register.class.php" );

// create a new instance of the Plugin_Register class
$register = new Plugin_Register(); // leave this as it is
$register->file = __FILE__; // leave this as it is
$register->slug = "pluginregister"; // create a unique slug for your plugin (normally the plugin name in lowercase, with no spaces or special characters works fine)
$register->name = "Plugin Register"; // the full name of your plugin (this will be displayed in your statistics)
$register->version = "1.0"; // the version of your plugin (this will be displayed in your statistics)
$register->developer = "Chris Taylor"; // your name
$register->homepage = "http://www.stillbreathing.co.uk"; // your Wordpress website where Plugin Register is installed (no trailing slash)`

The reports you get include:

* Graphs showing how many registrations have been made for the last 24 hours, 14 days and 12 weeks
* A list of all plugins registered, with how many unique versions and unique sites
* A list of all version of a particular plugin, with the number of unique sites
* A list of all sites which have registered any of your plugins
* Details of what plugins were registered on a particular day
* A search, so you can see what sites have got version X of plugin Foo_Bar installed

== Privacy ==

Please be aware that this plugin break the privacy that users of Wordpress plugins have traditionally enjoyed. For many users the fact that a plugin developer is aware they are using a particular plugin will not be a problem, but for others it could caue an issue. For example, the website using your plugin may be private - even behind a firewall - or restricted. Informing you as the developer about that site could even break policies or guidelines which are in force on the site.

It is important to ensure that you do everything you can to ensure people are aware of what the use of this Plugin Register code will do. Here is the text I've been using in my plugins:

"Please note: On activation this plugin will send a message to the developer with your site name and URL. This information will be kept private. If you are not happy with the developer knowing you are using their plugin, please do not use it."

Feel free to use that, or write your own. This text should be displayed prominently in the description of your plugin - most importantly BEFORE the user has installed/activated it.

In the future I may make the registration of plugins an optional thing, for example by showing a message on activation that says something like "Thank you for using this plugin. Please click here to register your website with the plugin developer so they know you are using it."

== Installation ==

1. Install from the Wordpress plugin repository
2. Activate the plugin through the 'Plugins' menu in WordPress

== Screenshots ==

Coming soon...

== Frequently Asked Questions ==

= Why did you write this plugin? =

Although the download stats for the Wordpress repository are great, they don't actually tell you where your plugins are installed. Having an automated way of seeing who is activating your plugins - and therefore who is actually using them, not just downloading them - is fantastic to see your Open Source work actually in use.

= Is any personally-identifiable information saved? =

No. The only information saved by Plugin Register is the name and version of the plugin, and the name and URL of the Wordpress site it is installed on. I do not intend to ever get any persons personal information using this plugin. Registration is also manually-triggered, so no details are stored without the permission of the person who activated the plugin.

== Changelog ==

= 0.3 =
* Made registration manual
* Added date range graphs

= 0.2 =
* Changed main report to show just new sites registered in the last week, and show the total number of registrations and unique sites

= 0.1 =
* Initial Wordpress plugin repository commit

== Upgrade Notice ==

= 0.3 =
This version makes the registration process manual, rather than automatic. Several people were unhappy that this plugin automatically gathered information about their sites, so I changed it to be an explicit user action to register a plugin.