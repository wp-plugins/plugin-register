<?php
/**
 * @package Plugin Register
 * @author Chris Taylor
 * @version 0.5.1
 */
/*
Plugin Name: Plugin Register
Plugin URI: http://www.stillbreathing.co.uk/wordpress/plugin-register/
Description: This is a plugin for plugin developers only. Plugin Register allows you to keep track of what version of your plugins are being installed. By registering a function to be run on activation of your plugin, a call is made to this plugin which stores details the site which is installing your plugin, which plugin is being installed, and the plugin version. Some reports are available so you can see what versions are installed.
Author: Chris Taylor
Version: 0.5.1
Author URI: http://www.stillbreathing.co.uk/
*/

// set the current version
function pluginregister_current_version() {
	return "0.5.1";
}

// ==========================================================================================
// hooks

// set activation hooks
register_activation_hook( __FILE__, "pluginregister_activate" );

// initialise the plugin
pluginregister_init();

// ==========================================================================================
// service calling function

require_once( "plugin-register.class.php" );

$register = new Plugin_Register();
$register->file = __FILE__;
$register->slug = "pluginregister";
$register->name = "Plugin Register";
$register->version = pluginregister_current_version();
$register->developer = "Chris Taylor";
$register->homepage = "http://www.stillbreathing.co.uk";
$register->register_message = 'Hey! Thanks! <a href="%1">Register the plugin here</a>.';
$register->thanks_message = "That's great, thanks a million.";
$register->Plugin_Register();

// ==========================================================================================
// initialisation functions

function pluginregister_init() {
	if ( function_exists( "add_action" ) ) {
		add_action( "template_redirect", "pluginregister_service" );
		add_action( "admin_menu", "pluginregister_admin_menu" );
		add_action( "admin_head", "pluginregister_admin_head" );
		add_action( "admin_menu", "pluginregister_download_class" );
		add_action( "wp_dashboard_setup", "pluginregister_dashboard" );
	}
}

// ==========================================================================================
// activation functions

// activate the plugin
function pluginregister_activate() {	
	// create the table
	pluginregister_create_table();
	// add the version
	update_option ( "pluginregister_version", pluginregister_current_version() );
}

// create the table
function pluginregister_create_table() {

	global $wpdb;

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	
	// table to store the vouchers
	$sql = "CREATE TABLE " . $wpdb->prefix . "plugin_register (
		  id mediumint(9) NOT NULL AUTO_INCREMENT,
		  time bigint(11) DEFAULT '0' NOT NULL,
		  plugin VARCHAR(50) NOT NULL,
		  sitename varchar(500) NOT NULL,
		  url varchar(500) NOT NULL,
		  pluginversion varchar(12) NOT NULL DEFAULT 0,
		  PRIMARY KEY  id (id)
		);";
	dbDelta( $sql );
}

// ==========================================================================================
// admin functions

function pluginregister_dashboard() {
	wp_add_dashboard_widget( 'pluginregister_dashboard_report', __( 'Plugin Register', "pluginregister"  ), 'pluginregister_dashboard_report' );
}

function pluginregister_dashboard_report() {
	if (current_user_can("edit_users")) {
		pluginregister_new_urls_report();
	}
}

// add the admin menu page
function pluginregister_admin_menu() {

	add_submenu_page('plugins.php', __( "Plugin Register", "pluginregister" ), __( "Plugin Register", "pluginregister" ), 'edit_users', 'pluginregister_reports', 'pluginregister_reports');
	
}

function pluginregister_download_class() {
	if ( @$_GET["page"] == "pluginregister_reports" && @$_GET["download"] == "class" ) {
		header( "Content-type:application/octet-stream" );
		header( "Content-Disposition: attachment; filename=plugin-register.class.php" );
		$file = file_get_contents( str_replace( ".php", ".class.php", __FILE__ ) );
		print $file;
		exit();
	}
}

/// show the reports
function pluginregister_reports() {

	global $wpdb;

	echo '
	<div class="wrap" id="pluginregister">
	<div id="icon-plugins" class="icon32"><br /></div>
	';
	
	if ( isset( $_GET["deletesite"] ) && $_GET["deletesite"] != "" ) {
		$sql = $wpdb->prepare( "delete from " . $wpdb->prefix . "plugin_register where url = '%s'", urldecode( $_GET["deletesite"] ) );
		if ( $wpdb->query( $sql ) ) {
			echo '
			<div id="message" class="updated fade">
				<p style="line-height:1.4em">' . __( "The registrations for this site have been deleted", "pluginregister" ) . '</p>
			</div>
			';
		} else {
			echo '
			<div id="message" class="error">
				<p style="line-height:1.4em">' . __( "Sorry, the registrations for this site could not be deleted", "pluginregister" ) . '</p>
			</div>
			';
		}
	}
	
	if ( isset( $_GET["deleteregistration"] ) && $_GET["deleteregistration"] != "" ) {
		$sql = $wpdb->prepare( "delete from " . $wpdb->prefix . "plugin_register where id = %d", $_GET["deleteregistration"] );
		if ( $wpdb->query( $sql ) ) {
			echo '
			<div id="message" class="updated fade">
				<p style="line-height:1.4em">' . __( "The plugin registration has been deleted", "pluginregister" ) . '</p>
			</div>
			';
		} else {
			echo '
			<div id="message" class="error">
				<p style="line-height:1.4em">' . __( "Sorry, the plugin registration could not be deleted", "pluginregister" ) . '</p>
			</div>
			';
		}
	}
	
	if ( !isset( $_GET["plugin"] ) && !isset( $_GET["version"] ) && !isset( $_GET["siteq"] ) && !isset( $_GET["pluginq"] ) && !isset( $_GET["versionq"] ) && !isset( $_GET["url"] ) && !isset( $_GET["date"] ) ) {
	
		pluginregister_main_report();

	} else if ( isset( $_GET["plugin"] ) && !isset( $_GET["version"] ) ) {
	
		pluginregister_plugin_report();
	
	} else if ( isset( $_GET["plugin"] ) && isset( $_GET["version"] ) ) {
	
		pluginregister_version_report();
	
	} else if ( isset( $_GET["siteq"] ) || isset( $_GET["pluginq"] ) || isset( $_GET["versionq"] ) ) {
	
		pluginregister_search_report();
		
	} else if ( isset( $_GET["url"] ) ) {
	
		pluginregister_url_report();
	
	} else if ( isset( $_GET["date"] ) ) {
	
		pluginregister_date_report();
	
	}
	
	echo '	
	</div>
	';

}

// add the CSS and JavaScript for the reports
function pluginregister_admin_head()
{
	if (isset($_GET["page"]) && $_GET["page"] == "pluginregister_reports")
	{	
	echo '
<style type="text/css">
#pluginregister td {
vertical-align: bottom;
}
#pluginregister ul.inline li {
display: inline;
margin-right: 2em;
}
</style>';
	}
}

// show the main report
function pluginregister_main_report() {

	global $wpdb;
	echo '
	<h2>' . __( "Plugin Register", "pluginregister" ) . '</h2>
	
	<form action="plugins.php" method="get">
		<p>' . __( "Site name/url", "pluginregister" ) . ' <input type="text" name="siteq" />
		' . __( "Plugin name", "pluginregister" ) . ' <input type="text" name="pluginq" />
		' . __( "Plugin version", "pluginregister" ) . ' <input type="text" name="versionq" style="width:6em" />
		<input type="submit" class="button" value="' . __( "Search plugin register", "pluginregister" ) . '" />
		<input type="hidden" name="page" value="pluginregister_reports" /></p>
	</form>';
	
	// show date range reports
	echo '
	<h3>' . __("Date range reports") . '</h3>
	
	<ul class="inline">
		<li><a href="plugins.php?page=pluginregister_reports#range24hours" class="button">' . __("Last 24 hours") . '</a></li>
		<li><a href="plugins.php?page=pluginregister_reports&amp;range=14days#range14days" class="button">' . __("Last 14 days") . '</a></li>
		<li><a href="plugins.php?page=pluginregister_reports&amp;range=12weeks#range12weeks" class="button">' . __("Last 12 weeks") . '</a></li>
		<li><a href="plugins.php?page=pluginregister_reports&amp;range=12months#range12months" class="button">' . __("Last 12 months") . '</a></li>
	</ul>

	';
	
	// show 24 hour report
	if (!isset($_GET["range"]))
	{
		
		pluginregister_24hour_report();
		
	}
	
	// show 14 day report
	if (isset($_GET["range"]) && $_GET["range"] =="14days")
	{
		
		pluginregister_14day_report();
		
	}
	
	// show 12 week report
	if (isset($_GET["range"]) && $_GET["range"] =="12weeks")
	{
	
		pluginregister_12week_report();
		
	}
	
	// show 12 month report
	if (isset($_GET["range"]) && $_GET["range"] =="12months")
	{
	
		pluginregister_12month_report();
		
	}
	
	echo '
	<h3 style="padding-top:2em">' . __( "Registered plugins", "pluginregister" ) . '</h3>
	';
	
	// get the unique plugins registered
	$sql = "select r.plugin,
			count(r.id) as registrations,
			(select count(distinct(url)) from " . $wpdb->prefix . "plugin_register where plugin = r.plugin) as sites,
			(select count(distinct(pluginversion)) from " . $wpdb->prefix . "plugin_register where plugin = r.plugin) as versions
			from " . $wpdb->prefix . "plugin_register r
			group by r.plugin 
			order by r.plugin;";
	$plugins = $wpdb->get_results( $sql );
	
	if( $plugins && is_array( $plugins ) && count( $plugins ) > 0 ) {
		
		$totalsites = $wpdb->get_var("select count(distinct(url)) from " . $wpdb->prefix . "plugin_register;");
		$totalplugins = $wpdb->get_var("select count(id) from " . $wpdb->prefix . "plugin_register;");
	
		echo '
		<table class="widefat fixed" cellspacing="0">

		<thead>
		<tr class="thead">
			<th>' . __( "Plugin", "pluginregister" ) . '</th>
			<th>' . __( "Registrations", "pluginregister" ) . '</th>
			<th>' . __( "Unique versions", "pluginregister" ) . '</th>
			<th>' . __( "Unique sites", "pluginregister" ) . '</th>
		</tr>
		</thead>
		
		<tfoot>
		<tr>
			<th></th>
			<th>' . $totalplugins . '</th>
			<th></th>
			<th>' . $totalsites . '</th>
		</tr>
		</tfoot>

		<tbody>
		';
		foreach ( $plugins as $plugin ) {
			echo '
			<tr>
				<td><a href="plugins.php?page=pluginregister_reports&amp;plugin=' . urlencode( $plugin->plugin ) . '">' . $plugin->plugin . '</a></td>
				<td>' . $plugin->registrations . '</td>
				<td>' . $plugin->versions . '</td>
				<td>' . $plugin->sites . '</td>
			</tr>
			';
		}
		echo '
		</tbody>
		</table>
		';
	} else {
		echo '
		<p>' . __( "No registered plugins", "pluginregister" ) . '</p>
		';
	}
	
	pluginregister_new_urls_report( 2592000, false );
	
	?>
	<h3 style="padding-top:2em"><?php echo __( "Using Plugin Register in your plugins", "pluginregister" ); ?></h3>
	<p><?php echo __( 'To use Plugin Register in your plugins you must include the <a href="plugins.php?page=pluginregister_reports&amp;download=class">plugin-register.class.php</a> file, then create a new instance of the Plugin_Register class. The code below gives an example.', 'pluginregister' ); ?></p>
	
	<textarea rows="11" cols="50" style="width:95%;font-family:monospace">// include the Plugin_Register class
require_once( &quot;plugin-register.class.php&quot; ); // leave this as it is

// create a new instance of the Plugin_Register class
$register->file = __FILE__; // leave this as it is
$register->slug = &quot;pluginregister&quot;; // create a unique slug for your plugin (normally the plugin name in lowercase, with no spaces or special characters works fine)
$register->name = &quot;Plugin Register&quot;; // the full name of your plugin (this will be displayed in your statistics)
$register->version = &quot;1.0&quot;; // the version of your plugin (this will be displayed in your statistics)
$register->developer = &quot;Chris Taylor&quot;; // your name
$register->homepage = &quot;http://www.stillbreathing.co.uk&quot;; // your Wordpress website where Plugin Register is installed (no trailing slash)

// the next two lines are optional
// 'register_plugin' is the message you want to be displayed when someone has activated this plugin. The %1 is replaced by the correct URL to register the plugin (the %1 MUST be the HREF attribute of an &lt;a&gt; element)
$register->register_message = 'Hey! Thanks! &lt;a href=&quot;%1&quot;&gt;Register the plugin here&lt;/a&gt;.';
// 'thanks_message' is the message you want to display after someone has registered your plugin
$register->thanks_message = &quot;That's great, thanks a million.&quot;;

$register->Plugin_Register(); // leave this as it is</textarea>

	<p><?php echo __( "<strong>Important:</strong> If you are using the 'register_activation_hook' function in your plugin please ensure you call the Plugin_Register class AFTER your last 'register_activation_hook' call. If in doubt put your Plugin_Register code at the very end of your plugin file.", "pluginregister" ); ?></p>

<?

}

function pluginregister_new_urls_report( $seconds = 604800, $mini = true ) {
	global $wpdb;

	// set up pagination start
	$limit = 10;
	if ( !$mini ) $limit = 25;
	$start = findStart( $limit );
	$end = $start + $limit;
	
	// get the unique sites first registered in the last week
	$sql = $wpdb->prepare( "select SQL_CALC_FOUND_ROWS s.sitename, s.url,
			count(s.id) as registrations,
			(select min(time) from sb.wp_plugin_register where url = s.url) as firstregistration
			from " . $wpdb->prefix . "plugin_register s
			where (select min(time) from " . $wpdb->prefix . "plugin_register where url = s.url) > " . (time()-$seconds) . "
			group by s.url 
			order by firstregistration desc
			limit %d, %d;",
			$start,
			$limit );
	$sites = $wpdb->get_results( $sql );
	
	if( $sites && is_array( $sites ) && count( $sites ) > 0 ) {
	
		// get the total number of rows
		$count = $wpdb->get_var( "SELECT FOUND_ROWS();" );
		
		if ( $count < $limit ) {
			$view = $count;
		} else {
			$view = ( $start+1 ) . '&ndash;' . $end;
		}

		// get the pages
		$pages = findPages($count, $limit);
		
		// set up the pagination links
		$pagelist = paginate_links( array(
			'base' => add_query_arg( 'paged', '%#%' ),
			'format' => '',
			'prev_text' => __('&laquo;'),
			'next_text' => __('&raquo;'),
			'total' => $pages,
			'current' => $_GET['paged']
		));
	
		$days = $seconds / 60 / 60 / 24;
	
		if ( !$mini ) {
		echo '
		<h3 style="padding-top:2em">' . sprintf( __( "New registered sites in the last %d days", "pluginregister" ), $days ) . '</h3>
		';
		} else {
		echo '
		<p>' . sprintf( __( 'New registered sites in the last %d days. <a href="plugins.php?page=pluginregister_reports">View full reports</a>.', "pluginregister" ), $days ) . '</p>
		';
		}
		
		echo '
		<div class="tablenav">
		<div class="tablenav-pages">
		<span class="displaying-num">Displaying ' . $view . ' of ' . $count . '</span>
			' . $pagelist . '
		</div>
		</div>
		<table class="widefat fixed" cellspacing="0">

		<thead>
		<tr class="thead">
			<th>' . __( "Site name", "pluginregister" ) . '</th>
			<th>' . __( "URL", "pluginregister" ) . '</th>
			<th>' . __( "First registration", "pluginregister" ) . '</th>
			';
			if ( !$mini ) {
			echo '<th>' . __( "Registrations", "pluginregister" ) . '</th>
			<th>' . __( "Delete registrations", "pluginregister" ) . '</th>';
			}
			echo '
		</tr>
		</thead>

		<tbody>
		';
		foreach ( $sites as $site ) {
			
			$sql = $wpdb->prepare( "select distinct(plugin)
							from " . $wpdb->prefix . "plugin_register
							where url = %s;",
							$site->url );
			$plugins = $wpdb->get_results( $sql );
		
			echo '
			<tr>
				<td><a href="plugins.php?page=pluginregister_reports&amp;url=' . urlencode( $site->url ) . '">' . $site->sitename . '</a></td>
				<td><a href="' . $site->url . '">' . $site->url . '</a></td>
				<td>' . date( "F j, Y, g:i a", $site->firstregistration ) . '</td>';
				if ( !$mini ) {
				echo '
				<td>' . $site->registrations . '</td>
				<td><a href="plugins.php?page=pluginregister_reports&amp;deletesite=' . urlencode( $site->url ) . '" class="button">' . __( "Delete registrations", "pluginregister" ) . '</a></td>
				';
				}
				echo '
			</tr>
			<tr>
				';
				if ( !$mini ) {
				echo '<td colspan="5"><span style="font-style: italic">';
				} else {
				echo '<td colspan="3"><span style="font-style: italic">';
				}
				if ( is_array( $plugins ) && count( $plugins ) > 0) {
					$list = "";
					foreach( $plugins as $plugin ) {
						$list .= $plugin->plugin . ", ";
					}
					echo trim( trim( $list ), "," );
				}
				echo '</span></td>
			</tr>
			';
		}
		echo '
		</tbody>
		</table>
		<div class="tablenav">
		<div class="tablenav-pages">
		<span class="displaying-num">Displaying ' . $view . ' of ' . $count . '</span>
			' . $pagelist . '
		</div>
		</div>
		';
	} else {
		echo '
		<p>' . __( "No sites found", "pluginregister" ) . '</p>
		';
	}
}

// show 24 hour report
function pluginregister_24hour_report($plugin = "", $version = "")
{

	global $wpdb;
	
	$plugin = $wpdb->escape($plugin);
	$version = $wpdb->escape($version);
	
	$begin = time() - (60 * 60 * 23);
	$start = $begin;
	
	for($i = 0; $i < 24; $i++)
	{
		$hours[] = $start;
	
		$sql = "select count(id) as num
			from " . $wpdb->prefix . "plugin_register
			where hour(FROM_UNIXTIME(time)) = hour(FROM_UNIXTIME(" . $start . "))
			and day(FROM_UNIXTIME(time)) = day(FROM_UNIXTIME(" . $start . "))
			and month(FROM_UNIXTIME(time)) = month(FROM_UNIXTIME(" . $start . "))
			and year(FROM_UNIXTIME(time)) = year(FROM_UNIXTIME(" . $start . "))
			and 
			('" . $plugin . "' = '' or plugin = '" . $plugin . "')
			and
			('" . $version . "' = '' or plugin = '" . $version . "');";

		$registrationsnum[] = $wpdb->get_var($sql);
		
		$start = $start + (60 * 60);			
	}
	
	$registrationsmax = 0;
	
	for($i = 0; $i < 24; $i++)
	{
		if ($registrationsnum[$i] > $registrationsmax) { $registrationsmax = $registrationsnum[$i]; }
	}
	
	echo '
	<h4 id="range24hours">' . __("Plugin registrations in the last 24 hours") . '</h4>
	<table class="widefat post fixed">
		<thead>
		<tr>
			<th style="width:100px"></th>
		';
	for($i = 0; $i < 24; $i++)
	{
		echo '
			<th>' . date("H", $hours[$i]) . '</th>
		';
	}
	echo '
		</tr>
		</thead>
		<tbody>
		<tr>
		<th style="width:100px">' . __("Registrations") . '</th>
		';
		
	for($i = 0; $i < 24; $i++)
	{
		echo '
			<td>';
		if ($registrationsnum[$i] != "0" && $registrationsmax != "0")
		{
		echo '
			<div style="background:#6F6F6F;width:10px;height:' . (round(($registrationsnum[$i]/$registrationsmax)*100)) . 'px"></div>';
			}
		echo '
			' . $registrationsnum[$i] . '</td>
		';
	}
	echo '
		</tr>
		</tbody>
	</table>
	';
}

// show 14 day report
function pluginregister_14day_report($plugin = "", $version = "")
{

	global $wpdb;
	
	$plugin = $wpdb->escape($plugin);
	$version = $wpdb->escape($version);

	$begin = time() - (60 * 60 * 24 * 13);
	$start = $begin;
	
	for($i = 0; $i < 14; $i++)
	{
		$days[] = date("jS M", $start);
	
		$sql = "select count(id) as num
			from " . $wpdb->prefix . "plugin_register
			where day(FROM_UNIXTIME(time)) = day(FROM_UNIXTIME(" . $start . "))
			and month(FROM_UNIXTIME(time)) = month(FROM_UNIXTIME(" . $start . "))
			and year(FROM_UNIXTIME(time)) = year(FROM_UNIXTIME(" . $start . "))
			and 
			('" . $plugin . "' = '' or plugin = '" . $plugin . "')
			and
			('" . $version . "' = '' or plugin = '" . $version . "');";

		$registrationsnum[] = $wpdb->get_var($sql);
		
		$start = $start + (60 * 60 * 24);			
	}
	
	$registrationsmax = 0;
	
	for($i = 0; $i < 14; $i++)
	{
		if ($registrationsnum[$i] > $registrationsmax) { $registrationsmax = $registrationsnum[$i]; }
	}
	
	echo '
	<h4 id="range14days">' . __("Plugin registrations in the last 14 days") . '</h4>
	<table class="widefat post fixed">
		<thead>
		<tr>
			<th style="width:100px"></th>
		';
	for($i = 0; $i < 14; $i++)
	{
		echo '
			<th>' . $days[$i] . '</th>
		';
	}
	echo '
		</tr>
		</thead>
		<tbody>
		<tr>
		<th style="width:100px">' . __("Registrations") . '</th>
		';
		
	for($i = 0; $i < 14; $i++)
	{
		echo '
			<td>';
		if ($registrationsnum[$i] != "0" && $registrationsmax != "0")
		{
		echo '
			<div style="background:#6F6F6F;width:10px;height:' . (round(($registrationsnum[$i]/$registrationsmax)*100)) . 'px"></div>';
			}
		echo '
			' . $registrationsnum[$i] . '</td>
		';
	}
	echo '
		</tr>
		</tbody>
	</table>
	';
}

// show 12 week report
function pluginregister_12week_report($plugin = "", $version = "")
{

	global $wpdb;
	
	$plugin = $wpdb->escape($plugin);
	$version = $wpdb->escape($version);

	// show 12 week report
	$begin = time() - (60 * 60 * 24 * 7 * 11);
	$start = $begin;
	
	for($i = 0; $i < 12; $i++)
	{
		$weeks[] = date("jS M", $start);
	
		$sql = "select count(id) as num
			from " . $wpdb->prefix . "plugin_register
			where week(FROM_UNIXTIME(time)) = week(FROM_UNIXTIME(" . $start . "))
			and year(FROM_UNIXTIME(time)) = year(FROM_UNIXTIME(" . $start . "))
			and 
			('" . $plugin . "' = '' or plugin = '" . $plugin . "')
			and
			('" . $version . "' = '' or plugin = '" . $version . "');";

		$registrationsnum[] = $wpdb->get_var($sql);
		
		$start = $start + (60 * 60 * 24 * 7);			
	}
	
	$registrationsmax = 0;
	
	for($i = 0; $i < 12; $i++)
	{
		if ($registrationsnum[$i] > $registrationsmax) { $registrationsmax = $registrationsnum[$i]; }
	}
	
	echo '
	<h4 id="range12weeks">' . __("Plugin registrations in the last 12 weeks") . '</h4>
	<table class="widefat post fixed">
		<thead>
		<tr>
			<th style="width:100px"></th>
		';
	for($i = 0; $i < 12; $i++)
	{
		echo '
			<th>' . $weeks[$i] . '</th>
		';
	}
	echo '
		</tr>
		</thead>
		<tbody>
		<tr>
		<th style="width:100px">' . __("Registrations") . '</th>
		';
		
	for($i = 0; $i < 12; $i++)
	{
		echo '
			<td>';
		if ($registrationsnum[$i] != "0" && $registrationsmax != "0")
		{
		echo '
			<div style="background:#6F6F6F;width:10px;height:' . (round(($registrationsnum[$i]/$registrationsmax)*100)) . 'px"></div>';
			}
		echo '
			' . $registrationsnum[$i] . '</td>
		';
	}
	echo '
		</tr>
		</tbody>
	</table>
	';
}

// show 12 month report
function pluginregister_12month_report($plugin = "", $version = "")
{

	global $wpdb;
	
	$plugin = $wpdb->escape($plugin);
	$version = $wpdb->escape($version);

	// show 12 week report
	$begin = pluginregister_addMonthToDate( pluginregister_addYearToDate( time(), -1 ), 1);
	$start = $begin;
	
	for($i = 0; $i < 12; $i++)
	{
		$months[] = date("M y", $start);
	
		$sql = "select count(id) as num
			from " . $wpdb->prefix . "plugin_register
			where month(FROM_UNIXTIME(time)) = month(FROM_UNIXTIME(" . $start . "))
			and year(FROM_UNIXTIME(time)) = year(FROM_UNIXTIME(" . $start . "))
			and 
			('" . $plugin . "' = '' or plugin = '" . $plugin . "')
			and
			('" . $version . "' = '' or plugin = '" . $version . "');";

		$registrationsnum[] = $wpdb->get_var($sql);
		
		$start = pluginregister_addMonthToDate( $start, 1 );
	}
	
	$registrationsmax = 0;
	
	for($i = 0; $i < 12; $i++)
	{
		if ($registrationsnum[$i] > $registrationsmax) { $registrationsmax = $registrationsnum[$i]; }
	}
	
	echo '
	<h4 id="range12months">' . __("Plugin registrations in the last 12 months") . '</h4>
	<table class="widefat post fixed">
		<thead>
		<tr>
			<th style="width:100px"></th>
		';
	for($i = 0; $i < 12; $i++)
	{
		echo '
			<th>' . $months[$i] . '</th>
		';
	}
	echo '
		</tr>
		</thead>
		<tbody>
		<tr>
		<th style="width:100px">' . __("Registrations") . '</th>
		';
		
	for($i = 0; $i < 12; $i++)
	{
		echo '
			<td>';
		if ($registrationsnum[$i] != "0" && $registrationsmax != "0")
		{
		echo '
			<div style="background:#6F6F6F;width:10px;height:' . (round(($registrationsnum[$i]/$registrationsmax)*100)) . 'px"></div>';
			}
		echo '
			' . $registrationsnum[$i] . '</td>
		';
	}
	echo '
		</tr>
		</tbody>
	</table>
	';
}

// from user contributed notes at http://www.php.net/manual/en/function.mktime.php
function pluginregister_addYearToDate( $timeStamp, $totalYears=1 ){
	$thePHPDate = getdate( $timeStamp );
	$thePHPDate['year'] = $thePHPDate['year']+$totalYears;
	$timeStamp = mktime( $thePHPDate['hours'], $thePHPDate['minutes'], $thePHPDate['seconds'], $thePHPDate['mon'], $thePHPDate['mday'], $thePHPDate['year'] );
	return $timeStamp;
}
function pluginregister_addMonthToDate( $timeStamp, $totalMonths=1 ){
	// You can add as many months as you want. mktime will accumulate to the next year.
	$thePHPDate = getdate( $timeStamp ); // Covert to Array    
	$thePHPDate['mon'] = $thePHPDate['mon']+$totalMonths; // Add to Month    
	$timeStamp = mktime( $thePHPDate['hours'], $thePHPDate['minutes'], $thePHPDate['seconds'], $thePHPDate['mon'], $thePHPDate['mday'], $thePHPDate['year'] ); // Convert back to timestamp
	return $timeStamp;
}
function pluginregister_addDayToDate( $timeStamp, $totalDays=1 ){
	// You can add as many days as you want. mktime will accumulate to the next month / year.
	$thePHPDate = getdate( $timeStamp );
	$thePHPDate['mday'] = $thePHPDate['mday']+$totalDays;
	$timeStamp = mktime( $thePHPDate['hours'], $thePHPDate['minutes'], $thePHPDate['seconds'], $thePHPDate['mon'], $thePHPDate['mday'], $thePHPDate['year'] );
	return $timeStamp;
}
function pluginregister_addHourToDate( $timeStamp, $totalHours=1 ){
	// You can add as many days as you want. mktime will accumulate to the next month / year.
	$thePHPDate = getdate( $timeStamp );
	$thePHPDate['mhour'] = $thePHPDate['mhour']+$totalHours;
	$timeStamp = mktime( $thePHPDate['mhour'], $thePHPDate['minutes'], $thePHPDate['seconds'], $thePHPDate['mon'], $thePHPDate['day'], $thePHPDate['year'] );
	return $timeStamp;
}

// show the plugin report
function pluginregister_plugin_report() {

	global $wpdb;
	$plugin = urldecode( $_GET["plugin"] );

	echo '
	<h2><a href="plugins.php?page=pluginregister_reports">' . __( "Plugin Register", "pluginregister" ) . '</a>: ' . $plugin . '</h2>
	<h3>' . __( "Registered versions", "pluginregister" ) . '</h3>
	';
	
	// get the unique plugins registered
	$sql = $wpdb->prepare( "select r.plugin, r.pluginversion as version, 
						count(r.id) as registrations, 
						(select count(distinct(url)) from " . $wpdb->prefix . "plugin_register where plugin = r.plugin and pluginversion = r.pluginversion) as sites
						from " . $wpdb->prefix . "plugin_register r
						where r.plugin = %s group by r.plugin, r.pluginversion 
						order by r.pluginversion;", 
						$plugin );
	$versions = $wpdb->get_results( $sql );
	
	if( $versions && is_array( $versions ) && count( $versions ) > 0 ) {
		echo '
		<table class="widefat fixed" cellspacing="0">

		<thead>
		<tr class="thead">
			<th>' . __( "Plugin", "pluginregister" ) . '</th>
			<th>' . __( "Version", "pluginregister" ) . '</th>
			<th>' . __( "Registrations", "pluginregister" ) . '</th>
			<th>' . __( "Unique sites", "pluginregister" ) . '</th>
		</tr>
		</thead>

		<tbody>
		';
		foreach ( $versions as $version ) {
			echo '
			<tr>
				<td><a href="plugins.php?page=pluginregister_reports&amp;plugin=' . urlencode( $version->plugin ) . '&amp;version=' . urlencode( $version->version ) . '">' . $version->plugin . '</a></td>
				<td><a href="plugins.php?page=pluginregister_reports&amp;plugin=' . urlencode( $version->plugin ) . '&amp;version=' . urlencode( $version->version ) . '">' . $version->version . '</a></td>
				<td>' . $version->registrations . '</td>
				<td>' . $version->sites . '</td>
			</tr>
			';
		}
		echo '
		</tbody>
		</table>
		';
	} else {
		echo '
		<p>' . __( "No registered versions of this plugin", "pluginregister" ) . '</p>
		';
	}
}

// show the plugin version report
function pluginregister_version_report() {

	global $wpdb;
	$plugin = urldecode( $_GET["plugin"] );
	$version = urldecode( $_GET["version"] );
	
	echo '
	<h2><a href="plugins.php?page=pluginregister_reports">' . __( "Plugin Register", "pluginregister" ) . '</a>: <a href="plugins.php?page=pluginregister_reports&amp;plugin=' . urlencode( $plugin ) . '">' . $plugin . '</a>: ' . $version . '</h2>
	';
	
	// set up pagination start
	$limit = 25;
	$start = findStart( $limit );
	$end = $start + $limit;
	
	$sql = $wpdb->prepare( "select SQL_CALC_FOUND_ROWS id, plugin, pluginversion as version, sitename, url, time
							from " . $wpdb->prefix . "plugin_register
							where plugin = %s
							and pluginversion = %s
							order by time desc
							limit %d, %d;",
							$plugin,
							$version,
							$start,
							$limit );
	
	// get the results
	$results = $wpdb->get_results( $sql );
	
	if( $results && is_array( $results ) && count( $results ) > 0 ) {
	
		// get the total number of rows
		$count = $wpdb->get_var( "SELECT FOUND_ROWS();" );
		
		if ( $count < $limit ) {
			$view = $count;
		} else {
			$view = ( $start+1 ) . '&ndash;' . $end;
		}

		// get the pages
		$pages = findPages($count, $limit);
		
		// set up the pagination links
		$pagelist = paginate_links( array(
			'base' => add_query_arg( 'paged', '%#%' ),
			'format' => '',
			'prev_text' => __('&laquo;'),
			'next_text' => __('&raquo;'),
			'total' => $pages,
			'current' => $_GET['paged']
		));
	
		echo '
		<div class="tablenav">
		<div class="tablenav-pages">
		<span class="displaying-num">Displaying ' . $view . ' of ' . $count . '</span>
			' . $pagelist . '
		</div>
		</div>
		<table class="widefat fixed" cellspacing="0">

		<thead>
		<tr class="thead">
			<th>' . __( "Plugin", "pluginregister" ) . '</th>
			<th>' . __( "Version", "pluginregister" ) . '</th>
			<th>' . __( "Site", "pluginregister" ) . '</th>
			<th>' . __( "Registration date", "pluginregister" ) . '</th>
			<th>' . __( "Delete registration", "pluginregister" ) . '</th>
		</tr>
		</thead>

		<tbody>
		';
		foreach ( $results as $result ) {
			echo '
			<tr>
				<td>' . $result->plugin . '</td>
				<td>' . $result->version . '</td>
				<td><a href="plugins.php?page=pluginregister_reports&amp;url=' . urlencode( $result->url ) . '">' . $result->sitename . '</a></td>
				<td><a href="plugins.php?page=pluginregister_reports&amp;date=' . date( "Y/n/j", $result->time) . '">' . date( "F j, Y, g:i a", $result->time ) . '</a></td>
				<td><a href="plugins.php?page=pluginregister_reports&amp;plugin=' . $_GET["plugin"] . '&amp;version=' . $_GET["version"] . '&amp;deleteregistration=' . $result->id . '" class="button">' . __( "Delete registration", "pluginregister" ) . '</a></td>
			</tr>
			';
		}
		echo '
		</tbody>
		</table>
		<div class="tablenav">
		<div class="tablenav-pages">
		<span class="displaying-num">Displaying ' . $view . ' of ' . $count . '</span>
			' . $pagelist . '
		</div>
		</div>
		';
	} else {
		echo '
		<p>' . __( "No registrations found for this plugin version", "pluginregister" ) . '</p>
		';
	}
}

// show the search report
function pluginregister_search_report() {

	global $wpdb;
	$siteq = @$_GET["siteq"];
	$pluginq = @$_GET["pluginq"];
	$versionq = @$_GET["versionq"];
	
	echo '
	<h2><a href="plugins.php?page=pluginregister_reports">' . __( "Plugin Register", "pluginregister" ) . '</a>: ' . __( "Search", "pluginregister" ) . '</h2>
	
	<form action="plugins.php" method="get">
		<p>' . __( "Site name/url", "pluginregister" ) . ' <input type="text" name="siteq" value="' . $siteq . '" />
		' . __( "Plugin name", "pluginregister" ) . ' <input type="text" name="pluginq" value="' . $pluginq . '" />
		' . __( "Plugin version", "pluginregister" ) . ' <input type="text" name="versionq" value="' . $versionq . '" style="width:6em" />
		<input type="submit" class="button" value="' . __( "Search plugin register", "pluginregister" ) . '" />
		<input type="hidden" name="page" value="pluginregister_reports" /></p>
	</form>
	';
	
	// set up pagination start
	$limit = 25;
	$start = findStart( $limit );
	$end = $start + $limit;
	
	// search plugin registrations
	$sql = "select SQL_CALC_FOUND_ROWS plugin, pluginversion as version, sitename, url, time
			from " . $wpdb->prefix . "plugin_register
			where ('" . mysql_real_escape_string( $pluginq ) . "' = '' or plugin like '%" . mysql_real_escape_string( $pluginq ) . "%')
			and ('" . mysql_real_escape_string( $versionq ) . "' = '' or pluginversion like '%" . mysql_real_escape_string( $versionq ) . "%')
			and ('" . mysql_real_escape_string( $siteq ) . "' = '' or sitename like '%" . mysql_real_escape_string( $siteq ) . "%')
			and ('" . mysql_real_escape_string( $siteq ) . "' = '' or url like '%" . mysql_real_escape_string( $siteq ) . "%')
			order by time desc
			limit " . mysql_real_escape_string( $start ) . ", " . mysql_real_escape_string( $limit ) . ";";

	// get the results
	$results = $wpdb->get_results( $sql );
	
	if( $results && is_array( $results ) && count( $results ) > 0 ) {
	
		// get the total number of rows
		$count = $wpdb->get_var( "SELECT FOUND_ROWS();" );
		
		if ( $count < $limit ) {
			$view = $count;
		} else {
			$view = ( $start+1 ) . '&ndash;' . $end;
		}

		// get the pages
		$pages = findPages($count, $limit);
		
		// set up the pagination links
		$pagelist = paginate_links( array(
			'base' => add_query_arg( 'paged', '%#%' ),
			'format' => '',
			'prev_text' => __('&laquo;'),
			'next_text' => __('&raquo;'),
			'total' => $pages,
			'current' => $_GET['paged']
		));
	
		echo '
		<div class="tablenav">
		<div class="tablenav-pages">
		<span class="displaying-num">Displaying ' . $view . ' of ' . $count . '</span>
			' . $pagelist . '
		</div>
		</div>
		<table class="widefat fixed" cellspacing="0">

		<thead>
		<tr class="thead">
			<th>' . __( "Plugin", "pluginregister" ) . '</th>
			<th>' . __( "Version", "pluginregister" ) . '</th>
			<th>' . __( "Site", "pluginregister" ) . '</th>
			<th>' . __( "Registration date", "pluginregister" ) . '</th>
		</tr>
		</thead>

		<tbody>
		';
		foreach ( $results as $result ) {
			echo '
			<tr>
				<td><a href="plugins.php?page=pluginregister_reports&amp;plugin=' . urlencode( $result->plugin ) . '&amp;version=' . urlencode( $result->version ) . '">' . $result->plugin . '</a></td>
				<td>' . $result->version . '</td>
				<td><a href="plugins.php?page=pluginregister_reports&amp;url=' . urlencode( $result->url ) . '">' . $result->sitename . '</a></td>
				<td><a href="plugins.php?page=pluginregister_reports&amp;date=' . date( "Y/n/j", $result->time) . '">' . date( "F j, Y, g:i a", $result->time ) . '</a></td>
			</tr>
			';
		}
		echo '
		</tbody>
		</table>
		<div class="tablenav">
		<div class="tablenav-pages">
		<span class="displaying-num">Displaying ' . $view . ' of ' . $count . '</span>
			' . $pagelist . '
		</div>
		</div>
		';
	} else {
		echo '
		<p>' . __( "No results found for your search", "pluginregister" ) . '</p>
		';
	}
}

// show the site URL report
function pluginregister_url_report() {

	global $wpdb;
	$url = urldecode( $_GET["url"] );
	
	// set up pagination start
	$limit = 25;
	$start = findStart( $limit );
	$end = $start + $limit;
	
	$sql = $wpdb->prepare( "select id, plugin, pluginversion as version, sitename, url, time
							from " . $wpdb->prefix . "plugin_register
							where url = %s
							order by time desc
							limit %d, %d;",
							$url,
							$start,
							$limit );
	
	// get the results
	$results = $wpdb->get_results( $sql );
	
	if( $results && is_array( $results ) && count( $results ) > 0 ) {
	
		echo '
		<h2><a href="plugins.php?page=pluginregister_reports">' . __( "Plugin Register", "pluginregister" ) . '</a>: <a href="' . $url . '">' . $results[0]->sitename . '</a></h2>
		';
	
		// get the total number of rows
		$count = $wpdb->get_var( "SELECT FOUND_ROWS();" );
		
		if ( $count < $limit ) {
			$view = $count;
		} else {
			$view = ( $start+1 ) . '&ndash;' . $end;
		}

		// get the pages
		$pages = findPages($count, $limit);
		
		// set up the pagination links
		$pagelist = paginate_links( array(
			'base' => add_query_arg( 'paged', '%#%' ),
			'format' => '',
			'prev_text' => __('&laquo;'),
			'next_text' => __('&raquo;'),
			'total' => $pages,
			'current' => $_GET['paged']
		));
	
		echo '
		<div class="tablenav">
		<div class="tablenav-pages">
		<span class="displaying-num">Displaying ' . $view . ' of ' . $count . '</span>
			' . $pagelist . '
		</div>
		</div>
		<table class="widefat fixed" cellspacing="0">

		<thead>
		<tr class="thead">
			<th>' . __( "Plugin", "pluginregister" ) . '</th>
			<th>' . __( "Version", "pluginregister" ) . '</th>
			<th>' . __( "Registration date", "pluginregister" ) . '</th>
			<th>' . __( "Delete registration", "pluginregister" ) . '</th>
		</tr>
		</thead>

		<tbody>
		';
		foreach ( $results as $result ) {
			echo '
			<tr>
				<td><a href="plugins.php?page=pluginregister_reports&amp;plugin=' . urlencode( $result->plugin ) . '">' . $result->plugin . '</a></td>
				<td><a href="plugins.php?page=pluginregister_reports&amp;plugin=' . urlencode( $result->plugin ) . '&amp;version=' . urlencode( $result->version ) . '">' . $result->version . '</a></td>
				<td><a href="plugins.php?page=pluginregister_reports&amp;date=' . date( "Y/n/j", $result->time) . '">' . date( "F j, Y, g:i a", $result->time ) . '</a></td>
				<td><a href="plugins.php?page=pluginregister_reports&amp;url=' . $_GET["url"] . '&amp;deleteregistration=' . $result->id . '" class="button">' . __( "Delete registration", "pluginregister" ) . '</a></td>
			</tr>
			';
		}
		echo '
		</tbody>
		</table>
		<div class="tablenav">
		<div class="tablenav-pages">
		<span class="displaying-num">Displaying ' . $view . ' of ' . $count . '</span>
			' . $pagelist . '
		</div>
		</div>
		';
	} else {
		echo '

		<h2><a href="plugins.php?page=pluginregister_reports">' . __( "Plugin Register", "pluginregister" ) . '</a>: ' . $url . '</h2>

		<p>' . __( "No registrations found for this site URL", "pluginregister" ) . '</p>
		';
	}

}

// show the date report
function pluginregister_date_report() {

	global $wpdb;
	$date = $_GET["date"];
	$dateparts = explode( "/", $date );
	
	echo '
	<h2><a href="plugins.php?page=pluginregister_reports">' . __( "Plugin Register", "pluginregister" ) . '</a>: ' . $date . '</h2>
	';
	
	// set up pagination start
	$limit = 25;
	$start = findStart( $limit );
	$end = $start + $limit;
	
	$sql = $wpdb->prepare( "select plugin, pluginversion as version, sitename, url, time
							from " . $wpdb->prefix . "plugin_register
							where year(FROM_UNIXTIME(time)) = %s
							and month(FROM_UNIXTIME(time)) = %s
							and day(FROM_UNIXTIME(time)) = %s
							order by time desc
							limit %d, %d;",
							$dateparts[0],
							$dateparts[1],
							$dateparts[2],
							$start,
							$limit );
	
	// get the results
	$results = $wpdb->get_results( $sql );
	
	if( $results && is_array( $results ) && count( $results ) > 0 ) {
	
		// get the total number of rows
		$count = $wpdb->get_var( "SELECT FOUND_ROWS();" );
		
		if ( $count < $limit ) {
			$view = $count;
		} else {
			$view = ( $start+1 ) . '&ndash;' . $end;
		}

		// get the pages
		$pages = findPages($count, $limit);
		
		// set up the pagination links
		$pagelist = paginate_links( array(
			'base' => add_query_arg( 'paged', '%#%' ),
			'format' => '',
			'prev_text' => __('&laquo;'),
			'next_text' => __('&raquo;'),
			'total' => $pages,
			'current' => $_GET['paged']
		));
	
		echo '
		<div class="tablenav">
		<div class="tablenav-pages">
		<span class="displaying-num">Displaying ' . $view . ' of ' . $count . '</span>
			' . $pagelist . '
		</div>
		</div>
		<table class="widefat fixed" cellspacing="0">

		<thead>
		<tr class="thead">
			<th>' . __( "Plugin", "pluginregister" ) . '</th>
			<th>' . __( "Version", "pluginregister" ) . '</th>
			<th>' . __( "Registration date", "pluginregister" ) . '</th>
		</tr>
		</thead>

		<tbody>
		';
		foreach ( $results as $result ) {
			echo '
			<tr>
				<td><a href="plugins.php?page=pluginregister_reports&amp;plugin=' . urlencode( $result->plugin ) . '">' . $result->plugin . '</a></td>
				<td><a href="plugins.php?page=pluginregister_reports&amp;plugin=' . urlencode( $result->plugin ) . '&amp;version=' . urlencode( $result->version ) . '">' . $result->version . '</a></td>
				<td>' . date( "F j, Y, g:i a", $result->time ) . '</td>
			</tr>
			';
		}
		echo '
		</tbody>
		</table>
		<div class="tablenav">
		<div class="tablenav-pages">
		<span class="displaying-num">Displaying ' . $view . ' of ' . $count . '</span>
			' . $pagelist . '
		</div>
		</div>
		';
	} else {
		echo '
		<p>' . __( "No registrations found on this day", "pluginregister" ) . '</p>
		';
	}

}

// pagngation functions
// Originally from http://www.onextrapixel.com/2009/06/22/how-to-add-pagination-into-list-of-records-or-wordpress-plugin/
if ( !function_exists( "findStart" ) ) {
	function findStart($limit) {
		if ((!isset($_GET['paged'])) || ($_GET['paged'] == "1")) {
	    	$start = 0;
	    	$_GET['paged'] = 1;
	    } else {
	       	$start = ($_GET['paged']-1) * $limit;
	    }
		return $start;
	}

	  /*
	   * int findPages (int count, int limit)
	   * Returns the number of pages needed based on a count and a limit
	   */
	function findPages($count, $limit) {
	     $pages = (($count % $limit) == 0) ? $count / $limit : floor($count / $limit) + 1; 

	     return $pages;
	} 

	/*
	* string pageList (int curpage, int pages)
	* Returns a list of pages in the format of "« < [pages] > »"
	**/
	function pageList($curpage, $pages, $count, $limit)
	{
		$qs = preg_replace("&p=([0-9]+)", "", $_SERVER['QUERY_STRING']);
		$start = findStart($limit);
		$end = $start + $limit;
		$page_list  = "<span class=\"displaying-num\">Displaying " . ( $start + 1 ). "&#8211;" . $end . " of " . $count . "</span>\n"; 

	    /* Print the first and previous page links if necessary */
	    if (($curpage != 1) && ($curpage)) {
	       $page_list .= "<a href=\"".$_SERVER['PHP_SELF']."?".$qs."&amp;p=1\" class=\"page-numbers\">&laquo;</a>\n";
	    } 

	    if (($curpage-1) > 0) {
	       $page_list .= "<a href=\"".$_SERVER['PHP_SELF']."?".$qs."&amp;p=".($curpage-1)."\" class=\"page-numbers\">&lt;</a>\n";
	    } 

	    /* Print the numeric page list; make the current page unlinked and bold */
	    for ($i=1; $i<=$pages; $i++) {
	    	if ($i == $curpage) {
	         	$page_list .= "<span class=\"page-numbers current\">".$i."</span>";
	        } else {
	         	$page_list .= "<a href=\"".$_SERVER['PHP_SELF']."?".$qs."&amp;p=".$i."\" class=\"page-numbers\">".$i."</a>\n";
	        }
	       	$page_list .= " ";
	      } 

	     /* Print the Next and Last page links if necessary */
	     if (($curpage+1) <= $pages) {
	       	$page_list .= "<a href=\"".$_SERVER['PHP_SELF']."?".$qs."&amp;p=".($curpage+1)."\" class=\"page-numbers\">&gt;</a>\n";
	     } 

	     if (($curpage != $pages) && ($pages != 0)) {
	       	$page_list .= "<a href=\"".$_SERVER['PHP_SELF']."?".$qs."&amp;p=".$pages."\" class=\"page-numbers\">&raquo;</a>\n";
	     }
	     $page_list .= "\n"; 

	     return $page_list;
	}

	/*
	* string nextPrev (int curpage, int pages)
	* Returns "Previous | Next" string for individual pagination (it's a word!)
	*/
	function nextPrev($curpage, $pages) {
	 $next_prev  = ""; 

		if (($curpage-1) <= 0) {
	   		$next_prev .= "Previous";
		} else {
	   		$next_prev .= "<a href=\"".$_SERVER['PHP_SELF']."?".$_SERVER['QUERY_STRING']."&amp;p=".($curpage-1)."\" class='page-numbers'>Previous</a>";
		} 

	 		$next_prev .= " | "; 

	 	if (($curpage+1) > $pages) {
	   		$next_prev .= "Next";
	    } else {
	       	$next_prev .= "<a href=\"".$_SERVER['PHP_SELF']."?".$_SERVER['QUERY_STRING']."&amp;p=".($curpage+1)."\" class='page-numbers'>Next</a>";
	    }
		return $next_prev;
	}
}

// ==========================================================================================
// service functions

function pluginregister_service() {

	if ( isset( $_GET["plugin"] ) && isset( $_GET["version"] ) && isset( $_GET["site"] ) && isset( $_GET["url"] ) ) {
	
		global $wpdb;
		
		$sql = $wpdb->prepare( "insert into " . $wpdb->prefix . "plugin_register
								(time, plugin, pluginversion, sitename, url)
								values
								(%d, %s, %s, %s, %s);",
								time(),
								trim( urldecode( $_GET["plugin"] ) ),
								trim( urldecode( $_GET["version"] ) ),
								trim( urldecode( $_GET["site"] ) ),
								trim( urldecode( $_GET["url"] ) ) );
		if ( $wpdb->query( $sql ) ) {
		
			echo 'Plugin registered';
			exit();
		
		} else {

			echo 'Plugin not registered';
			exit();
		
		}
	}
}
?>