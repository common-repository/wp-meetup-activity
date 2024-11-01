<?php

/*
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


class Meetup_Activity_Widget extends WP_Widget {
	function Meetup_Activity_Widget() {
		$widget_ops = array( 'classname' => 'meetup-activity', 'description' => 'Display activities on your Meetups' );
		$this->WP_Widget( 'meetup_activity', 'Meetup Activity', $widget_ops );
	}

	function widget( $args, $instance ) {
		extract( $args, EXTR_SKIP );
		
		global $wpdb;

		echo $before_widget;
		
		$title = empty($instance['title']) ? '' : apply_filters('widget_title', $instance['title']);
		
		if ( !empty( $title ) ) { 
		    echo $before_title . $title . $after_title; 
		};
		
		$threadsArray = array();
    
		$groupsArray = get_option('wpmeetupactivity_groups');
		$my_prefs = get_option('wpmeetupactivity_prefs');

		$fb_options = get_option('wpmeetupactivity_fb_options');
		$fb_pages = $fb_options['pages'];
    
		$orderBy = $my_prefs['orderBy'];
		if(empty($orderBy)) {
		    $orderBy = 'add_date';
		}
    
		if(count($groupsArray) > 0) {
		    $activities = $wpdb->get_results("SELECT * FROM ".WP_MEETUP_ACTIVITY_TABLE."_act ORDER BY ".$orderBy." DESC;",ARRAY_A);
	
		    if($activities) {
			$disp_cnt = 1;
			echo "<ul class='wpmeetupactivity-widget-list'>";
			foreach($activities as $act) {
			    $title = wpmeetupactivity_stripbbcode($act["item_title"]);
			    echo "<li>";
			    if($act['ranking'] > 1) { /* Hot topic ! */
				echo "<img src='".wpmeetupactivity_get_plugin_dir()."/img/hot.png' class='wpmeetupactivity-icon'>";
			    } else if($act['item_type'] == 'new_discussion') {
				echo "<img src='".wpmeetupactivity_get_plugin_dir()."/img/new.png' class='wpmeetupactivity-icon'>";
			    } else {
				echo "<img src='".wpmeetupactivity_get_plugin_dir()."/img/reply.png' class='wpmeetupactivity-icon'>";
			    }
		
			    echo "<p><a href='".$act["item_url"]."'";
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
			    echo "</p></li>";
			    
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

    /** @see WP_Widget::update */
    function update($new_instance, $old_instance) {             
	$instance = $old_instance;
	$instance['title']  = strip_tags($new_instance['title']);
        return $instance;
    }

    /** @see WP_Widget::form */
    function form($instance) {
        $instance = wp_parse_args( (array) $instance, array( 
            'title' => 'Activities on my Meetups',
            ));
        $title  = strip_tags($instance['title']);
	echo "<p>
            <label for='".$this->get_field_id('title')."'>Widget Title:</label>
            <input class='widefat' id='".$this->get_field_id('title')."' name='".$this->get_field_name('title')."' type='text' value='".esc_attr($title)."' />
        </p>";
    }
}

class Meetup_Events_Widget extends WP_Widget {
	function Meetup_Events_Widget() {
		$widget_ops = array( 'classname' => 'meetup-events', 'description' => 'Display upcoming events in your Meetups' );
		$this->WP_Widget( 'meetup_events', 'Meetup Events', $widget_ops );
	}

	function widget( $args, $instance ) {
		extract( $args, EXTR_SKIP );
		
		global $wpdb;

		echo $before_widget;
		
		$title = empty($instance['title']) ? '' : apply_filters('widget_title', $instance['title']);
		
		if ( !empty( $title ) ) { 
		    echo $before_title . $title . $after_title; 
		};

		$monthArray = array('Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec');

		$title = get_option('wpmeetupactivity_events_title');
	        $desc = get_option('wpmeetupactivity_events_desc');

	        $groupsArray = get_option('wpmeetupactivity_groups');
		$my_prefs = get_option('wpmeetupactivity_prefs');


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
			    echo "</p>
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

    /** @see WP_Widget::update */
    function update($new_instance, $old_instance) {             
	$instance = $old_instance;
	$instance['title']  = strip_tags($new_instance['title']);
        return $instance;
    }

    /** @see WP_Widget::form */
    function form($instance) {
        $instance = wp_parse_args( (array) $instance, array( 
            'title' => 'Upcoming events',
        ));
        $title  = strip_tags($instance['title']);
	echo "<p>
            <label for='".$this->get_field_id('title')."'>Widget Title:</label>
            <input class='widefat' id='".$this->get_field_id('title')."' name='".$this->get_field_name('title')."' type='text' value='".esc_attr($title)."' />
        </p>";
    }
}
	