<?php
/*
Plugin Name: Joi Events
Version: 2020.04
Plugin URI: http://ingeni.net
Author: Bruce McKinnon - ingeni.net
Author URI: http://ingeni.net
Description: Get Joi event info
License: GPL v3

Ingeni Joi Events
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
// v2020.02 - Minor date formatting change
// v2020.03 - Added track nesting, and the hidelabelcolors option for the shortcode
// v2020.04 - Fixed update URL



define("SAVE_JOI_SETTINGS", "Save Settings...");
define("TEST_JOI_SETTINGS", "Test Connection...");
define("CLEAR_JOI_CACHE", "Clear Cache...");
define("JOI_PRIVATE_TOKEN", "ingeni_joi_private_token");
define("JOI_API_URL", "ingeni_joi_api_url");
define("JOI_API_DEBUG", "ingeni_joi_api_debug");

define("JOI_CACHE_TIMEOUT_DATE", "ingeni_joi_cache_timeout_date");
define("JOI_CACHE_MINS", "ingeni_joi_cache_timeout_mins");
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
function ingeni_joi_get_all_events( $hide_label_colors ) {
	global $ingeniJoiEventsApi;

	$debugMode = get_option(JOI_API_DEBUG, 0);

	$hide_colors = explode(',',$hide_label_colors);

	// If the cache is empty, go an get a live refresh
	if ( !$ingeniJoiEventsApi ) {
		$token = get_option(JOI_PRIVATE_TOKEN);
		$url = get_option(JOI_API_URL, JOI_DEFAULT_URL);
		$ingeniJoiEventsApi = new IngeniJoiEventsApi( $url, $token );
	}

	$cache_expiry = new DateTime("2020-01-01");
	$cache_expiry = get_option(JOI_CACHE_TIMEOUT_DATE, $cache_expiry);

	if ( $cache_expiry < new DateTime("now") ) {
		// Time to clear the cache
		ingeni_joi_clear_cache();
		if ($debugMode == 1) {
			$ingeniJoiEventsApi->fb_log("clearing cache");
		}

		// Now grab the JSON feed of events
		$json = $ingeniJoiEventsApi->get_joi_events(false,$errMsg);

		if ( ( !empty($json) ) && ( strlen($errMsg) == 0 ) ) {

			// It worked! Now save the eventInfo, programInfo and sessionInfo into seperate structures in the DB
			update_option(JOI_CACHED_EVENT_INFO, serialize($json['eventInfo']) );
			update_option(JOI_CACHED_SESSION_INFO, serialize($json['sessionInfo']) );
			update_option(JOI_CACHED_PROGRAM_INFO, serialize($json['programInfo']) );	
			update_option(JOI_CACHED_TIMELINE, serialize($json['timeline']) );	

			if ($debugMode == 1) {
				$ingeniJoiEventsApi->fb_log(print_r($json['timeline'],true),'timeline', true);
				$ingeniJoiEventsApi->fb_log(print_r($json['sessionInfo'],true),'session-info', true);
				$ingeniJoiEventsApi->fb_log(print_r($json['programInfo'],true),'program-info', true);
				$ingeniJoiEventsApi->fb_log(print_r($json['eventInfo'],true),'event-info', true);
			}


			// Set the new cache expiry date
			$new_expiry = new DateTime("now");
			$expiry_mins = get_option(JOI_CACHE_MINS, 60);
			if ($expiry_mins < 1) {
				$expiry_mins = 1;  // 1 minute
			}
			if ($expiry_mins > 1440) {
				$expiry_mins = 1440; // 1 day
			}
			$expiry_formatted = sprintf("PT%dM",$expiry_mins);
			$new_expiry->add(new DateInterval($expiry_formatted));
			update_option(JOI_CACHE_TIMEOUT_DATE, $new_expiry );		
		}

	}

	$timeline = unserialize( get_option(JOI_CACHED_TIMELINE) );

	$allEventsHtml = "";
	$day_h3 = "";

	// Construct the front-end HTML
	foreach($timeline as $day) {
		$dayHtml = '<div class="joi_day">';
		$day_h3 = '';

		// Current track id
		$current_track = '';
		$track_label_color = '#000000';
		$track_wrapper_start = '';
		$track_wrapper_end = '';

		foreach($day as $item) {
			if (strlen($day_h3) == 0) {
				$day_h3 = '<h3>'.date("D j M Y",strtotime($item['session_date'])).'</h3>';
			}

				$item_track = '';
				$extra_row_css = '';
				
				//
				// Build row contents
				//
				$sessionHtml = '<div class="cell small-3 medium-2">'.date("g:i a",strtotime($item['start'])).'</div>';
				$sessionHtml .= '<div class="cell small-3 medium-2">'.date("g:i a",strtotime($item['end'])).'</div>';

				$label = '';
				if ( array_key_exists('labels',$item) ) {
					if (count($item['labels']) > 0) {
						$theLabel = ingeni_joi_get_label($item['labels'][0]);
						$label_title = $theLabel['label'];
						$label_color = $theLabel['color'];

						// Should this row be hidden?
						if (in_array($label_color,$hide_colors) ) {
							$label_title = '';
						}

						if (trim($label_title) != "") {
							$label = '<div class="joi_label"><span style="background-color: '.$label_color . ';">'.$label_title.'</span></div>';
						}
					}
					// Is this session in a track?
					if (count($item['labels']) > 1) {
						$theLabel= ingeni_joi_get_label($item['labels'][1]);
						if ( $theLabel['color'] == $track_label_color) {
							$item_track = $theLabel['label'];

							if ($current_track != $item_track) {
								if ($current_track == '') {
									$track_wrapper_start = '<div class="row track_wrapper"><div class="cell small-12 full"><h4>'.$item_track.'</h4>';
								} else {

									$track_wrapper_start = '</div><!-- 1 --></div>';
								}
							} else {
								$track_wrapper_start = '';
							}
							if ( (strlen($current_track) > 0) && ($item_track == '') ) {
								$track_wrapper_end = '</div><!-- 2 --></div>';
							}
							$current_track = $item_track;

						} else {
							$item_track = '';

						}
					} else {
						$track_wrapper_start = '';
						$track_wrapper_end = '';

						if ( (strlen($current_track) > 0) && ($item_track == '') ) {
							$track_wrapper_start = '</div><!-- 3 '.$current_track.'--></div>';
						}
						$current_track = '';
					}
				}

				$performer = '';
				if ( array_key_exists('performerIds',$item) ) {
					if (count($item['performerIds']) > 0) {
						$performer = ingeni_joi_get_performer($item['performerIds'][0]);
					}
					if (strlen($performer) > 0) {
						$performer = '<div class="performer">'.$performer.'</div>';
					}
				}
				$description = '';
				if ( array_key_exists('description',$item) ) {
					if (strlen( trim($item['description']) ) > 0) {
						$item['description'] = str_replace(PHP_EOL,'<p/>',$item['description']);
						$description = '<div class="description"><p>'.$item['description'].'</p></div>';
					}
				}
				$location = '';
				if ( array_key_exists('program',$item) ) {
					$location = ingeni_joi_get_location($item['program']);
					if (strlen($location) > 0) {
						$location = '<div class="location">'.$location.'</div>';
					}
				}


				$sessionHtml .= '<div class="cell small-6 medium-8 bold">'.$item['title'].$label.'</div>';

				

				if (strlen(trim($performer.$description)) > 0) {
					$extra_row_css .= 'accordion_wrap';
					$sessionHtml = '<button class="joi_accordion">'.$sessionHtml.'</button>';
					$sessionHtml .= '<div class="joi_panel">'.$performer.$description.$location.'</div>';
				}
				//
				// End building row contents
				//

			// Add row wrapping around row contents
			$dayHtml .= $track_wrapper_start.'<div class="row '.$extra_row_css.'">'. $sessionHtml . '</div>'.$track_wrapper_end;

		}
		$dayHtml .= '</div>';
		$allEventsHtml .= $day_h3 . $dayHtml;
		
	}

	return $allEventsHtml;
}

// Get a Joi Event performer/presenter name
function ingeni_joi_get_performer( $uid ) {
	$json = unserialize( get_option( JOI_CACHED_EVENT_INFO ) );

	$performer_name = '';

	if ( array_key_exists( $uid, $json['performers'] ) ) {
		$performer_name = $json['performers'][$uid]['name'];
	}

	return trim($performer_name);
}

// Get a Joi event label
function ingeni_joi_get_label( $uid, $required_color = '' ) {
	$json = unserialize( get_option( JOI_CACHED_EVENT_INFO ) );

	$label = '';

	if ( array_key_exists( $uid, $json['session_labels'] ) ) {
		$label = $json['session_labels'][$uid];
	}

	return $label;
}

// Get a Joi location info
function ingeni_joi_get_location( $program_ary ) {
	$loc_info = $loc_name = $loc_venue = '';


	if ( array_key_exists( 'title', $program_ary ) ) {
		$loc_name = $program_ary['title'];
	}
	if ( array_key_exists( 'venue', $program_ary ) ) {
		$loc_venue = $program_ary['venue'];;
	}

	if ( (strlen($loc_name) > 0) && (strlen($loc_venue) > 0) ) {
		$loc_info = $loc_name . ', ' . $loc_venue;
	} else {
		$loc_info = $loc_name . $loc_venue;
	}

	return $loc_info;
}



// Wordpress shortcode - List all Joi Events
add_shortcode( 'joi-events-list-all', 'ingeni_joi_events_list_all' );

function ingeni_joi_events_list_all( $atts ) {
	$params = shortcode_atts( array(
		'class' => 'joi_events_wrapper',
		'accordion' => 1,
		'hidelabelcolors' => '',
	), $atts );


	$returnHtml = "";

	$extra_css = "";

	// Load the accordion JS if required.
	if ( $params["accordion"] == 1 ) {
		wp_enqueue_script( 'ingeni-joi-accordion' );
		$extra_css .= " ingeni_joi_accordion";
	}


	if ( strlen($params["class"]) > 0 ) {
		$returnHtml .= '<div class="'.$params["class"].$extra_css.'">';
	}

	$returnHtml .= ingeni_joi_get_all_events( $params["hidelabelcolors"] );

	if ( strlen($params["class"]) > 0 ) {
		$returnHtml .= '</div>';
	}

	return $returnHtml;
}






//
// Remove all cached info from the WP DB
//
function ingeni_joi_clear_cache( ) {
	delete_option(JOI_CACHE_TIMEOUT_DATE);
	delete_option(JOI_CACHED_EVENT_INFO);
	delete_option(JOI_CACHED_PROGRAM_INFO);
	delete_option(JOI_CACHED_SESSION_INFO);
}


function ingeni_load_joi() {
	
	// Init auto-update from GitHub repo
	require 'plugin-update-checker/plugin-update-checker.php';
	$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
		'https://github.com/BruceMcKinnon/ingeni-joi-events',
		__FILE__,
		'ingeni-joi-events'
	);
}
add_action( 'wp_enqueue_scripts', 'ingeni_load_joi' );


//
// Custom JS
//
function ingeni_joi_register_js() {
	wp_register_script( 'ingeni-joi-accordion', plugins_url('js/ingeni_joi_accordion.js', __FILE__) );
}
add_action( 'wp_enqueue_scripts', 'ingeni_joi_register_js' );


//
// Custom CSS
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
				$return_json = $ingeniJoiEventsApi->get_joi_events( $_POST[JOI_API_URL], $errMsg );
				if ( ( !empty($return_json) ) && ( strlen($errMsg) == 0 ) ) {
					echo('<div class="updated"><p><strong>OK</p></div>');
				} else {
					echo('<div class="updated"><p><strong>Error: '.$errMsg.'</strong></p></div>');					
				}

			break;

			case CLEAR_JOI_CACHE :
				$errMsg = "";
				ingeni_joi_clear_cache();
				echo('<div class="updated"><p><strong>Cache cleared...</p></div>');
			break;
				
			case SAVE_JOI_SETTINGS :
				try {
					update_option(JOI_PRIVATE_TOKEN, $_POST[JOI_PRIVATE_TOKEN] );
					update_option(JOI_API_URL, $_POST[JOI_API_URL] );
					update_option(JOI_CACHE_MINS, $_POST[JOI_CACHE_MINS] );
					$debug_mode = 0;
					if ( isset($_POST[JOI_API_DEBUG]) ) {
						$debug_mode = 1;
					}
					update_option(JOI_API_DEBUG, $debug_mode );

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
			echo('<input type="hidden" name="ingeni_joi_edit_hidden" value="Y">');
			
			echo('<table class="form-table">');

			$url = get_option(JOI_API_URL);
			if ( strlen( trim($url) ) == 0 ) {
				$url = JOI_DEFAULT_URL;
			}
			echo('<tr valign="top">');
				echo('<td>Joi Events API URL</td><td><input type="text" name="'.JOI_API_URL.'" value="'.$url.'"></td>'); 
			echo('</tr>');
			echo('<tr valign="top hide">');
				echo('<td>Joi Events API Private Token</td><td><input type="text" name="'.JOI_PRIVATE_TOKEN.'" value="'.get_option(JOI_PRIVATE_TOKEN).'"></td>'); 
			echo('</tr>');

			echo('<tr valign="top">'); 
				echo('<td>Cache Timeout (mins)</td><td><input type="number" name="'.JOI_CACHE_MINS.'" min="1" max="1440" value="'.get_option(JOI_CACHE_MINS,60).'" /></td>'); 
			echo('</tr>');
			
			echo('<tr valign="top">');
				$debug_mode = get_option(JOI_API_DEBUG, 0);
				$checked = '';
				if ( ( $debug_mode < 0) || ( $debug_mode > 1) ) {
					$debug_mode = 0;
				}
				if ( $debug_mode == 1 ) {
					$checked = 'checked';
				}
				echo('<td>&nbsp;</td><td><input type="checkbox" name="'.JOI_API_DEBUG.'"  value="'.$debug_mode.'" '.$checked.'/><label>Debug mode</label></td>'); 
			echo('</tr>');	


			
			echo('</tbody></table><br/>');			
			
			echo('<p class="submit"><input type="submit" name="btn_ingeni_joi_submit" id="btn_ingeni_joi_submit" class="button button-primary" value="'.SAVE_JOI_SETTINGS.'">');
			echo('<input type="submit" name="btn_ingeni_joi_submit" id="btn_ingeni_joi_submit" class="button button-primary" value="'.TEST_JOI_SETTINGS.'">');
			echo('<input type="submit" name="btn_ingeni_joi_submit" id="btn_ingeni_joi_submit" class="button button-primary" value="'.CLEAR_JOI_CACHE.'"></p>');
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