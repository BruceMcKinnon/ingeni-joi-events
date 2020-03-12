<?php
/*
Plugin Name: Joi Events
Version: 2020.11
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
// v2020.05 - Re-did track nesting code. Added the groupcolors option to the shortcode
// v2020.06 - Added extra trapping in case the 'color' field is not included in labels.
//					- Extra performer info included, plus social icons.
// v2020.07 - Does a sort of the days session to make sure all sessions of the same track are together.
// v2020.08 - Updating code hooked in wrong location!
// v2020.09 - Support the display of multiple performers.
//					- Better parsing of session labels, ignoring track labels.
// v2020.10	- Added support for CSS3 sticky positioning on h3 headers.
// v2020.11  - Added support for the dayheaderformat parameter



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
function ingeni_joi_get_all_events( $hide_label_colors, $group_label_colors, $day_header_format = "D j M Y" ) {
	global $ingeniJoiEventsApi;

	$debugMode = get_option(JOI_API_DEBUG, 0);

	$hide_colors = explode(',',$hide_label_colors);

	$track_label_colors = explode(',',$group_label_colors);


	// If the cache is empty, go an get a live refresh
	if ( !$ingeniJoiEventsApi ) {
		$token = get_option(JOI_PRIVATE_TOKEN);
		$url = get_option(JOI_API_URL, JOI_DEFAULT_URL);
		$ingeniJoiEventsApi = new IngeniJoiEventsApi( $url, $token );
	}
//$ingeniJoiEventsApi->fb_log('track colours: '.print_r($track_label_colors,true));

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
	$day_header = "";

	// Construct the front-end HTML
	foreach($timeline as $day) {
		$dayHtml = '<div class="joi_day">';
		$day_header = '';

		// Current track id
		$current_track = '';
		$track_label_color = '#000000';
		$track_wrapper_start = '';
		$track_wrapper_end = '';


		$day = joi_sort_day( $day, $track_label_colors );

		foreach($day as $item) {
			if (strlen($day_header) == 0) {
				$day_header = '<div class="sticky_container"><h3>'.date($day_header_format,strtotime($item['session_date'])).'</h3></div>';
			}

				$item_track = '';
				$extra_row_css = '';
				
				//
				// Build row contents
				//
				$sessionHtml = '<div class="cell small-3 medium-2">'.date("g:i a",strtotime($item['start'])).'</div>';
				$sessionHtml .= '<div class="cell small-3 medium-2">'.date("g:i a",strtotime($item['end'])).'</div>';

$debug_info = '';
				$label = '';
				if ( array_key_exists('labels',$item) ) {
//$ingeniJoiEventsApi->fb_log('labels: '.print_r($item['labels'],true));
					if (count($item['labels']) > 0) {
						$got_label = false;
						for ($idx = 0; $idx < count($item['labels']); ++$idx) {

							// Get the primary label colour
							$theLabel = ingeni_joi_get_label($item['labels'][$idx]);
							$label_title = '';
							$label_color = '';

							if ( is_array($theLabel) ) {

								if ( array_key_exists('label',$theLabel) ) {
									$label_title = $theLabel['label'];
								}
								if ( array_key_exists('color',$theLabel) ) {
									$label_color = $theLabel['color'];
								}

							}

							// Just make sure this label isn't actually a session track grouping label
							$track_label = joi_group_this_label($item['labels'], $track_label_colors, $idx);
							if ($track_label == '') {
								$got_label = true;
								$idx < count($item['labels']);
							}
						}

						$debug_info = ' [' . $label_title . ' | ' . $label_color .']';
						// Should this row be hidden?
						if (in_array($label_color,$hide_colors) ) {
							$label_title = '';
						}

						if (trim($label_title) != "") {
							$label = '<div class="joi_label"><span style="background-color: '.$label_color . ';">'.$label_title.'</span></div>';
						}
					}

					// Is this session in a track?
					// Now see if this row is part of a grouped track
					// Should this row be grouped?

					$item_track = joi_group_this_label($item['labels'], $track_label_colors) ;
//$ingeniJoiEventsApi->fb_log('item_track: '.$item['title'].'='.$item_track.' | current='.$current_track);
					if ($item_track != '') {


							if ($current_track != $item_track) {
								if ($current_track == '') {
									$track_wrapper_start = '<div class="row track_wrapper"><div class="cell small-12 full"><h4>'.$item_track.'</h4>';
								} else {

									$track_wrapper_start = '</div><!-- 1 --></div>';
									$track_wrapper_start .= '<div class="row track_wrapper"><div class="cell small-12 full"><h4>'.$item_track.'</h4>';
								}
							} else {
								$track_wrapper_start = '';
							}
							if ( (strlen($current_track) > 0) && ($item_track == '') ) {
								$track_wrapper_end = '</div><!-- 2 --></div>';
							}
							$current_track = $item_track;

					} else {
						$track_wrapper_start = '';
						$track_wrapper_end = '';

						if ( (strlen($current_track) > 0) && ($item_track == '') ) {
							$track_wrapper_start = '</div><!-- 3 '.$current_track.' | '.$item_track.'--></div>';
						}
						$current_track = '';
					}


				} else {

//$ingeniJoiEventsApi->fb_log('no labels: '.$item['title'].'='.$item_track.' | current='.$current_track);

						// In this circumstance, we get a grouped session, followed by a session with no labels.
						$track_wrapper_start = '';
						$track_wrapper_end = '';

						if ( (strlen($current_track) > 0) && ($item_track == '') ) {
							$track_wrapper_start = '</div><!-- 4 '.$current_track.'--></div>';
						}
						$current_track = '';

				}

				$performer = '';
				if ( array_key_exists('performerIds',$item) ) {
					if (count($item['performerIds']) > 0) {
						$performer = ingeni_joi_get_performers($item['performerIds']);
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

				$debug_info = '';
				$sessionHtml .= '<div class="cell small-6 medium-8 bold">'.$item['title'].$label.$debug_info.'</div>';

				

				if (strlen(trim($performer.$description)) > 0) {
					$extra_row_css .= 'accordion_wrap';
					$sessionHtml = '<button class="joi_accordion">'.$sessionHtml.'</button>';
					$sessionHtml .= '<div class="joi_panel">'.$description.$location.$performer.'</div>';
				}
				//
				// End building row contents
				//

			// Add row wrapping around row contents
			if ( strlen(trim($item['title'])) > 0 ) {
				$dayHtml .= $track_wrapper_start.'<div class="row '.$extra_row_css.'">'. $sessionHtml . '</div>'.$track_wrapper_end;
			}
		}
		$dayHtml .= '</div>';
		$allEventsHtml .= $day_header . $dayHtml;
		
	}

	return $allEventsHtml;
}


//
// Work through the days items, making sure all grouped sessions are in fact grouped together and don't have randon sessions inserted
//
function joi_sort_day( $day, $track_label_colors ) {
	global $ingeniJoiEventsApi;

	$prev_item_labels = '';
	$next_item_labels = '';
	$curr_item_labels = '';
	$curr_in_track = false;

	$prev_start = $next_start = $curr_start = '';

	for ($idx = 0; $idx < count($day); $idx++) {
		$prev_start = $curr_start;
		$curr_start = $day[$idx]['start'];
		if ( ($idx+1) < count($day)) {
			$next_start = $day[$idx+1]['start'];
		} else {
			$next_start = '';
		}
		// Does this session and the previous one and the next one all start at the same time?
		$prev_in_track = false;
		if ( ($prev_start == $curr_start) && ($curr_start == $next_start) ) {
//$ingeniJoiEventsApi->fb_log('three in a row:'.$curr_start.' ['.$day[$idx]['on_day'].']');
			// In this case, we have three session all starting at the same time
			// Make sure you keep a copy of the previous items labels
			if ( is_array($curr_item_labels) ) {
				$prev_item_labels = $curr_item_labels;

				// Was the prevous session in track?
				$track_label = joi_group_this_label( $prev_item_labels, $track_label_colors );
				if ( $track_label != '' ) {
					$prev_in_track = true;
				}

			}


			// Get the current item labels
			if ( array_key_exists('labels', $day[$idx] ) ) {
				$curr_item_labels = $day[$idx]['labels'];
			} else {
				$curr_item_labels = '';
			}


			// Is the current session in track?
			$curr_in_track = false;
			$track_label = joi_group_this_label( $curr_item_labels, $track_label_colors );
			if ( $track_label != '' ) {
				$curr_in_track = true;
			}


			// Is the next session in track?
			$next_in_track = false;
			if (($idx+1) < count($day) ) {

				// Get the next item labels
				if ( array_key_exists('labels', $day[$idx+1] ) ) {
					$next_item_labels = $day[$idx+1]['labels'];

					$track_label = joi_group_this_label( $next_item_labels, $track_label_colors );
					if ( $track_label != '' ) {
						$next_in_track = true;
					}

				} else {
					$next_item_labels = '';
					$next_in_track = false;
				}

			} else {
				$next_start = '';
				$next_in_track = false;
			}

			// Now we have all of the info, the test is:
			//
			// if ( ( x-1 && x+1 ) && ( !x ) ) {
			//		swap the position of the current and next rows
			// }
			//
//$ingeniJoiEventsApi->fb_log('test is '.joi_bool_str($prev_in_track).' '.joi_bool_str($next_in_track).' '.joi_bool_str($curr_in_track));
			if ( ( $prev_in_track && $next_in_track ) && ( !$curr_in_track ) ) {
//$ingeniJoiEventsApi->fb_log('current sitting between two tracks!!!!!');
				// Time to swap the position of the current session with the next session
				joi_move_element( $day, ($idx+1), $idx);
			}

		}
	}

	return $day;
}

function joi_bool_str($value) {
	$retStr = 'false';
	if ($value) {
		$retStr = 'true';
	}
	return $retStr;
}

function joi_move_element(&$array, $a, $b) {
//$ingeniJoiEventsApi->fb_log('splicing:'.$a.' = '.$b);
	$out = array_splice($array, $a, 1);
	array_splice($array, $b, 0, $out);
}



function joi_group_this_label( &$labels, &$track_label_colors, $specific_idx = -1 ) {
	$retTrackLabel = '';

//global $ingeniJoiEventsApi;
	$label_count = count($labels);
	$start_idx = 0;
	$end_idx = $label_count;
	if ($specific_idx > -1) {
		$start_idx = $specific_idx;
		$end_idx = $specific_idx+1;
	}


//$ingeniJoiEventsApi->fb_log('start ['.$end_idx.']: '.print_r($labels,true));
	if ($end_idx > 0) {
//$ingeniJoiEventsApi->fb_log('start: '.print_r($labels,true));
		for ( $idx = $start_idx; $idx < $end_idx; $idx++ ) {

			$session_label = ingeni_joi_get_label( $labels[$idx] );
//$ingeniJoiEventsApi->fb_log('track colours: '.print_r($session_label,true).' = '. print_r($track_label_colors,true));
			if ( is_array($session_label) ) {
				if ( array_key_exists('color',$session_label) ) {
					if ( in_array($session_label['color'], $track_label_colors) ) {
						$retTrackLabel = $session_label['label'];
						$idx = $end_idx;
						break;
					}
				}
			}
		}
	}

	return $retTrackLabel;
}



function ingeni_joi_get_field($key, $target_ary) {
	$value = '';
	if ( array_key_exists($key,$target_ary) ) {
		$value = $target_ary[$key];
	}

	return $value;
}

function ingeni_joi_get_icon($type) {
	$type = strtolower($type);
	$retHtml = $type;

	switch ($type) {
		case 'twitter':
			$retHtml = '<img src="'.plugins_url('icons/twitter_24px.svg', __FILE__).'" alt="'.$type.'" />';
		break;
		case 'facebook':
			$retHtml = '<img src="'.plugins_url('icons/facebook_24px.svg', __FILE__).'" alt="'.$type.'" />';
		break;
		case 'instagram':
			$retHtml = '<img src="'.plugins_url('icons/instagram_24px.svg', __FILE__).'" alt="'.$type.'" />';
		break;
		case 'linkedin':
			$retHtml = '<img src="'.plugins_url('icons/linkedin_24px.svg', __FILE__).'" alt="'.$type.'" />';
		break;
		case 'youtube':
			$retHtml = '<img src="'.plugins_url('icons/youtube_24px.svg', __FILE__).'" alt="'.$type.'" />';
		break;
		case 'web':
			$retHtml = '<img src="'.plugins_url('icons/web_24px.svg', __FILE__).'" alt="'.$type.'" />';
		break;
	}

	return $retHtml;
}


// Get a Joi Event performer/presenter name
function ingeni_joi_get_performers( &$performers ) {
	global $ingeniJoiEventsApi;
	$json = unserialize( get_option( JOI_CACHED_EVENT_INFO ) );

	$performer_name = '';

	if ( is_array($performers) ) {
		if ( count($performers) > 0 ) {
			for ($performer_idx = 0; $performer_idx < count($performers); $performer_idx++) {
				$uid = $performers[$performer_idx];

				if ( array_key_exists( $uid, $json['performers'] ) ) {

					$name = ingeni_joi_get_field('name',$json['performers'][$uid]);
					$title = ingeni_joi_get_field('line1',$json['performers'][$uid]);
					$line2 = ingeni_joi_get_field('line2',$json['performers'][$uid]);
					$bio = ingeni_joi_get_field('bio',$json['performers'][$uid]);
					$photo = ingeni_joi_get_field('photoUrl',$json['performers'][$uid]);
					$logo = ingeni_joi_get_field('logoUrl',$json['performers'][$uid]);
					if ( ($photo == '') && ($logo != '') ) {
						$photo = $logo;
					}

					//$title = $line1;
					if ( ($title != '') && ($line2 != '') ) {
						$title .= ', ';
					}
					$title .= $line2;

					$performer_name .= '<header>';
					if ($photo != '') {
						$performer_name .= '<div class="photo" style="background-image: url('.$photo.');"></div>';
					}
					
					$performer_name .= '<div class="name">'.$name.'<p class="title">'.$title.'</p></div>';
					$performer_name .= '</header>';

					$performer_name .= '<div class="bio">'.$bio.'</div>';

					$social_links = '';
					if ( array_key_exists('social',$json['performers'][$uid]) ) {
						$socials_ary = $json['performers'][$uid]['social'];

						if ( is_array($socials_ary) ) {
							for ($idx = 0; $idx < count($socials_ary); $idx++) {
								if (strlen(ingeni_joi_get_field('url',$socials_ary[$idx])) > 0) {
									$social_links .= '<a href="'.ingeni_joi_get_field('url',$socials_ary[$idx]).'" target="_blank">'.ingeni_joi_get_icon(ingeni_joi_get_field('type',$socials_ary[$idx])).'</a>';
								}
							}
						}
					}

					$performer_name .= '<div class="social_links">'.$social_links .'</div>';
				}
			}
		}
	}
	return trim($performer_name);
}

// Get a Joi event label
function ingeni_joi_get_label( $uid, $required_color = '' ) {
	$json = unserialize( get_option( JOI_CACHED_EVENT_INFO ) );

	$label = '';

//global $ingeniJoiEventsApi;
//$ingeniJoiEventsApi->fb_log('looking for: '.$uid);

	if ( array_key_exists( $uid, $json['session_labels'] ) ) {
		$label = $json['session_labels'][$uid];
//$ingeniJoiEventsApi->fb_log($uid.' = '.print_r($label,true));
	} else {
		// If not in session lables, have a look in tracks labels
		if ( array_key_exists( $uid, $json['program_labels'] ) ) {
			$label = $json['program_labels'][$uid];
		}
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
		'groupcolors' => '',
		'dayheaderformat' => 'l j M',
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

	$returnHtml .= ingeni_joi_get_all_events( $params["hidelabelcolors"], $params["groupcolors"], $params["dayheaderformat"] );

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
add_action( 'init', 'ingeni_load_joi' );


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