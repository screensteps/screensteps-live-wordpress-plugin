<?php
/*
Plugin Name: ScreenSteps Live
Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
Description: This plugin will incorporate lessons from your ScreenSteps Live account into your WordPress Pages.
Version: 0.5
Author: Trevor DeVore
Author URI: http://www.screensteps.com
*/

// This plugin processes content of all posts
add_filter('the_content', 'screenstepslive_insert', 100);
add_action('admin_menu', 'screenstepslive_add_pages');


function screenstepslive_include(&$sslivewp)
{
	// PROVIDE EXAMPLE SETTINGS AS DEFAULT
	if (get_option('screenstepslive_domain') == '' && get_option('screenstepslive_api_key') == '') {
		update_option('screenstepslive_domain', 'example.screenstepslive.com');
		update_option('screenstepslive_api_key', '12e5317e88');
		update_option('screenstepslive_protocol', 'http');
	}
	
	require_once('sslivewordpress_class.php');
	
	// Create ScreenSteps Live object using your domain and API key
	$sslivewp = new SSLiveWordPress(get_option('screenstepslive_domain'), 
									get_option('screenstepslive_api_key'), 
									get_option('screenstepslive_protocol'));
	$sslivewp->manual_settings = unserialize(get_option('screenstepslive_manual_settings'));
	$sslivewp->user_can_read_private = current_user_can('read_private_posts') == 1;
}


// Called by WordPress to process content
function screenstepslive_insert($the_content)
{
	$sslivewp = NULL;
	$text = '';
	
	$manual_id = intval($_GET['manual_id']);
	$lesson_id = intval($_GET['lesson_id']);
	
	if (strpos($the_content, '{{SCREENSTEPSLIVE_CONTENT}}') !==FALSE ) {
		// Include necessary SS Live files
		screenstepslive_include($sslivewp);
		
		if (!$manual_id > 0)
		{
			$text = $sslivewp->GetManualsList();
			
		} else if ($manual_id > 0 && $lesson_id == 0) {
			$text = $sslivewp->GetManualList($manual_id);
		
		}  else if ($manual_id > 0 && $lesson_id > 0) {
			$text = $sslivewp->GetLessonHTML($manual_id, $lesson_id);

		}
	}
	
	if ($text != '') $the_content = str_ireplace('{{SCREENSTEPSLIVE_CONTENT}}', $text, $the_content);
	
	$sslivewp = NULL;
	
    return $the_content;
}

// Add admin page
function screenstepslive_add_pages()
{
	add_options_page('ScreenSteps Live Options', 'ScreenSteps Live', 8, __FILE__, 'screenstepslive_option_page');
}


// Shows Admin page
function screenstepslive_option_page()
{
	$sslivewp = NULL;
	screenstepslive_include($sslivewp);
	
	// API form was submitted
	if ($_POST['api_submitted'] == 1) {
		update_option('screenstepslive_domain', $_POST['domain']);
		update_option('screenstepslive_api_key', $_POST['api_key']);
		update_option('screenstepslive_protocol', $_POST['protocol']);
	}
	
	// Manuals form was subbmited
	if ($_POST['manuals_submitted'] == 1) {
		$manual_settings = array();
		
		// Array has keys that are ids of visible lessons. Value is permissions.
		if (is_array($_POST['manual_visible'])) {
			foreach($_POST['manual_visible'] as $manual_id => $value) {
				$manual_settings[$manual_id] = strtolower($_POST['manual_permission'][$manual_id]);
			}
		}
		
		update_option('screenstepslive_manual_settings', serialize($manual_settings));
	}
	
	// UI
	$xmlobject = $sslivewp->GetManuals(false);
	if ($xmlobject) {
		if (count($xmlobject) == 0) {
			print "<div>No manuals found.</div>";
		} else {
			// Get stored settings
			$manual_settings = unserialize(get_option('screenstepslive_manual_settings'));
			
			// Print API info
			print ('<form method="post" action="' . GETENV('REQUEST_URI') . '">' . "\n");
			print ('<input type="hidden" name="api_submitted" value="1">' . "\n");
			print ('<table>');
			print ('<tr><td>ScreenSteps Live Domain:</td><td>' . 
					'<input type="text" name="domain" id="domain" value="'. get_option('screenstepslive_domain') . '"></td></tr>');
			print ('<tr><td>ScreenSteps Live API Key:</td><td>' . 
					'<input type="text" name="api_key" id="api_key" value="'. get_option('screenstepslive_api_key') . '"></td></tr>');
			print ('<tr><td>ScreenSteps Live Domain:</td><td>' . 
					'<input type="text" name="protocol" id="protocol" value="'. get_option('screenstepslive_protocol') . '"></td></tr>');
			print ('</table>');
			print ('<button type="submit" id="submit_api_settings">Submit</button>' . "\n");
			print ("</form>\n");
			
			print ("<p></p>\n");
			
			
			// Print manual setings		
			print ('<form method="post" action="' . GETENV('REQUEST_URI') . '">' . "\n");
			print ('<input type="hidden" name="manuals_submitted" value="1">' . "\n");
			print ("<table><tr><td>Active</td><td>Manual</td><td>Permissions</td></tr><tr>\n");
			foreach ($xmlobject as $manual) {
				// Determine initial state for visible checkbox and permission settings.
				$manual_id = intval($manual->id); // arrays don't like object props as key refs
				$checked = ($manual_settings[$manual_id] != '') ? ' checked' : '';
				$private_option = '';
				$public_option = '';
				if ($checked == ' checked') {
					$private_option = $manual_settings[$manual_id] == 'private' ? ' selected="selected"' : '';
					$public_option = $manual_settings[$manual_id] == 'public' ? ' selected="selected"' : '';
					$all_option = $manual_settings[$manual_id] == 'all' ? ' selected="selected"' : '';
				}
				
				print ("<tr>\n");
				print ('<td><input type="checkbox" name="manual_visible[' . $manual->id . ']"' . $checked . '></td>' . "\n");
				print ('<td>' . $manual->title . "</td>\n");
				print ('<td><select name="manual_permission[' . $manual->id . ']"><option' . $private_option . 
						'>Private</option><option' . $public_option . '>Public</option>' . 
						'<option' . $all_option . '>All</option></select></td>' . "\n");
				print ("</tr>\n");
			}
			print ("</table>\n");
			print ('<button type="submit" id="submit_manual_settings">Submit</button>' . "\n");
			print ("</form>\n");
		}
	} else {
		print ("Error:" . $this->last_error);
	}
	
	$sslivewp = NULL;
}

//echo <<<END
			
//END;

?>