<?php
/**
 * @package Plugin Register
 * @author Chris Taylor
 * @version 0.1
 */
/*
Plugin Name: Plugin Register
Plugin URI: http://www.stillbreathing.co.uk/projects/plugin-register/
Description: This is a plugin for plugin developers only. Plugin Register allows you to keep track of what version of your plugins are being installed. By registering a function to be run on activation of your plugin, a call is made to this plugin which stores details the site which is installing your plugin, which plugin is being installed, and the plugin version. Some reports are available so you can see what versions are installed.
Author: Chris Taylor
Version: 0.1
Author URI: http://www.stillbreathing.co.uk/
*/

// set the current version
function pluginregister_current_version() {
	return "0.1";
}

// ==========================================================================================
// service calling function

register_activation_hook( __FILE__, pluginregister_plugin_register );
function pluginregister_plugin_register() {
	$plugin = "Plugin Register";
	$version = "0.1";
	$site = get_option( "blogname" );
	$url = get_option( "siteurl" );
	$register_url = "http://www.stillbreathing.co.uk/?plugin=" . urlencode( $plugin ) . "&version=" . urlencode( $version ) . "&site=" . urlencode( $site ) . "&url=" . urlencode( $url );
	wp_remote_fopen( $register_url );
}

// ==========================================================================================
// hooks

// set activation hooks
register_activation_hook( __FILE__, pluginregister_activate );

// initialise the plugin
pluginregister_init();

// ==========================================================================================
// initialisation functions

function pluginregister_init() {
	if ( function_exists( "add_action" ) ) {
		add_action( "template_redirect", "pluginregister_service" );
		add_action( "admin_menu", "pluginregister_admin_menu" );
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

// add the admin menu page
function pluginregister_admin_menu() {

	add_submenu_page('plugins.php', __( "Plugin Register", "pluginregister" ), __( "Plugin Register", "pluginregister" ), 10, 'pluginregister_reports', 'pluginregister_reports');
	
}

/// show the reports
function pluginregister_reports() {

	global $wpdb;
	
	require_once( "pager.php" );

	echo '
	<div class="wrap">
	<div id="icon-plugins" class="icon32"><br /></div>
	';
	
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
	</form>
	
	
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
	
	// set up pagination start
	$limit = 25;
	$start = findStart( $limit );
	$end = $start + $limit;
	
	// get the unique sites registered
	$sql = $wpdb->prepare( "select SQL_CALC_FOUND_ROWS sitename, url,
			count(id) as registrations
			from " . $wpdb->prefix . "plugin_register
			group by url 
			order by url
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
	
		echo '
		<h3 style="padding-top:2em">' . __( "Registered sites", "pluginregister" ) . '</h3>
		
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
			<th>' . __( "Registrations", "pluginregister" ) . '</th>
		</tr>
		</thead>

		<tbody>
		';
		foreach ( $sites as $site ) {
			echo '
			<tr>
				<td><a href="plugins.php?page=pluginregister_reports&amp;url=' . urlencode( $site->url ) . '">' . $site->url . '</a></td>
				<td><a href="' . $site->url . '">' . $site->url . '</a></td>
				<td>' . $site->registrations . '</td>
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
	
	echo '
	<h3 style="padding-top:2em">' . __( "Using Plugin Register in your plugins", "pluginregister" ) . '</h3>
	<p>' . __( "To use Plugin Register in your plugins ensure you include this code, replacing the [PLACEHOLDER TEXT] with the details of your plugin:", "pluginregister" ) . '</p>
	
	<textarea rows="11" cols="50" style="width:95%;font-family:monospace">register_activation_hook( __FILE__, "[YOUR UNIQUE PLUGIN SLUG]_plugin_register" );
function [YOUR UNIQUE PLUGIN SLUG]_plugin_register() {
	$plugin = "[YOUR PLUGIN NAME]";
	$version = "[YOUR PLUGIN VERSION]";
	$site = get_option( "blogname" );
	$url = get_option( "siteurl" );
	$register_url = "' . get_option( "siteurl" ) . '/?plugin=" . urlencode( $plugin ) . "&version=" . urlencode( $version ) . "&site=" . urlencode( $site ) . "&url=" . urlencode( $url );
	wp_remote_fopen( $register_url );
}</textarea>
';

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
	
	$sql = $wpdb->prepare( "select SQL_CALC_FOUND_ROWS plugin, pluginversion as version, sitename, url, time
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
	$versionq = @$_GET["pluginq"];
	
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
	$sql = $wpdb->prepare( "select SQL_CALC_FOUND_ROWS plugin, pluginversion as version, sitename, url, time
			from " . $wpdb->prefix . "plugin_register
			where ('" . mysql_real_escape_string( $pluginq ) . "' = '' or plugin like '%" . mysql_real_escape_string( $pluginq ) . "%')
			or ('" . mysql_real_escape_string( $versionq ) . "' = '' or pluginversion like '%" . mysql_real_escape_string( $versionq ) . "%')
			or ('" . mysql_real_escape_string( $siteq ) . "' = '' or sitename like '%" . mysql_real_escape_string( $siteq ) . "%')
			or ('" . mysql_real_escape_string( $siteq ) . "' = '' or url like '%" . mysql_real_escape_string( $siteq ) . "%')
			order by time desc
			limit %d, %d;",
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
	
	$sql = $wpdb->prepare( "select plugin, pluginversion as version, sitename, url, time
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