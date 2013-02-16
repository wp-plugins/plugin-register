<?php
/**
 * @package Plugin Register
 * @author Chris Taylor
 * @version 0.6.1
 */
/*
Plugin Name: Plugin Register
Plugin URI: http://www.stillbreathing.co.uk/wordpress/plugin-register/
Description: This is a plugin for plugin developers only. Plugin Register allows you to keep track of what version of your plugins are being installed. By registering a function to be run on activation of your plugin, a call is made to this plugin which stores details the site which is installing your plugin, which plugin is being installed, and the plugin version. Some reports are available so you can see what versions are installed.
Author: Chris Taylor
Version: 0.6.1
Author URI: http://www.stillbreathing.co.uk/
*/

// set the current version
function pluginregister_current_version() {
	return "0.6.1";
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
		add_filter( "cron_schedules", "pluginregister_cron_add_weekly" );
		
		// disabled as it caused out of memory exceptions on my site
		//pluginregister_initialise_notifications();
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
		$seconds = 604800;
		global $wpdb;

		// set up pagination start
		$limit = 10;
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
		
			echo '
			<p>' . sprintf( __( 'New registered sites in the last %d days. <a href="plugins.php?page=pluginregister_reports">View full reports</a>.', "pluginregister" ), $days ) . '</p>
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
					<td>
						<a href="plugins.php?page=pluginregister_reports&amp;url=' . urlencode( $site->url ) . '">';
						if ( $site->sitename == "" ) {
							echo __( "(no name)", "pluginregister" );
						} else {
							echo $site->sitename;
						}
				echo '
						</a></td>
					<td><a href="' . $site->url . '">' . $site->url . '</a></td>
				</tr>
				<tr>
					<td colspan="2"><span style="font-style: italic">';
					if ( is_array( $plugins ) && count( $plugins ) > 0) {
						$list = "";
						foreach( $plugins as $plugin ) {
							$list .= $plugin->plugin . ", ";
						}
						echo trim( trim( $list ), "," );
					}
					echo '</span></td>
				</tr>';
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
	
	if ( !isset( $_GET["plugin"] ) && !isset( $_GET["version"] ) && !isset( $_GET["siteq"] ) && !isset( $_GET["pluginq"] ) && !isset( $_GET["versionq"] ) && !isset( $_GET["url"] ) && !isset( $_GET["date"] ) && !isset( $_GET["screen"] ) && !isset( $_GET["list"] ) ) {
	
		pluginregister_main_report();

	} else if ( isset( $_GET["screen"] ) ) {
		
		if ( $_GET["screen"] == "settings" ){
			
			if ( isset( $_GET["testemail"] ) ) {
				$data = pluginregister_get_settings();
				pluginregister_send_notification_emails( $data["schedule"] );
			}
			
			pluginregister_settings_screen();
			
		}
		
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
	
	} else if ( isset( $_GET["list"] ) ) {
	
		if ( $_GET["list"] == "sites" ) {
	
			pluginregister_sites_report();
		
		}
		
	}
	
	echo '	
	</div>
	';

}

// add the CSS and JavaScript for the reports
function pluginregister_admin_head()
{
	echo '
	<link rel="stylesheet" href="' . plugins_url( 'plugin-register.css' , __FILE__ ) . '" type="text/css" media="all" />
	<script type="text/javascript" src="' . plugins_url( 'plugin-register.js' , __FILE__ ) . '"></script>
	';
}
 
function pluginregister_cron_add_weekly( $schedules ) {
	// Adds once weekly to the existing schedules.
	$schedules['weekly'] = array(
		'interval' => 604800,
		'display' => __( 'Once Weekly' )
	);
	return $schedules;
}

// get the settings
function pluginregister_get_settings() {

	// set defaults
	$data = array(
		"enabled" => true,
		"schedule" => "daily"
	);
	
	// get the settings
	return maybe_unserialize( get_option( "pluginregister_settings", maybe_serialize( $data ) ) );
	
}

// initialise the notifications
function pluginregister_initialise_notifications() {

	// get settings
	$data = pluginregister_get_settings();
	
	// if enabled
	if ( $data["enabled"] ) {
	
		// schedule the next job
		if ( ! wp_next_scheduled( 'pluginregister_notify' ) ) {
			wp_schedule_event( time(), $data["schedule"], 'pluginregister_send_notification_emails', $data["schedule"] );
		}
		
	// not enabled
	} else {
	
		// cancel the next job
		$timestamp = wp_next_scheduled( 'pluginregister_notify' );
		wp_unschedule_event( $timestamp, 'pluginregister_notify', $data["schedule"] );
		
	}
}

// send the notification emails
function pluginregister_send_notification_emails( $schedule ) {

	$data = pluginregister_get_settings();
	
	$siteurl = get_admin_url();
	
	global $wpdb;

	$days = -1;
	if ( $schedule == "weekly" ) {
		$days = -7;
	}
	
	$start = pluginregister_addDayToDate( time(), $days );
	
	$sql = "select plugin, pluginversion as version, sitename, url, time
		from " . $wpdb->prefix . "plugin_register
		where time >= " . $start . "
		order by time;";
	
	// get the results
	$results = $wpdb->get_results( $sql );
	
	$html = '';
	
	if( $results && is_array( $results ) && count( $results ) > 0 ) {
	
		$html .= '
		<p>' . __( "Plugins registered:", "pluginregister" ) . ' ' . count( $results ) . '</p>

		<table>

		<thead>
		<tr>
			<th>' . __( "Plugin", "pluginregister" ) . '</th>
			<th>' . __( "Version", "pluginregister" ) . '</th>
			<th>' . __( "Site", "pluginregister" ) . '</th>
			<th>' . __( "URL", "pluginregister" ) . '</th>
			<th>' . __( "Registration date", "pluginregister" ) . '</th>
		</tr>
		</thead>

		<tbody>
		';
		foreach ( $results as $result ) {
			$html .= '
			<tr>
				<td><a href="' . $siteurl . 'plugins.php?page=pluginregister_reports&amp;plugin=' . urlencode( $result->plugin ) . '">' . $result->plugin . '</a></td>
				<td><a href="' . $siteurl . 'plugins.php?page=pluginregister_reports&amp;plugin=' . urlencode( $result->plugin ) . '&amp;version=' . urlencode( $result->version ) . '">' . $result->version . '</a></td>
				<td><a href="' . $siteurl . 'plugins.php?page=pluginregister_reports&amp;url=' . urlencode( $result->url ) . '">' . $result->sitename . '</a></td>
				<td><a href="' . $result->url . '">' . $result->url . '</a></td>
				<td><a href="' . $siteurl . 'plugins.php?page=pluginregister_reports&amp;date=' . date( "Y/n/j", $result->time) . '">' . date( "F j, Y, g:i a", $result->time ) . '</a></td>
			</tr>
			';
		}
		$html .= '
		</tbody>
		</table>
		';
	} else {
		$html .= '
		<p>' . __( "No registrations found in this period", "pluginregister" ) . '</p>
		';
	}
	
	add_filter( 'wp_mail_content_type', create_function( '', 'return "text/html";' ) );
	wp_mail( get_settings('admin_email'), __( "Plugin Register Registrations", "pluginregister" ), $html );
}

// show the main report
function pluginregister_main_report() {

	// the email feature isn't working properly
	// <a href="plugins.php?page=pluginregister_reports&amp;screen=settings" class="button">' . __( "Email alerts", "pluginregister" ) . '</a>

	global $wpdb;
	echo '
	<h2>
		' . __( "Plugin Register", "pluginregister" ) . '
	</h2>
	
	<form action="plugins.php" method="get">
		<p>
		' . __( "Site name/url", "pluginregister" ) . ' <input type="text" name="siteq" />
		' . __( "Plugin name", "pluginregister" ) . ' <input type="text" name="pluginq" />
		' . __( "Plugin version", "pluginregister" ) . ' <input type="text" name="versionq" style="width:6em" />
		<input type="submit" class="button" value="' . __( "Search plugin register", "pluginregister" ) . '" />
		<input type="hidden" name="page" value="pluginregister_reports" />
		<a href="plugins.php?page=pluginregister_reports&amp;list=sites" class="button">' . __( "Sites list", "pluginregister" ) . '</a>
		</p>
	</form>';
	
	// show date range reports
	echo '
	<h3>' . __("Date range reports") . '</h3>
	<ul class="inline">
		<li><a href="#range24hours" class="button rangebutton">' . __("Last 24 hours") . '</a></li>
		<li><a href="#range14days" class="button rangebutton">' . __("Last 14 days") . '</a></li>
		<li><a href="#range12weeks" class="button rangebutton">' . __("Last 12 weeks") . '</a></li>
		<li><a href="#range12months" class="button rangebutton">' . __("Last 12 months") . '</a></li>
	</ul>
	';
	pluginregister_24hour_report();
	pluginregister_14day_report();
	pluginregister_12week_report();
	pluginregister_12month_report();
	
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
	
	pluginregister_new_urls_report( 2592000 );
	
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

function pluginregister_settings_screen() {

	$data = pluginregister_get_settings();
	
	// save the settings
	if ( count( $_POST ) > 0 ) {
		if ( isset( $_POST["cron"] ) && $_POST["cron"] == "1" ) {
			$data["enabled"] = true;
		} else {
			$data["enabled"] = false;
		}
		if ( isset( $_POST["schedule"] ) ) {
			$data["schedule"] = $_POST["schedule"];
		}
		update_option( "pluginregister_settings", maybe_serialize( $data ) );
		
		echo '
		<div id="message" class="updated fade">
			<p><strong>' . __( 'The settings have been saved. <a href="plugins.php?page=pluginregister_reports&amp;screen=settings&amp;testemail=true">Test notification email</a> (will be sent to the site admin email address).', "pluginregister" ) . '</strong></p>
		</div>
		';
	}

	echo '
	<h2>
		' . __( "Plugin Register Settings", "pluginregister" ) . '
		<a href="plugins.php?page=pluginregister_reports" class="button">' . __( "Main report", "pluginregister" ) . '</a>
	</h2>
	
	<form action="plugins.php?page=pluginregister_reports&amp;screen=settings" method="post">
	
		<table class="form-table"><tbody>
			<tr valign="top">
				<th scope="row">
					<label for="cron">' . __( "Enable registration emails", "pluginregister" ) . '</label>
				</th>
				<td>
					<input name="cron" type="checkbox" id="cron" value="1"' . ( $data["enabled"] ? ' checked="checked"' : "" ) . '>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">
					<label for="schedule">' . __( "Email schedule", "pluginregister" ) . '</label>
				</th>
				<td>
					<select name="schedule" id="schedule">
						<option value="daily"' . ( $data["schedule"] == "daily" ? ' selected="selected"' : '' ) . '>' . __( "Daily", "pluginregister" ) . '</option>
						<option value="weekly"' . ( $data["schedule"] == "weekly" ? ' selected="selected"' : '' ) . '>' . __( "Weekly", "pluginregister" ) . '</option>
					</select>
				</td>
			</tr>
		</tbody></table>
		
		<p class="submit">
			<input type="submit" name="submit" id="submit" class="button button-primary" value="' . __( "Save Changes", "pluginregister" ) . '">
		</p>
		
	</form>
	';
}

function pluginregister_new_urls_report( $seconds = 604800 ) {
	global $wpdb;

	// set up pagination start
	$limit = 25;
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
	
		echo '
		<h3 style="padding-top:2em">' . sprintf( __( "New registered sites in the last %d days", "pluginregister" ), $days ) . '</h3>
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
			<th>' . __( "Registrations", "pluginregister" ) . '</th>
			<th>' . __( "Delete registrations", "pluginregister" ) . '</th>
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
				<td>
					<a href="plugins.php?page=pluginregister_reports&amp;url=' . urlencode( $site->url ) . '">';
						if ( $site->sitename == "" ) {
							echo __( "(no name)", "pluginregister" );
						} else {
							echo $site->sitename;
						}
				echo '
					</a></td>
				<td><a href="' . $site->url . '">' . $site->url . '</a></td>
				<td>' . date( "F j, Y, g:i a", $site->firstregistration ) . '</td>
				<td>' . $site->registrations . '</td>
				<td><a href="plugins.php?page=pluginregister_reports&amp;deletesite=' . urlencode( $site->url ) . '" class="button">' . __( "Delete registrations", "pluginregister" ) . '</a></td>
			</tr>
			<tr>
				<td colspan="5"><span style="font-style: italic">';
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

// get the last 24 hours fake SQL table
function pluginregister_last_24hours() {
	$time = strtotime( "-23 hour" );
	$sql = "select 0 as x, " . date( "H", $time ) . " as hour\n";
	for($i = 1; $i < 24; $i++)
	{
		$time = strtotime( "+1 hour", $time );
		$sql .= "union all select " . $i . ", " . date( "H", $time ) . "\n";
	}
	return $sql;
}

// show 24 hour report
function pluginregister_24hour_report($plugin = "", $version = "")
{
	global $wpdb;
	
	$sql = $wpdb->prepare("select hour, count(r.id) as registrations
			from (
			" . pluginregister_last_24hours() . "
			) as hours
			left outer
			join " . $wpdb->prefix . "plugin_register r
			on hour(FROM_UNIXTIME(r.time)) = hour
			and FROM_UNIXTIME(r.time) > date_add(now(), INTERVAL -24 HOUR)
			and (%s = '' or plugin = %s)
			and (%s = '' or pluginversion = %s)
			group by hour
			order by x",
			$plugin,
			$plugin,
			$version,
			$version);

	$hours = $wpdb->get_results( $sql );

	$registrationsmax = 0;
	
	echo '
	<div id="range24hours" class="rangereport">
	<h4>' . __("Plugin registrations in the last 24 hours") . '</h4>
	<table class="widefat post fixed">
		<thead>
		<tr>
			<th style="width:100px">' . __( "Hour", "pluginregister" ) . '</th>
		';
	foreach( $hours as $hour )
	{
		if ( $hour->registrations > $registrationsmax ) { $registrationsmax = $hour->registrations; }
	
		echo '
			<th>' . $hour->hour . '</th>
		';
	}
	echo '
		</tr>
		</thead>
		<tbody>
		<tr>
		<th style="width:100px">' . __("Registrations") . '</th>
		';
		
	foreach( $hours as $hour )
	{
		echo '
			<td>';
		if ( $hour->registrations != "0" && $registrationsmax != "0" )
		{
		echo '
			<div style="background:#6F6F6F;width:10px;height:' . ( round( ( $hour->registrations / $registrationsmax ) * 100 ) ) . 'px"></div>';
			}
		echo '
			' . $hour->registrations . '</td>
		';
	}
	echo '
		</tr>
		</tbody>
	</table>
	</div>
	';
}

// get the last 14 days fake SQL table
function pluginregister_last_14days() {
	$time = strtotime( "-13 day" );
	$sql = "select 0 as x, " . $time . " as date\n";
	for($i = 1; $i < 14; $i++)
	{
		$time = strtotime( "+1 day", $time );
		$sql .= "union all select " . $i . ", " . $time . "\n";
	}
	return $sql;
}

// show 14 day report
function pluginregister_14day_report($plugin = "", $version = "")
{

	global $wpdb;
	
	$sql = $wpdb->prepare("select date, count(r.id) as registrations
			from (
			" . pluginregister_last_14days() . "
			) as days
			left outer
			join " . $wpdb->prefix . "plugin_register r
			on day(FROM_UNIXTIME(time)) = day(FROM_UNIXTIME(date))
			and month(FROM_UNIXTIME(time)) = month(FROM_UNIXTIME(date))
			and year(FROM_UNIXTIME(time)) = year(FROM_UNIXTIME(date))
			and (%s = '' or plugin = %s)
			and (%s = '' or pluginversion = %s)
			group by date
			order by x",
			$plugin,
			$plugin,
			$version,
			$version);

	$days = $wpdb->get_results( $sql );

	$registrationsmax = 0;
	
	echo '
	<div id="range14days" class="rangereport">
	<h4>' . __("Plugin registrations in the last 14 days") . '</h4>
	<table class="widefat post fixed">
		<thead>
		<tr>
			<th style="width:100px">' . __( "Day", "pluginregister" ) . '</th>
		';
	$x = 0;
	foreach( $days as $day )
	{
		if ($day->registrations > $registrationsmax) { $registrationsmax = $day->registrations; }
	
		echo '
			<th>' . date( "d", $day->date );
			if ( $x == 0 || date( "d", $day->date ) == 1 ) {
				echo ' ' . date( "M", $day->date );
			}
		echo '</th>
		';
		$x++;
	}
	echo '
		</tr>
		</thead>
		<tbody>
		<tr>
		<th style="width:100px">' . __("Registrations") . '</th>
		';
		
	foreach( $days as $day )
	{
		echo '
			<td>';
		if ( $day->registrations != "0" && $registrationsmax != "0")
		{
		echo '
			<div style="background:#6F6F6F;width:10px;height:' . ( round( ( $day->registrations / $registrationsmax ) * 100 ) ) . 'px"></div>';
			}
		echo '
			' . $day->registrations . '</td>
		';
	}
	echo '
		</tr>
		</tbody>
	</table>
	</div>
	';
}

// get the last 12 weeks fake SQL table
function pluginregister_last_12weeks() {
	$time = strtotime( "-11 week" );
	$sql = "select 0 as x, " . $time . " as date\n";
	for($i = 1; $i < 12; $i++)
	{
		$time = strtotime( "+1 week", $time );
		$sql .= "union all select " . $i . ", " . $time . "\n";
	}
	return $sql;
}

// show 12 week report
function pluginregister_12week_report($plugin = "", $version = "")
{

	global $wpdb;
	
	$sql = $wpdb->prepare("select date, count(r.id) as registrations
			from (
			" . pluginregister_last_12weeks() . "
			) as days
			left outer
			join " . $wpdb->prefix . "plugin_register r
			on week(FROM_UNIXTIME(time)) = week(FROM_UNIXTIME(date))
			and year(FROM_UNIXTIME(time)) = year(FROM_UNIXTIME(date))
			and (%s = '' or plugin = %s)
			and (%s = '' or pluginversion = %s)
			group by date
			order by x",
			$plugin,
			$plugin,
			$version,
			$version);

	$weeks = $wpdb->get_results( $sql );
	
	echo '
	<div id="range12weeks" class="rangereport">
	<h4>' . __("Plugin registrations in the last 12 weeks") . '</h4>
	<table class="widefat post fixed">
		<thead>
		<tr>
			<th style="width:100px">' . __( "Week", "pluginregister" ) . '</th>
		';
	foreach( $weeks as $week )
	{
		if ( $week->registrations > $registrationsmax ) { $registrationsmax = $week->registrations; }
		echo '
			<th>' . date( "W", $week->date ) . '</th>
		';
	}
	echo '
		</tr>
		</thead>
		<tbody>
		<tr>
		<th style="width:100px">' . __("Registrations") . '</th>
		';
		
	foreach( $weeks as $week )
	{
		echo '
			<td>';
		if ( $week->registrations != "0" && $registrationsmax != "0" )
		{
		echo '
			<div style="background:#6F6F6F;width:10px;height:' . ( round( ( $week->registrations / $registrationsmax ) * 100 ) ) . 'px"></div>';
			}
		echo '
			' . $week->registrations . '</td>
		';
	}
	echo '
		</tr>
		</tbody>
	</table>
	</div>
	';
}

// get the last 12 months fake SQL table
function pluginregister_last_12months() {
	$time = strtotime( "-11 month" );
	$sql = "select 0 as x, " . $time . " as date\n";
	for($i = 1; $i < 12; $i++)
	{
		$time = strtotime( "+1 month", $time );
		$sql .= "union all select " . $i . ", " . $time . "\n";
	}
	return $sql;
}

// show 12 month report
function pluginregister_12month_report($plugin = "", $version = "")
{

	global $wpdb;
	
	$sql = $wpdb->prepare("select date, count(r.id) as registrations
			from (
			" . pluginregister_last_12months() . "
			) as days
			left outer
			join " . $wpdb->prefix . "plugin_register r
			on month(FROM_UNIXTIME(time)) = month(FROM_UNIXTIME(date))
			and year(FROM_UNIXTIME(time)) = year(FROM_UNIXTIME(date))
			and (%s = '' or plugin = %s)
			and (%s = '' or pluginversion = %s)
			group by date
			order by x",
			$plugin,
			$plugin,
			$version,
			$version);

	$months = $wpdb->get_results( $sql );
	
	echo '
	<div id="range12months" class="rangereport">
	<h4>' . __("Plugin registrations in the last 12 months") . '</h4>
	<table class="widefat post fixed">
		<thead>
		<tr>
			<th style="width:100px"></th>
		';
	foreach( $months as $month )
	{
		if ($month->registrations > $registrationsmax) { $registrationsmax = $month->registrations; }
		echo '
			<th>' . date( "n", $month->date ) . '</th>
		';
	}
	echo '
		</tr>
		</thead>
		<tbody>
		<tr>
		<th style="width:100px">' . __("Registrations") . '</th>
		';
		
	foreach( $months as $month )
	{
		echo '
			<td>';
		if ($month->registrations != "0" && $registrationsmax != "0")
		{
		echo '
			<div style="background:#6F6F6F;width:10px;height:' . ( round( ( $month->registrations / $registrationsmax ) * 100 ) ) . 'px"></div>';
			}
		echo '
			' . $month->registrations . '</td>
		';
	}
	echo '
		</tr>
		</tbody>
	</table>
	</div>
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
	<h2><a href="plugins.php?page=pluginregister_reports">' . __( "Plugin Register", "pluginregister" ) . '</a>: ' . $plugin . '</h2>';
	
	// show date range reports
	echo '
	<h3>' . __("Date range reports") . '</h3>
	<ul class="inline">
		<li><a href="#range24hours" class="button rangebutton">' . __("Last 24 hours") . '</a></li>
		<li><a href="#range14days" class="button rangebutton">' . __("Last 14 days") . '</a></li>
		<li><a href="#range12weeks" class="button rangebutton">' . __("Last 12 weeks") . '</a></li>
		<li><a href="#range12months" class="button rangebutton">' . __("Last 12 months") . '</a></li>
	</ul>
	';
	pluginregister_24hour_report( $plugin );
	pluginregister_14day_report( $plugin );
	pluginregister_12week_report( $plugin );
	pluginregister_12month_report($plugin );
	
	echo '
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
	
	// show date range reports
	echo '
	<h3>' . __("Date range reports") . '</h3>
	<ul class="inline">
		<li><a href="#range24hours" class="button rangebutton">' . __("Last 24 hours") . '</a></li>
		<li><a href="#range14days" class="button rangebutton">' . __("Last 14 days") . '</a></li>
		<li><a href="#range12weeks" class="button rangebutton">' . __("Last 12 weeks") . '</a></li>
		<li><a href="#range12months" class="button rangebutton">' . __("Last 12 months") . '</a></li>
	</ul>
	';
	pluginregister_24hour_report( $plugin, $version );
	pluginregister_14day_report( $plugin, $version );
	pluginregister_12week_report( $plugin, $version );
	pluginregister_12month_report($plugin, $version );
	
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
				<td>
					<a href="plugins.php?page=pluginregister_reports&amp;url=' . urlencode( $result->url ) . '">' . $result->sitename . '</a><br />
					<a href="' . $result->url . '">' . $result->url . '</a>
				</td>
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
	
	$sql = $wpdb->prepare( "select SQL_CALC_FOUND_ROWS id, plugin, pluginversion as version, sitename, url, time
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

// show the list of sites report
function pluginregister_sites_report() {

	global $wpdb;
	
	// set up pagination start
	$limit = 100;
	$start = findStart( $limit );
	$end = $start + $limit;
	
	$sql = $wpdb->prepare( "select SQL_CALC_FOUND_ROWS distinct sitename, url
							from " . $wpdb->prefix . "plugin_register
							order by url asc
							limit %d, %d;",
							$start,
							$limit );
	
	// get the results
	$results = $wpdb->get_results( $sql );
	
	if( $results && is_array( $results ) && count( $results ) > 0 ) {
	
		echo '
		<h2><a href="plugins.php?page=pluginregister_reports">' . __( "Plugin Register", "pluginregister" ) . '</a>: ' . __( "Sites list", "pluginregister" ) . '</h2>
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
			<th>' . __( "Site", "pluginregister" ) . '</th>
			<th>' . __( "URL", "pluginregister" ) . '</th>
		</tr>
		</thead>

		<tbody>
		';
		foreach ( $results as $result ) {
			echo '
			<tr>
				<td><a href="plugins.php?page=pluginregister_reports&amp;url=' . urlencode( $result->url ) . '">' . $result->sitename . '</a></td>
				<td><a href="' . $result->url . '">' . $result->url . '</a></td>
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
	
	$sql = $wpdb->prepare( "select SQL_CALC_FOUND_ROWS plugin, pluginversion as version, sitename, url, time
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
			<th>' . __( "Site", "pluginregister" ) . '</th>
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
				<td>
					<a href="plugins.php?page=pluginregister_reports&amp;url=' . urlencode( $result->url ) . '">' . $result->sitename . '</a><br />
					<a href="' . $result->url . '">' . $result->url . '</a>
				</td>
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