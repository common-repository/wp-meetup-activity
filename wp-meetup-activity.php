<?php
/*
Plugin Name: WP Meetup Activity
Plugin URI: http://www.zerozone.it/wordpress-meetup-plugin/
Description: Display group activity and events from Meetup.com in a widget for your wordpress
Version: 0.1.7
Author: Michele "O-Zone" Pinassi
Author URI: http://www.zerozone.it/

Copyright 2012-2014  Michele "O-Zone" Pinassi

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/* Meetup API */
include(dirname(__FILE__).DIRECTORY_SEPARATOR.'meetup_api/Meetup.php');

include('wp-meetup-activity-widgets.php');

add_option('wpmeetupactivity_apikey', '', '', 'yes');
add_option('wpmeetupactivity_events_desc', 'at %ADDRESS%. There are already %RSVP_YES% booked friends !', '', 'yes');
add_action('admin_menu', 'wpmeetupactivity_plugin_menu');
add_action('init', 'wpmeetupactivity_register_styles');

add_action( 'widgets_init', create_function( '', "register_widget('Meetup_Activity_Widget');" ) );
add_action( 'widgets_init', create_function( '', "register_widget('Meetup_Events_Widget');" ) );

register_activation_hook(__FILE__,'wpmeetupactivity_activate');
register_deactivation_hook( __FILE__,'wpmeetupactivity_deactivate');
register_uninstall_hook( __FILE__, 'wpmeetupactivity_uninstall');

global $wpdb;
global $current_site;

define(WP_MEETUP_ACTIVITY,'0.1.7');
define(WP_MEETUP_ACTIVITY_TABLE, $wpdb->prefix."meetup_activity");
define(WP_MEETUP_ACTIVITY_CRON_HOOK, "wpmeetupactivity_".$current_site->id);
define(WP_MEETUP_ACTIVITY_OLDERDAYS,7); /* Days for argument degree */

/*

CRON procedures that run every hours to fetch new activity

*/

add_action("wpmeetupactivity_".$current_site->id."_hourly", "wpmeetupactivity_plugin_cron_hourly");

function wpmeetupactivity_plugin_cron_hourly() {
    wpmeetupactivity_fetch_activity();
    wpmeetupactivity_fetch_events();
}

function wpmeetupactivity_install_cron() {
    global $current_site;
    if(!wp_next_scheduled(WP_MEETUP_ACTIVITY_CRON_HOOK.'_hourly')) {
	wp_schedule_event( current_time( 'timestamp' ), 'hourly', WP_MEETUP_ACTIVITY_CRON_HOOK.'_hourly');
	echo "<div class=\"updated\"><p><strong>"; 
	echo __('CRON Hourly updates scheduled !', $wpmeetupactivity_textdomain);
	echo "</strong></p></div>";
    }
}

function wpmeetupactivity_uninstall_cron() {

    wp_clear_scheduled_hook(WP_MEETUP_ACTIVITY_CRON_HOOK.'_hourly');

/*    $timestamp = wp_next_scheduled(WP_MEETUP_ACTIVITY_CRON_HOOK.'_hourly');
    while($timestamp) {
	wp_unschedule_event($timestamp, 'hourly', WP_MEETUP_ACTIVITY_CRON_HOOK.'_hourly');
	$timestamp = wp_next_scheduled(WP_MEETUP_ACTIVITY_CRON_HOOK.'_hourly');
    }

    wp_clear_scheduled_hook( WP_MEETUP_ACTIVITY_CRON_HOOK.'_hourly' );
*/
    echo "<div class=\"updated\"><p><strong>"; 
    echo __('CRON Hourly updates cleared !', $wpmeetupactivity_textdomain);
    echo "</strong></p></div>";
}

/*

wpmeetupactivity_activate() - Install plugin DATABASE 

*/
function wpmeetupactivity_activate() {
    wpmeetupactivity_install_cron();
    wpmeetupactivity_update_db_check();
}

function wpmeetupactivity_update_db_check() {
    global $wpdb;

    if (get_site_option('wpmeetupactivity_db_version') != WP_MEETUP_ACTIVITY) {
	$sql = "CREATE TABLE ".WP_MEETUP_ACTIVITY_TABLE."_act (
	id int(10) NOT NULL AUTO_INCREMENT,
	group_id int(12) NOT NULL,
	thread_id int(12) NOT NULL,
	message_id int(12) NOT NULL,
	author VARCHAR(32) NOT NULL,
	item_type VARCHAR(32) NOT NULL,
	item_title text NOT NULL,
	item_url text NOT NULL,
	ranking DECIMAL(5,3) NOT NULL,
	chg_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
	add_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
	UNIQUE KEY id (id)
	) DEFAULT CHARSET = utf8;

	CREATE TABLE ".WP_MEETUP_ACTIVITY_TABLE."_events (
	id int(10) NOT NULL AUTO_INCREMENT,
	group_id INT(12) NOT NULL,
	event_id INT(12) NOT NULL,
	event_title TEXT NOT NULL,
	event_address TEXT NOT NULL,
	event_description TEXT NOT NULL,
	event_datetime DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
	event_updated DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
	is_public SMALLINT(1) NOT NULL,
	event_status VARCHAR(16) NOT NULL,
	event_url TEXT NOT NULL,
	yes_rsvp INT(5) NOT NULL,
	add_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
	PRIMARY KEY id (id),
	UNIQUE KEY event_id (event_id)
	) DEFAULT CHARSET = utf8;

	CREATE TABLE ".WP_MEETUP_ACTIVITY_TABLE."_groups (
	group_id mediumint(10) NOT NULL,
	group_name text NOT NULL,
	add_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
	last_update datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
	UNIQUE KEY group_id (group_id)
	) DEFAULT CHARSET = utf8;";
	
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
	add_option("wpmeetupactivity_db_version", WP_MEETUP_ACTIVITY);
	
	echo "<div class=\"updated\"><p><strong>"; 
	echo __('Updated DB to '.WP_MEETUP_ACTIVITY, $wpmeetupactivity_textdomain);
	echo "</strong></p></div>";
    }
}

function wpmeetupactivity_deactivate() {
    wpmeetupactivity_uninstall_cron();
}

function wpmeetupactivity_uninstall() {
    global $wpdb;
    /* Perform a complete uninstall of DB tables on UNINSTALL */
    $wpdb->query($wpdb->prepare("DROP ".WP_MEETUP_ACTIVITY_TABLE."_groups; DROP ".WP_MEETUP_ACTIVITY_TABLE."_act;"));
    /* Disable CRON */
    wpmeetupactivity_uninstall_cron();
}

/*

wpmeetupactivity_fetch_groups() - Fetch groups list

*/

function wpmeetupactivity_fetch_groups() {
    global $wpdb;

    $groupsArray = array();

    $apikey = get_option('wpmeetupactivity_apikey');

    if($apikey) {
	// Preleva lista dei gruppi utente. Utilizza l'escamotage di guardare in quali gruppi c'è stata attività
	try {
    	    $connection = new MeetupKeyAuthConnection($apikey);
    	    $m = new MeetupFeeds($connection); 
    	    $activities = $m->getActivity();
	
	    foreach($activities as $act) {
		if(!in_array($act['group_id'],array_keys($groupsArray))) {
		    $groupsArray[$act['group_id']] = $act['group_name'];
		
		    $wpdb->query($wpdb->prepare("INSERT IGNORE INTO ".WP_MEETUP_ACTIVITY_TABLE."_groups (group_id, group_name, add_date) VALUES ( %d, %s, NOW())", $act['group_id'], $act['group_name']));
		}
	    }
	}  catch (Exception $e) {
    	    echo "Meetup API error: $e";
	}
    } else {
	echo "Set Meetup API key first !";
    }
}

/*

wpmeetupactivity_fetch_activity() - Fetch activities list

*/

function wpmeetupactivity_fetch_activity() {
    global $wpdb;

    $apikey = get_option('wpmeetupactivity_apikey');

    $groupsArray = get_option('wpmeetupactivity_groups');

    $filterBy = array('new_discussion','new_reply');

    if(count($groupsArray) > 0) {
	if($apikey) {
	    try {
		$connection = new MeetupKeyAuthConnection($apikey);
		$m = new MeetupFeeds($connection); 
		$activities = $m->getActivity();

		foreach($activities as $act) {
		    if(in_array($act["group_id"],$groupsArray)) {
			/* get activities only from subscribed groups */
			
			if(in_array($act["item_type"],$filterBy)) {
			    /* check if is an interesting activity... */
			    $groupId = $act["group_id"];
			    $messageId = $act["message_id"];
			    $threadId = $act["thread_id"];
			    
			    /* If thread_id is already present, check message_id for newest reply. Else add new thread_id row. */
			    $result = $wpdb->get_row("SELECT message_id,ranking,TIMESTAMPDIFF(HOUR,chg_date,NOW()) AS tdelta FROM ".WP_MEETUP_ACTIVITY_TABLE."_act  WHERE group_id=$groupId AND thread_id=$threadId;", ARRAY_A);
			    
			    if($result) {
				/* Check if there are news about this thread... */
				if(intval($messageId) > intval($result['message_id'])) {
				    /* Update ! */
				    $ranking = $result['ranking'];
				    $tdelta = $result['tdelta'];
				    if($tdelta > 0) {
					$ranking += (1/$tdelta);
				    }
				    
				    $wpdb->query($wpdb->prepare("UPDATE ".WP_MEETUP_ACTIVITY_TABLE."_act SET message_id=%d,author=%s,ranking=%f,item_type=%s,chg_date=NOW() WHERE group_id=$groupId AND thread_id=$threadId;",$messageId,$act['member_name'],$ranking,$act['item_type']));
								    
				    if(is_admin()) {
					echo "<div class=\"updated\"><p><strong>".__('Updated thread '.$act['discussion_title'], $wpmeetupactivity_textdomain)."</strong></p></div>";
				    }
				}
			    } else {
				/* Add new thread row - Ranking: 1 */
				$wpdb->query($wpdb->prepare("INSERT INTO ".WP_MEETUP_ACTIVITY_TABLE."_act (group_id, thread_id, message_id, author, item_type, item_title, item_url, ranking, chg_date, add_date) VALUES ( %d, %d, %d, %s, %s, %s, %s, 1, NOW(), NOW())", $groupId, $threadId, $messageId, $act['member_name'], $act['item_type'], $act['discussion_title'], $act['link']));

				if(is_admin()) {
				    echo "<div class=\"updated\"><p><strong>".__('Added new thread '.$act['discussion_title'], $wpmeetupactivity_textdomain)."</strong></p></div>";
				}
			    }
			}
			// Update LAST_UPDATE
		    }
	    	    $wpdb->query($wpdb->prepare("UPDATE ".WP_MEETUP_ACTIVITY_TABLE."_groups SET last_update=NOW() WHERE group_id='%d';",$act["group_id"]));
		}
		// Degrade ranking for older items
		$wpdb->query($wpdb->prepare("UPDATE ".WP_MEETUP_ACTIVITY_TABLE."_act SET ranking=ranking-0.1 WHERE DATEDIFF(NOW(),chg_date) > %d;",WP_MEETUP_ACTIVITY_OLDERDAYS));
	    }  catch (Exception $e) {
		echo "Meetup API error: $e";
	    }
	}
    }
}

function wpmeetupactivity_fetch_events($verbose=false) {
    global $wpdb;

    $apikey = get_option('wpmeetupactivity_apikey');

    $groupsArray = get_option('wpmeetupactivity_groups');

    if(count($groupsArray) > 0) {
	if($apikey) {
	    try {
		$connection = new MeetupKeyAuthConnection($apikey);
		$m = new MeetupEvents($connection); 
		foreach($groupsArray as $groupId) {
		    $events = $m->getEvents(array('group_id' => $groupId));

		    foreach($events as $event) {
			$eventId = $event['id'];
			
			$eventUpdated = gmdate("Y-m-d H:i:s",($event['updated']/1000));
			
			$eventDate = gmdate("Y-m-d H:i:s",($event['time']/1000));
			
			$eventVenue = $event['venue']['name'].','.$event['venue']['city'];

			/* If event_id is already present, check for update. Else add new event_id row. */
			$result = $wpdb->get_row("SELECT id,TIMESTAMPDIFF(MINUTE,event_updated,'$eventUpdated') AS tDelta FROM ".WP_MEETUP_ACTIVITY_TABLE."_events WHERE group_id='$groupId' AND event_id='$eventId';", ARRAY_A);
			
			if($result) {
			    // L'evento esiste gia
			    if($result['tDelta'] > 0) {
				$result = $wpdb->query( $wpdb->prepare( "UPDATE ".WP_MEETUP_ACTIVITY_TABLE."_events SET event_title='%s', event_address='%s', event_description='%s', event_datetime='%s',  event_updated='%s', event_status='%s', yes_rsvp=%d WHERE event_id=$eventId;", $event['name'],  $eventVenue,  $event['description'], $eventDate, $eventUpdated, $event['status'], intval($event['yes_rsvp_count'])));
				if($verbose) { 
				    echo "<div class=\"updated\"><p><strong>"; 
				    if($result) {
					echo __('Updated event '.$eventId.': '.$event['name'], $wpmeetupactivity_textdomain);
				    } else {
					echo __('DB SQL Error: '.$wpdb->print_error(), $wpmeetupactivity_textdomain);
				    }
				    echo "</strong></p></div>";
				}
			    }
			} else {
			    // L'evento non esiste: aggiungilo al DB !
			    $result = $wpdb->query( $wpdb->prepare("INSERT INTO ".WP_MEETUP_ACTIVITY_TABLE."_events (group_id, event_id, event_title, event_address, event_description, event_datetime, event_updated, is_public, event_status, event_url, yes_rsvp, add_date) VALUES (%d, %d, '%s', '%s', '%s', '%s', '%s', 1, '%s', '%s', %d, NOW())", $groupId, $eventId, $event['name'], $eventVenue, $event['description'], $eventDate, $eventUpdated, $event['status'], $event['event_url'], intval($event['yes_rsvp_count'])));
			    if($verbose) { 
				echo "<div class=\"updated\"><p><strong>"; 
				if($result) {
				    echo __('Added new event '.$eventId.': '.$event['name'], $wpmeetupactivity_textdomain);
				} else {
				    echo __('DB SQL Error: '.$wpdb->print_error(), $wpmeetupactivity_textdomain);
				}
				echo "</strong></p></div>";
			    }
			}
		    }
		}
	    } catch (Exception $e) {
		echo "Meetup API error: $e";
	    }
	}
    }
}


function wpmeetupactivity_plugin_menu() {
    add_options_page('WP-Meetup-Activity Plugin Options', 'WP-Meetup-Activity', 8, __FILE__, 'wpmeetupactivity_plugin_options');
}

function wpmeetupactivity_register_styles() {
    wp_register_style('wpmeetupactivity', plugins_url('default.css', __FILE__), array(), '20130215', 'all' );
    wp_enqueue_style('wpmeetupactivity');
}

function wpmeetupactivity_stripbbcode($text) {
    $text = preg_replace('~\[([^\]]+?)(=[^\]]+?)?\](.+?)\[/\1\]~', '', $text);
    return preg_replace('|[[\/\!]*?[^\[\]]*?]|si', '', $text); 
}

function wpmeetupactivity_get_plugin_dir($type='url') {
	if ( !defined('WP_CONTENT_URL') )
		define( 'WP_CONTENT_URL', get_option('siteurl') . '/wp-content');
	if ( !defined('WP_CONTENT_DIR') )
		define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
	if ($type=='path') { return WP_CONTENT_DIR.'/plugins/'.plugin_basename(dirname(__FILE__)); }
	else { return WP_CONTENT_URL.'/plugins/'.plugin_basename(dirname(__FILE__)); }
	
}

function wpmeetupactivity_widget_events($args) {
    global $wpdb;

    $monthArray = array('Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec');

    extract($args);

    $title = get_option('wpmeetupactivity_events_title');
    $desc = get_option('wpmeetupactivity_events_desc');

    $groupsArray = get_option('wpmeetupactivity_groups');
    $my_prefs = get_option('wpmeetupactivity_prefs');

    echo $before_widget;
    echo $before_title . $title . $after_title;

    if(count($groupsArray) > 0) {
	$events = $wpdb->get_results("SELECT * FROM ".WP_MEETUP_ACTIVITY_TABLE."_events WHERE DATEDIFF(event_datetime,NOW()) >= 0 ORDER BY event_datetime ASC LIMIT 3;",ARRAY_A);
	
	if($events) {
	    $c=0;
	    foreach($events as $event) {
		$eventUrl = $event['event_url'];
		$eventDate = date_parse($event['event_datetime']);
		$eventTitle = $event['event_title'];
		
		echo "<div class='wpmeetupactivity-event-widget-box'>
		    <div class='wpmeetupactivity-event-widget-cal-box'>
			<div class='wpmeetupactivity-event-widget-cal-box-day'>".$eventDate['day']."</div>
			<div class='wpmeetupactivity-event-widget-cal-box-month'>".$monthArray[$eventDate['month']-1]." ".$eventDate['year']."</div>
		    </div>
		    <div class='wpmeetupactivity-event-widget-detail-box'>
			<a href='$eventUrl' title='$eventTitle' alt='$eventTitle' target=_new'><b class='wpmeetupactivity-event-widget-box-title'>".((strlen($eventTitle) > 35) ? substr($eventTitle,0,32).'...' : $eventTitle)."</b></a>
			<p>";
		echo str_replace(array("%ADDRESS%","%RSVP_YES%"),array($event['event_address'],$event['yes_rsvp']),$desc);
		echo "	</p>
		    </div>
		</div>";
		$c = $c+1;
		if($c >= $my_prefs['displayEvents']) {
		    break;
		}
	    }
	} else {
	    echo "<ul><li>No events planned in your groups</li></ul>";
	}
    } else {
	echo "<b>No groups selected: did you set up the plugin ?</b>";
    }

    echo $after_widget;
}


function wpmeetupactivity_widget_activity($args) {
    global $wpdb;

    extract($args);

    $title = get_option('wpmeetupactivity_title');

    echo $before_widget;
    echo $before_title . $title . $after_title;
    
    $threadsArray = array();
    
    $groupsArray = get_option('wpmeetupactivity_groups');
    $my_prefs = get_option('wpmeetupactivity_prefs');
    
    $orderBy = $my_prefs['orderBy'];
    if(empty($orderBy)) {
	$orderBy = 'add_date';
    }
    
    if(count($groupsArray) > 0) {
	$activities = $wpdb->get_results("SELECT * FROM ".WP_MEETUP_ACTIVITY_TABLE."_act ORDER BY ".$orderBy." DESC;",ARRAY_A);
	
	if($activities) {
	    $disp_cnt = 1;
	    echo "<ul>";
	    foreach($activities as $act) {
		/*
		'group_id' => $act['group_id'], 
		'thread_id' => $act['thread_id'], 
		'author' => $act['member_name'], 
		'message_id' => $act['message_id'], 
		'item_type' => $act['item_type'],
		'item_title' => $act["discussion_title"],
		'item_url' => $act['link']
		'ranking' => 
		'add_date' =>
		'chg_date' =>
		*/
		$title = wpmeetupactivity_stripbbcode($act["item_title"]);
		echo "<li>";
		if($act['ranking'] > 1) { /* Hot topic ! */
		    echo "<img src='".wpmeetupactivity_get_plugin_dir()."/img/hot.png' class='wpmeetupactivity-icon'>";
		} else if($act['item_type'] == 'new_discussion') {
		    echo "<img src='".wpmeetupactivity_get_plugin_dir()."/img/new.png' class='wpmeetupactivity-icon'>";
		} else {
		    echo "<img src='".wpmeetupactivity_get_plugin_dir()."/img/reply.png' class='wpmeetupactivity-icon'>";
		}
		
		echo "<a href='".$act["item_url"]."'";
		if($my_prefs["openInNewWindow"]) {
		    echo " target='_new'";
		}
		echo ">$title</a>";
		if($my_prefs["displayAuthor"]) {
		    echo " by ".$act["author"];
		}
		if($my_prefs["displayDate"]) {
		    echo " on ".$act["add_date"];
		}
		echo "</li>";
			    
		$disp_cnt = $disp_cnt+1;
		if($disp_cnt > $my_prefs['displayAct']) {
		    break;
		}
	    }
	    echo "</ul>";
	} else {
	    echo "<ul><li>No activities in selected groups</li></ul>";
	}
    } else {
	echo "<b>No groups selected: did you set up the plugin ?</b>";
    }
    echo $after_widget;
}

function wpmeetupactivity_is_checked($value) {
    if($value) {
	return "checked";
    }
}

function wpmeetupactivity_is_selected($value_1,$value_2=true) {
    if($value_1 == $value_2) {
	return "selected";
    }
}

/*

WP-Meetup-Activity options page

*/
function wpmeetupactivity_plugin_options() {
    global $wpdb;

    load_plugin_textdomain($wpmeetupactivity_textdomain, PLUGINDIR . '/' . dirname(plugin_basename(__FILE__)), dirname(plugin_basename(__FILE__)));

    $myGroups = array();

    $my_prefs = get_option('wpmeetupactivity_prefs');
    if(!is_array($my_prefs)) {
	$my_prefs = array();
    }

    if($_SERVER['REQUEST_METHOD'] == 'POST') {

	if(is_array($_POST["wpmeetupactivity_groups"])) {
	    foreach($_POST["wpmeetupactivity_groups"] as $groupId => $val) {
    	        if($val == 'on') {
    		    $myGroups[] = $groupId;
    		}
    	    }
        }
        
        if($_POST["wpmeetupactivity_prefs_opennewwindows"] == 'on') {
    	    $my_prefs["openInNewWindow"] = true;
        } else {
    	    $my_prefs["openInNewWindow"] = false;
        }

        if($_POST["wpmeetupactivity_prefs_displayauthor"] == 'on') {
    	    $my_prefs["displayAuthor"] = true;
        } else {
    	    $my_prefs["displayAuthor"] = false;
        }
        
        if($_POST["wpmeetupactivity_prefs_displaydate"] == 'on') {
    	    $my_prefs["displayDate"] = true;
        } else {
    	    $my_prefs["displayDate"] = false;
        }

	$my_prefs["displayAct"] = intval($_POST["wpmeetupactivity_prefs_displayact"]);
	$my_prefs["displayEvents"] = intval($_POST["wpmeetupactivity_prefs_displayevents"]);
	$my_prefs["orderBy"] = intval($_POST["wpmeetupactivity_prefs_orderby"]);

	// Salva opzioni
	update_option('wpmeetupactivity_apikey',$_POST['wpmeetupactivity_apikey']);
	update_option('wpmeetupactivity_title',$_POST['wpmeetupactivity_activity_title']);
	update_option('wpmeetupactivity_events_title',$_POST['wpmeetupactivity_events_title']);
	update_option('wpmeetupactivity_events_desc',$_POST['wpmeetupactivity_events_desc']);
	update_option('wpmeetupactivity_groups',$myGroups);
	update_option('wpmeetupactivity_prefs',$my_prefs);

	// Saved !

	echo "<div class=\"updated\"><p><strong>"; 
	echo __('Options saved.', $wpmeetupactivity_textdomain);
	echo "</strong></p></div>";

	if($_POST["wpmeetupactivity_is_scheduled"] == 'on') {
	    wpmeetupactivity_install_cron();
	} else {
	    wpmeetupactivity_uninstall_cron();
	}

	if($_POST["wpmeetupactivity_purge_act_db"] == 'on') {
	    $wpdb->get_results("TRUNCATE ".WP_MEETUP_ACTIVITY_TABLE."_act;");

	    echo "<div class=\"updated\"><p><strong>"; 
	    echo __('Activities DB purged.', $wpmeetupactivity_textdomain);
	    echo "</strong></p></div>";

	    $wpdb->get_results("TRUNCATE ".WP_MEETUP_ACTIVITY_TABLE."_events;");

	    echo "<div class=\"updated\"><p><strong>"; 
	    echo __('Events DB purged.', $wpmeetupactivity_textdomain);
	    echo "</strong></p></div>";
	}
	
	if($_POST["wpmeetupactivity_force_act_fetch"] == 'on') {
	    wpmeetupactivity_fetch_activity();
	    echo "<div class=\"updated\"><p><strong>"; 
	    echo __('Activities fetched successfully.', $wpmeetupactivity_textdomain);
	    echo "</strong></p></div>";
	}

	if($_POST["wpmeetupactivity_force_event_fetch"] == 'on') {
	    wpmeetupactivity_fetch_events(true);
	    echo "<div class=\"updated\"><p><strong>"; 
	    echo __('Events fetched successfully.', $wpmeetupactivity_textdomain);
	    echo "</strong></p></div>";
	}
	
    }

    $apikey = get_option('wpmeetupactivity_apikey');
    $title = get_option('wpmeetupactivity_title');
    $title_events = get_option('wpmeetupactivity_events_title');
    $events_desc = get_option('wpmeetupactivity_events_desc');

    echo "<div class='wrap'><!-- WRAP -->
    <h2>WP-Meetup-Activity Setup</h2>
    <p>
	<b>v".WP_MEETUP_ACTIVITY." &copy; <a href=\"http://www.zerozone.it\">Michele \"O-Zone\" Pinassi</a></b>
    </p>
    <!-- Table prefix on DBMS ".WP_MEETUP_ACTIVITY_TABLE." -->";
    
    /* Check for CRON schedule */
    $is_scheduled = wp_get_schedule(WP_MEETUP_ACTIVITY_CRON_HOOK.'_hourly');
    if(!$is_scheduled) {
	echo "<div class=\"error\"><p><strong>"; 
	echo __('Cron schedule not set ! Your database will not be updated: please activate hourly updates below', $wpmeetupactivity_textdomain);
	echo "</strong></p></div>";
    }
    
    echo "<form method='post'>
	<fieldset class='wpmeetupactivity-setup-fieldset'><legend> Meetup Api Key </legend>
	    <p>
		API Key: <input type='text' size='64' name='wpmeetupactivity_apikey' value='$apikey' />
    	    </p>
    	    <p>
    		If you need to get a <b>Meetup Api Key</b> for your account visit <a href='http://www.meetup.com/meetup_api/key/'>www.meetup.com/meetup_api/key/</a>
    	    </p>
	</fieldset>";
    // Se API Key settata, preleva i gruppi e mostra l'elenco 
    if(isset($apikey)) {
	$myGroups = get_option('wpmeetupactivity_groups');
	
	wpmeetupactivity_fetch_groups();
	
	$groups = $wpdb->get_results("SELECT * FROM ".WP_MEETUP_ACTIVITY_TABLE."_groups ORDER BY group_id DESC LIMIT 10;",ARRAY_A);
		
	echo "<fieldset class='wpmeetupactivity-setup-fieldset'><legend> Available groups </legend>
	<p><ul>";
	foreach($groups as $group) {
	    echo "<li><input type='checkbox' name='wpmeetupactivity_groups[".$group['group_id']."]'";
	    if(in_array($group['group_id'],$myGroups)) {
	        echo " checked";
	    }
	    echo "> ".$group['group_name']." (".$group['last_update'].")</li>";
	}
    	echo "</ul>
    	</p></fieldset>
    	<fieldset class='wpmeetupactivity-setup-fieldset'><legend> Administrator functions </legend>
    	<p>
	    <input type='checkbox' name='wpmeetupactivity_is_scheduled' ".wpmeetupactivity_is_checked($is_scheduled)."> Set hourly updates using wp_cron
	</p>
    	<p>
	    <input type='checkbox' name='wpmeetupactivity_force_act_fetch'> Force activity fetch on save changes
	</p>
    	<p>
	    <input type='checkbox' name='wpmeetupactivity_force_event_fetch'> Force events fetch on save changes
	</p>
    	<p>
	    <input type='checkbox' name='wpmeetupactivity_purge_act_db'> Purge database
	</p>
	<p>
	    <i>TIP: Select all checkboxes to re-initialize activities and events db with new, fresh, data !</i>
	</p>
	</fieldset>";
    }
    echo "<fieldset class='wpmeetupactivity-setup-fieldset'><legend> Miscellaneous settings </legend>
    <p>
	Activity widget title: <input type='text' size='64' name='wpmeetupactivity_activity_title' value='$title' />
    </p><p>
	Events widget title: <input type='text' size='64' name='wpmeetupactivity_events_title' value='$title_events' />
    </p><p>
	<input type='checkbox' name='wpmeetupactivity_prefs_opennewwindows' ".wpmeetupactivity_is_checked($my_prefs["openInNewWindow"]).">  Open link in a new window
    </p><p>
	<input type='checkbox' name='wpmeetupactivity_prefs_displayauthor' ".wpmeetupactivity_is_checked($my_prefs["displayAuthor"])."> Display post author name
    </p><p>
	<input type='checkbox' name='wpmeetupactivity_prefs_displaydate' ".wpmeetupactivity_is_checked($my_prefs["displayDate"])."> Display post date
    </p><p>
	Max number of activities to show: <select name='wpmeetupactivity_prefs_displayact'>
	    <option value=2 ".wpmeetupactivity_is_selected($my_prefs["displayAct"],2).">2</option>
	    <option value=5 ".wpmeetupactivity_is_selected($my_prefs["displayAct"],5).">5</option>
	    <option value=10 ".wpmeetupactivity_is_selected($my_prefs["displayAct"],10).">10</option>
	    <option value=15 ".wpmeetupactivity_is_selected($my_prefs["displayAct"],15).">15</option>
	    <option value=20 ".wpmeetupactivity_is_selected($my_prefs["displayAct"],20).">20</option>
	</select>
    </p><p>
	Order activities by: <select name='wpmeetupactivity_prefs_orderby'>
	    <option value='add_date' ".wpmeetupactivity_is_selected($my_prefs["orderBy"],'add_date').">Add date</option>
	    <option value='chg_date' ".wpmeetupactivity_is_selected($my_prefs["orderBy"],'chg_date').">Change date</option>
	    <option value='ranking' ".wpmeetupactivity_is_selected($my_prefs["orderBy"],'ranking').">Ranking</option>
	</select>
    </p><p>
	Events widget description: <input type='text' size='128' name='wpmeetupactivity_events_desc' value='".((strlen($events_desc) > 0) ? $events_desc : "at %ADDRESS%. There are already %RSVP_YES% booked friends !")."' />
	<p class='wpmeetupactivity-tipbox'>You can use HTML tags and the following special variables: %ADDRESS%=Venue of the event | %RSVP_YES%=How many peoples will partecipate</p>
    </p><p>
	Max number of events to show: <select name='wpmeetupactivity_prefs_displayevents'>
	    <option value=1 ".wpmeetupactivity_is_selected($my_prefs["displayEvents"],1).">1</option>
	    <option value=2 ".wpmeetupactivity_is_selected($my_prefs["displayEvents"],2).">2</option>
	    <option value=3 ".wpmeetupactivity_is_selected($my_prefs["displayEvents"],3).">3</option>
	    <option value=4 ".wpmeetupactivity_is_selected($my_prefs["displayEvents"],4).">4</option>
	    <option value=5 ".wpmeetupactivity_is_selected($my_prefs["displayEvents"],5).">5</option>
	</select>
    </p>
    <p class='submit'><input type='submit' name='Submit' value='Save changes' /></p>
    </form></div><!-- /WRAP -->";
}

?>
