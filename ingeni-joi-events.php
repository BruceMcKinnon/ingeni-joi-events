<?php
/*
Plugin Name: Ingeni Joi Events
Version: 2020.01
Plugin URI: http://ingeni.net
Author: Bruce McKinnon - ingeni.net
Author URI: http://ingeni.net
Description: Get Joi event info
License: GPL v3

Ingeni Eventbrite
Copyright (C) 2020, Bruce McKinnon

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/


//
// v2020.01 - Initial release
//



define("SAVE_JOI_SETTINGS", "Save Settings...");
define("TEST_JOI_SETTINGS", "Test Connection...");
define("JOI_PRIVATE_TOKEN", "ingeni_joi_private_token");
define("JOI_API_URL", "ingeni_joi_api_url");

define("JOI_CACHE_TIMEOUT", "ingeni_joi_cache_timeout");
define("JOI_CACHED_EVENT_INFO", "ingeni_joi_event_info");
define("JOI_CACHED_PROGRAM_INFO", "ingeni_joi_program_info");
define("JOI_CACHED_SESSION_INFO", "ingeni_joi_session_info");
define("JOI_CACHED_TIMELINE", "ingeni_joi_timeline");

define("JOI_DEFAULT_URL","https://joi.land/json/publishedEvent/faf0694e");

require_once('ingeni-joi-events-api-class.php');
$ingeniJoiEventsApi;


//
// Main function for calling Joi, saving event data and managing cached data
//
function ingeni_joi_get_all_events() {
	global $ingeniJoiEventsApi;

	// If the cache is empty, go an get a live refresh
	if ( !$ingeniJoiEventsApi ) {
		$token = get_option(JOI_PRIVATE_TOKEN);
		$url = get_option(JOI_API_URL, JOI_DEFAULT_URL);
		$ingeniJoiEventsApi = new IngeniJoiEventsApi( $url, $token );
	}

	$cache_expiry = new DateTime("2020-01-01");
	$cache_expiry = get_option(JOI_CACHE_TIMEOUT, $cache_expiry);

	if ( $cache_expiry < new DateTime("now") ) {
		// Time to clear the cache
$ingeniJoiEventsApi->fb_log("clearing cache");
		ingeni_joi_clear_cache();

		$json = $ingeniJoiEventsApi->get_joi_events(false,$errMsg);

		if ( ( !empty($json) ) && ( strlen($errMsg) == 0 ) ) {

$ingeniJoiEventsApi->fb_log(print_r($json,true));

			// It worked! Now save the eventInfo, programInfo and sessionInfo into seperate structures in the DB
			update_option(JOI_CACHED_EVENT_INFO, serialize($json['eventInfo']) );
			update_option(JOI_CACHED_SESSION_INFO, serialize($json['sessionInfo']) );
			update_option(JOI_CACHED_PROGRAM_INFO, serialize($json['programInfo']) );	
			update_option(JOI_CACHED_TIMELINE, serialize($json['timeline']) );			

			$new_expiry = new DateTime("now");
			$new_expiry->add(new DateInterval('PT1H'));
			update_option(JOI_CACHE_TIMEOUT, $new_expiry );		
		}



		


	}

	$timeline = unserialize( get_option(JOI_CACHED_TIMELINE) );
//$ingeniJoiEventsApi->fb_log(print_r($timeline[0],true));


	$allEventsHtml = "";
	$day_h3 = "";

	foreach($timeline as $day) {
		$dayHtml = '<div class="joi_day">';
		$day_h3 = '';

		foreach($day as $item) {
	//$ingeniJoiEventsApi->fb_log(print_r($item,true));
		//for ($idx = 0; $idx < count($timeline); ++$idx) {
			//$joi_event_ids .= '<p>'.$item->title." ".$item->date." ".$item->start." ".$item->end.'</p>';

			if (strlen($day_h3) == 0) {
				$day_h3 = '<h3>'.date("D d M Y",strtotime($item['session_date'])).'</h3>';
			}
			//$dayHtml .= '<p>Day '.$item['on_day']." ".$item['title']." ".$item['date']." ".$item['start']." ".$item['end'].'</p>';
		
			$dayHtml .= '<div class="row">';
				$dayHtml .= '<div class="cell small-2">'.date("g:i a",strtotime($item['start'])).'</div>';
				$dayHtml .= '<div class="cell small-2">'.date("g:i a",strtotime($item['end'])).'</div>';

				$label = '';
				if ( array_key_exists('labels',$item) ) {
					if (count($item['labels']) > 0) {
						$label = ingeni_joi_get_label($item['labels'][0]);
					}
					if (strlen($label) > 0) {
						$label .= ": ";
					}
				}

				$performer = '';
				if ( array_key_exists('performerIds',$item) ) {
					if (count($item['performerIds']) > 0) {
						$performer = ingeni_joi_get_performer($item['performerIds'][0]);
					}
					if (strlen($performer) > 0) {
						$performer .= " - ";
					}
				}
				$dayHtml .= '<div class="cell small-8 bold">'.$label.$performer.$item['title'].'</div>';

			$dayHtml .= '</div>';

		}
		$dayHtml .= '</div>';
		$allEventsHtml .= $day_h3 . $dayHtml;
		
	}

	return $allEventsHtml;
}


function ingeni_joi_get_performer( $uid ) {
	$json = unserialize( get_option( JOI_CACHED_EVENT_INFO ) );

	$performer_name = '';

	if ( array_key_exists( $uid, $json['performers'] ) ) {
		$performer_name = $json['performers'][$uid]['name'];
	}

	return trim($performer_name);
}


function ingeni_joi_get_label( $uid ) {
	$json = unserialize( get_option( JOI_CACHED_EVENT_INFO ) );

	$label = '';

	if ( array_key_exists( $uid, $json['session_labels'] ) ) {
		$label = $json['session_labels'][$uid]['label'];
	}

	return trim($label);
}


function get_single_event( $event_id ) {
	$retEvent = '';

	$json = get_option( JOI_CACHED_EVENT.$event_id );
	if ( !empty($json) ) {
		$retEvent = unserialize( $json );
	}

	return $retEvent;
}




// Wordpress shortcode - List all Joi Events
add_shortcode( 'joi-events-list-all', 'ingeni_joi_events_list_all' );

function ingeni_joi_events_list_all( $atts ) {
	$params = shortcode_atts( array(
		'class' => 'joi_events_wrapper',
	), $atts );


	$returnHtml = "";

	if ( strlen($params["class"]) > 0 ) {
		$returnHtml .= '<div class="'.$params["class"].'">';
	}

	$returnHtml .= ingeni_joi_get_all_events();

	if ( strlen($params["class"]) > 0 ) {
		$returnHtml .= '</div>';
	}

	return $returnHtml;
}






//
// Remove all cached info from the WP DB
//
function ingeni_joi_clear_cache( ) {
	delete_option(JOI_CACHE_TIMEOUT);
	delete_option(JOI_CACHED_EVENT_INFO);
	delete_option(JOI_CACHED_PROGRAM_INFO);
	delete_option(JOI_CACHED_SESSION_INFO);
}


function ingeni_load_joi() {
	
	// Init auto-update from GitHub repo
	require 'plugin-update-checker/plugin-update-checker.php';
	$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
		'https://github.com/BruceMcKinnon/ingeni-eventbrite',
		__FILE__,
		'ingeni-joi-events'
	);
}
add_action( 'wp_enqueue_scripts', 'ingeni_load_joi' );



//
// Cusom CSS
//
function ingeni_joi_enqueue_scripts() {
	wp_enqueue_style( 'ingeni-joi-css', plugins_url('css/ingeni-joi-events.css', __FILE__) );
}
add_action( 'wp_enqueue_scripts', 'ingeni_joi_enqueue_scripts' );

function ingeni_joi_enqueue_admin_scripts() {
	wp_enqueue_style( 'ingeni-joi-admin-css', plugins_url('css/ingeni-joi-events-admin.css', __FILE__) );
}
add_action( 'admin_enqueue_scripts', 'ingeni_joi_enqueue_admin_scripts' );
    




//
// Admin functions
//

add_action('admin_menu', 'ingeni_joi_submenu_page');
function ingeni_joi_submenu_page() {
	add_submenu_page( 'tools.php', 'Joi Events', 'Joi Events', 'manage_options', 'ingeni_joi_options', 'ingeni_joi_options_page' );
}

function ingeni_joi_options_page() {
	global $ingeniJoiEventsApi;


	if ( !$ingeniJoiEventsApi ) {
		$token = get_option(JOI_PRIVATE_TOKEN);
		$url = get_option(JOI_API_URL);
		$ingeniJoiEventsApi = new IngeniJoiEventsApi( $url, $token );
	}

	if ( (isset($_POST['ingeni_joi_edit_hidden'])) && ($_POST['ingeni_joi_edit_hidden'] == 'Y') ) {
		$errMsg = '';
		
		switch ($_REQUEST['btn_ingeni_joi_submit']) {
			case TEST_JOI_SETTINGS :

				$errMsg = "";
				$return_json = $ingeniJoiEventsApi->get_joi_events( true, $errMsg );
				if ( ( !empty($return_json) ) && ( strlen($errMsg) == 0 ) ) {
					echo('<div class="updated"><p><strong>OK</p></div>');
				} else {
					echo('<div class="updated"><p><strong>Error: '.$errMsg.'</strong></p></div>');					
				}

			break;
				
			case SAVE_JOI_SETTINGS :
				try {
					update_option(JOI_PRIVATE_TOKEN, $_POST[JOI_PRIVATE_TOKEN] );
					update_option(JOI_API_URL, $_POST[JOI_API_URL] );

					echo('<div class="updated"><p><strong>Settings saved...</strong></p></div>');

				} catch (Exception $e) {
					echo('<div class="updated"><p><strong>Error: '.$e->getMessage().'</strong></p></div>');		
				}

			break;
		}
	}

	echo('<div class="ingeni_joi_events_admin_wrap">');
		echo('<img src="'.plugins_url('css/joi_logo_seafoam_green_1000px.png', __FILE__).'" height="60px" width="auto" title="Joi Events"></img>');
		echo('<h2>Joi Events</h2>');

		echo('<form action="'. str_replace( '%7E', '~', $_SERVER['REQUEST_URI']).'" method="post" name="ingeni_joi_options_page">'); 
			echo('<input type="hidden" name="ingeni_joiedit_hidden" value="Y">');
			
			echo('<table class="form-table">');

			$url = get_option(JOI_API_URL);
			if ( strlen( trim($url) ) == 0 ) {
				$url = JOI_DEFAULT_URL;
			}
			echo('<tr valign="top">');
				echo('<td>Joi Events API URL</td><td><input type="text" name="'.JOI_API_URL.'" value="'.$url.'"></td>'); 
			echo('</tr>');
			echo('<tr valign="top">');
				echo('<td>Joi Events API Private Token</td><td><input type="text" name="'.JOI_PRIVATE_TOKEN.'" value="'.get_option(JOI_PRIVATE_TOKEN).'"></td>'); 
			echo('</tr>');


			
			echo('</tbody></table><br/>');			
			
			echo('<p class="submit"><input type="submit" name="btn_ingeni_joi_submit" id="btn_ingeni_joi_submit" class="button button-primary" value="'.SAVE_JOI_SETTINGS.'">   ');
			echo('<input type="submit" name="btn_ingeni_joi_submit" id="btn_ingeni_joi_submit" class="button button-primary" value="'.TEST_JOI_SETTINGS.'"></p>');
		echo('</form>');	
	echo('</div>');
}



//
// Plugin activation/deactivation hooks
//
function ingeni_settings_link($links) { 
  $settings_link = '<a href="tools.php?page=ingeni_joi_options">Settings</a>'; 
  array_push($links, $settings_link); 
  return $links; 
}
$plugin = plugin_basename(__FILE__); 
add_filter("plugin_action_links_$plugin", 'ingeni_settings_link' );


//
// Plugin registration functions
//
register_activation_hook(__FILE__, 'ingeni_joi_activation');
function ingeni_joi_activation() {
	try {
		global $ingeniJoiEventsApi;

		if ( !$ingeniJoiEventsApi ) {

			$token = get_option(JOI_PRIVATE_TOKEN);
			$url = get_option(JOI_API_URL);
			
			if (strlen($token) > 0) {
				$ingeniJoiEventsApi = new IngeniJoiEventsApi( $token );
			}
		}
	} catch (Exception $e) {
		fb_log("ingeni_joiactivation(): ".$e->getMessage());
	}
	flush_rewrite_rules( false );
}

register_deactivation_hook( __FILE__, 'ingeni_joi_deactivation' );
function ingeni_joi_deactivation() {
	flush_rewrite_rules( false );
}

?>