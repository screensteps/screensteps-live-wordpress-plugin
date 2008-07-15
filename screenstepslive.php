<?php
/*
Plugin Name: ScreenSteps Live
Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
Description: This plugin will incorporate lessons from your ScreenSteps Live account into your WordPress Pages.
Version: 0.9.0
Author: Trevor DeVore
Author URI: http://www.screensteps.com
*/

// Global var for SSLiveWordPress object. It is shared among multiple wp callbacks.
$screenstepslivewp = NULL;


// This plugin processes content of all posts
add_filter('the_content', 'screenstepslive_parseContent', 100);
add_filter('the_title', 'screenstepslive_parseTitle', 100);
add_filter('wp_title', 'screenstepslive_parseTitle', 100);
add_filter('delete_post', 'screenstepslive_checkIfDeletedPostIsReferenced', 100);
add_action('admin_menu', 'screenstepslive_addPages');


function screenstepslive_initializeObject()
{
	global $screenstepslivewp;
	
	if (!$screenstepslivewp) {
		// PROVIDE EXAMPLE SETTINGS AS DEFAULT
		if (get_option('screenstepslive_domain') == '' && get_option('screenstepslive_api_key') == '') {
			update_option('screenstepslive_domain', 'example.screenstepslive.com');
			update_option('screenstepslive_api_key', '12e5317e88');
			update_option('screenstepslive_protocol', 'http');
		}
		
		require_once('sslivewordpress_class.php');
		
		// Create ScreenSteps Live object using your domain and API key
		$screenstepslivewp = new SSLiveWordPress(get_option('screenstepslive_domain'), 
										get_option('screenstepslive_api_key'), 
										get_option('screenstepslive_protocol'));
		$screenstepslivewp->manual_settings = unserialize(get_option('screenstepslive_manual_settings'));
		$screenstepslivewp->user_can_read_private = current_user_can('read_private_posts') == 1;
		$screenstepslivewp->manuals_index_post_id = get_option('screenstepslive_manuals_index_post_id');
		$screenstepslivewp->manual_post_id = get_option('screenstepslive_manual_post_id');
		$screenstepslivewp->lesson_post_id = get_option('screenstepslive_lesson_post_id');
	}
	
	// Any caller will just get a reference to this object.
	return $screenstepslivewp;
}


function screenstepslive_parseTitle($the_title) {
	if (strpos($the_title, '{{SCREENSTEPSLIVE_MANUAL_TITLE}}') !== FALSE ) {
		$sslivewp = screenstepslive_initializeObject();
				
		$manual_id = intval($_GET['manual_id']);
		if ($manual_id > 0)
			$the_title = str_ireplace('{{SCREENSTEPSLIVE_MANUAL_TITLE}}', $sslivewp->GetManualTitle($manual_id), $the_title);
		
	} else if (strpos($the_title, '{{SCREENSTEPSLIVE_LESSON_TITLE}}') !== FALSE ) {
		$sslivewp = screenstepslive_initializeObject();
		$manual_id = intval($_GET['manual_id']);
		$lesson_id = intval($_GET['lesson_id']);
		if ($manual_id > 0 && $lesson_id > 0)
			$the_title = str_ireplace('{{SCREENSTEPSLIVE_LESSON_TITLE}}', $sslivewp->GetLessonTitle($manual_id, $lesson_id), $the_title);
	}
	
	return ($the_title);
}


// Called by WordPress to process content
function screenstepslive_parseContent($the_content)
{	
	if (strpos($the_content, '{{SCREENSTEPSLIVE_CONTENT}}') !== FALSE ) {
		$text = '';
		
		$manual_id = intval($_GET['manual_id']);
		$lesson_id = intval($_GET['lesson_id']);
		
		// Include necessary SS Live files
		$sslivewp = screenstepslive_initializeObject();
		
		if (!$manual_id > 0)
		{
			$text = $sslivewp->GetManualsList();
			
		} else if ($manual_id > 0 && $lesson_id == 0) {
			$text = $sslivewp->GetManualList($manual_id);
			
			$the_content = str_ireplace('{{SCREENSTEPSLIVE_LINK_TO_MANUALS_INDEX}}', $sslivewp->GetLinkToManualIndex(), $the_content);
			$the_content = str_ireplace('{{SCREENSTEPSLIVE_MANUAL_TITLE}}', $sslivewp->GetManualTitle($manual_id), $the_content);
		
		}  else if ($manual_id > 0 && $lesson_id > 0) {
			$text = $sslivewp->GetLessonHTML($manual_id, $lesson_id);
			
			$the_content = str_ireplace('{{SCREENSTEPSLIVE_LINK_TO_MANUALS_INDEX}}', $sslivewp->GetLinkToManualIndex(), $the_content);
			$the_content = str_ireplace('{{SCREENSTEPSLIVE_LINK_TO_MANUAL}}', $sslivewp->GetLinkToManual($manual_id), $the_content);
			$the_content = str_ireplace('{{SCREENSTEPSLIVE_MANUAL_TITLE}}', $sslivewp->GetManualTitle($manual_id), $the_content);
			$the_content = str_ireplace('{{SCREENSTEPSLIVE_PREV_LESSON_TITLE}}', $sslivewp->GetPrevLessonTitle($manual_id, $lesson_id), $the_content);
			$the_content = str_ireplace('{{SCREENSTEPSLIVE_NEXT_LESSON_TITLE}}', $sslivewp->GetNextLessonTitle($manual_id, $lesson_id), $the_content);
			
			// Prev lesson link
			$result = preg_match('/{{SCREENSTEPSLIVE_LINK_TO_PREV_LESSON text=&#8221;(.*?)&#8221;}}/', $the_content, $matches);
			if ($result) {
				$the_content = preg_replace('/{{SCREENSTEPSLIVE_LINK_TO_PREV_LESSON text=&#8221;(.*?)&#8221;}}/', 
								$sslivewp->GetLinkToPrevLesson($manual_id, $lesson_id, $matches[1]), $the_content);
			}
			
			// Next lesson link
			$result = preg_match('/{{SCREENSTEPSLIVE_LINK_TO_NEXT_LESSON text=&#8221;(.*?)&#8221;}}/', $the_content, $matches);
			if ($result) {
				$the_content = preg_replace('/{{SCREENSTEPSLIVE_LINK_TO_NEXT_LESSON text=&#8221;(.*?)&#8221;}}/', 
								$sslivewp->GetLinkToNextLesson($manual_id, $lesson_id, $matches[1]), $the_content);
			}
		}
	}
	
	if ($text != '') $the_content = str_ireplace('{{SCREENSTEPSLIVE_CONTENT}}', $text, $the_content);
	
    return $the_content;
}


function screenstepslive_checkIfDeletedPostIsReferenced($postID) {
	if ($postID == get_option('screenstepslive_manuals_index_post_id'))
		update_option('screenstepslive_manuals_index_post_id', '');
	else if ($postID == get_option('screenstepslive_manual_post_id'))
		update_option('screenstepslive_manual_post_id', '');
	else if ($postID == get_option('screenstepslive_lesson_post_id'))
		update_option('screenstepslive_lesson_post_id', '');
}


// Use to replace page title
//$the_content = str_ireplace('{{SCREENSTEPSLIVE_LESSON_TITLE}}', $sslivewp->GetLessonTitle($manual_id, $lesson_id), $the_content);

// Add admin page
function screenstepslive_addPages()
{
	add_options_page('ScreenSteps Live Options', 'ScreenSteps Live', 8, __FILE__, 'screenstepslive_optionPage');
}


// Shows Admin page
function screenstepslive_optionPage()
{
	$sslivewp = screenstepslive_initializeObject();
	
	// API form was submitted
	if ($_POST['api_submitted'] == 1) {
		update_option('screenstepslive_domain', $_POST['domain']);
		update_option('screenstepslive_api_key', $_POST['api_key']);
		update_option('screenstepslive_protocol', $_POST['protocol']);
	}
	
	if ($_POST['post_ids_submitted'] == 1) {
		update_option('screenstepslive_manuals_index_post_id', $_POST['manuals_index_post_id']);
		update_option('screenstepslive_manual_post_id', $_POST['manual_post_id']);
		update_option('screenstepslive_lesson_post_id', $_POST['lesson_post_id']);
	}
	
	// Manuals form was subbmited
	if ($_POST['manuals_submitted'] == 1) {
		$manual_settings = array();
		
		// Array has keys that are ids of visible lessons. Value is permissions.
		if (is_array($_POST['manual_visible'])) {
			foreach($_POST['manual_visible'] as $manual_id => $value) {
				$manual_settings[$manual_id] = $_POST['manual_permission'][$manual_id];
			}
		}
		
		update_option('screenstepslive_manual_settings', serialize($manual_settings));
	}
	
	// Create template pages
	if (isset($_GET['ssliveaction'])) {
		switch ($_GET['ssliveaction']) {
			case 'create_manual_index_page':
				// Don't process resubmitted pages
				if (intval(get_option('screenstepslive_manuals_index_post_id')) < 1) {
					$postID = screenstepslive_createTemplatePage('manuals');
					if (intval($postID) > 0) {
						update_option('screenstepslive_manuals_index_post_id', $postID);
					}
				}
				break;
			case 'create_manual_page':
				if (intval(get_option('screenstepslive_manual_post_id')) < 1) {
					$postID = screenstepslive_createTemplatePage('manual');
					if (intval($postID) > 0) {
						update_option('screenstepslive_manual_post_id', $postID);
					}
				}
				break;
			case 'create_lesson_page':
				if (intval(get_option('screenstepslive_lesson_post_id')) < 1) {
					$postID = screenstepslive_createTemplatePage('lesson');
					if (intval($postID) > 0) {
						update_option('screenstepslive_lesson_post_id', $postID);
					}
				}
				break;
		}
	}
	
	// UI			
echo <<<END
<div class="wrap">
	<h2>ScreenSteps Live</h2>
	<br />
	<fieldset class="options">
		<legend>ScreenSteps Live API Information</legend>		
END;
			
			// Print API info
			$http_option = get_option('screenstepslive_protocol') == 'http' ? ' selected="selected"' : '';
			$https_option = get_option('screenstepslive_protocol') == 'https' ? ' selected="selected"' : '';
			
			print ('<form method="post" action="' . GETENV('REQUEST_URI') . '">' . "\n");
			print ('<input type="hidden" name="api_submitted" value="1">' . "\n");
			print ('<table class="optiontable form-table">');
			print ('<tr><th scope="row" style="width:200px;">ScreenSteps Live Domain:</th><td>' . 
					'<input type="text" name="domain" id="domain" style="width:20em;" value="'. get_option('screenstepslive_domain') . '"></td></tr>');
			print ('<tr><th scope="row">ScreenSteps Live API Key:</th><td>' . 
					'<input type="text" name="api_key" id="api_key" value="'. get_option('screenstepslive_api_key') . '"></td></tr>');
			print ('<tr><th scope="row">ScreenSteps Live Domain:</th><td>' . 
					'<select name="protocol"><option value="http"'. $http_option . '">HTTP</option>' . 
					'<option value="https"'. $https_option . '">HTTPS</option></select>' .
					'</td></tr>');
			print ('</table>');

echo <<<END
			<div class="submit">
				<input type="submit" id="submit_api_settings" value="Save ScreenSteps Live API Settings" />
			</div>
		</form>
	</fieldset>
	
	<br />
	
	<fieldset class="options">
			<legend>WordPress Page Settings</legend>
END;
			
			
			// Print Page ID settings.
			print ('<form method="post" action="' . GETENV('REQUEST_URI') . '">' . "\n");
			print ('<input type="hidden" name="post_ids_submitted" value="1">' . "\n");
			print ('<table class="optiontable form-table">');
			print ('<tr><th scope="row" style="width:200px;">Manuals Index Page ID:</th><td width="30px">' . 
					'<input type="text" name="manuals_index_post_id" id="manuals_index_post_id" value="'. get_option('screenstepslive_manuals_index_post_id') . '"></td>');
			print ('<td>');
			if (intval(get_option('screenstepslive_manuals_index_post_id')) < 1) {
				print ('Enter page id or <a href="' . GETENV('REQUEST_URI') . '&ssliveaction=create_manual_index_page">Create Manual Index Page</a>');
			}
			print ('</td>');
			print ('</td></tr>');
			print ('<tr><th scope="row">Manual Page ID:</th><td width="30px">' . 
					'<input type="text" name="manual_post_id" id="manual_post_id" value="'. get_option('screenstepslive_manual_post_id') . '"></td>');
			print ('<td>');
			if (intval(get_option('screenstepslive_manual_post_id')) < 1) {
				print ('Enter page id or <a href="' . GETENV('REQUEST_URI') . '&ssliveaction=create_manual_page">Create Manual Page</a>');
			}
			print ('</td>');
			print ('</td></tr>');
			print ('<tr><th scope="row">Lesson Page ID:</th><td width="30px">' . 
					'<input type="text" name="lesson_post_id" id="lesson_post_id" value="'. get_option('screenstepslive_lesson_post_id') . '"></td>');
			print ('<td>');
			if (intval(get_option('screenstepslive_lesson_post_id')) < 1) {
				print ('Enter page id or <a href="' . GETENV('REQUEST_URI') . '&ssliveaction=create_lesson_page">Create Lesson Page</a>');
			}
			print ('</td>');
			print ('</tr>');
			print ('</table>');
			
echo <<<END
			<div class="submit">
				<input type="submit" id="submit_post_id_settings" value="Save WordPress Page Settings"/>
			</div>
		</form>
	</fieldset>
	<br />
	<fieldset class="options">
			<legend>ScreenSteps Live Manuals</legend>
END;
			
			
	$xmlobject = $sslivewp->GetManuals(false);
	if ($xmlobject) {
		if (count($xmlobject) == 0) {
			print "<div>No manuals were returned from the ScreenSteps Live server.</div>";
		} else {
			// Get stored settings
			$manual_settings = unserialize(get_option('screenstepslive_manual_settings'));
			
			// Print manual setings		
			print ('<form method="post" action="' . GETENV('REQUEST_URI') . '">' . "\n");
			print ('<input type="hidden" name="manuals_submitted" value="1">' . "\n");
			print ('<table class="optiontable form-table">' . "\n");
			print ('<tr>' . "\n");
			print ('<th scope="column" style="width:10px;">Active</th>' . "\n");
			print ('<th scope="column">Manual</th>' . "\n");
			print ('<th scope="column">Permissions</th>' . "\n");
			print ('</tr>' . "\n");
			
			foreach ($xmlobject as $manual) {
				// Determine initial state for visible checkbox and permission settings.
				$manual_id = intval($manual->id); // arrays don't like object props as key refs
				$checked = ($manual_settings[$manual_id] != '') ? ' checked' : '';
				$private_option = '';
				$public_option = '';
				if ($checked == ' checked') {
					$private_option = $manual_settings[$manual_id] == 'private' ? ' selected="selected"' : '';
					$public_option = $manual_settings[$manual_id] == 'public' ? ' selected="selected"' : '';
					$everyone_option = $manual_settings[$manual_id] == 'everyone' ? ' selected="selected"' : '';
				}
				
				print ("<tr>\n");
				print ('<td><input type="checkbox" name="manual_visible[' . $manual->id . ']"' . $checked . '></td>' . "\n");
				print ('<td>' . $manual->title . "</td>\n");
				print ('<td><select name="manual_permission[' . $manual->id . ']"><option value="private"' . $private_option . 
						'>Private</option><option value="public"' . $public_option . '>Public</option>' . 
						'<option value="everyone"' . $everyone_option . '>Everyone</option></select></td>' . "\n");
				print ("</tr>\n");
			}
			print ("</table>\n");

echo <<<END
			<div class="submit">
				<input type="submit" id="submit_manual_settings" value="Save ScreenSteps Live Manual Permissions"/>
			</div>
		</form>
END;

		}
	} else {
		print ("<div>Error:" . $this->last_error . "</div>\n");
	}

echo <<<END
	</fieldset>
	<br />
END;
}


function screenstepslive_createTemplatePage($type) {
		if (!current_user_can( 'edit_others_pages' )) {
			return new WP_Error( 'edit_others_pages', __( 'You are not allowed to create pages as this user.' ) );
		}
	
		$user = wp_get_current_user();
	
		$post['post_author'] = $user->id;
		$post['post_type'] = 'page';
		$post['post_status'] = 'draft';
		$post['comment_status'] = 'closed';
		$post['ping_status'] = 'closed';
		
		switch($type) {
			case 'manuals':
				$post['post_title'] = 'Manuals';
				$post['post_content'] = '{{SCREENSTEPSLIVE_CONTENT}}';
				break;
				
			case 'manual':
				$post['post_title'] = '{{SCREENSTEPSLIVE_MANUAL_TITLE}}';
				$post['post_content'] = '{{SCREENSTEPSLIVE_CONTENT}}' . "\n" .
										'<a href="{{SCREENSTEPSLIVE_LINK_TO_MANUALS_INDEX}}">Return to index</a>';
				break;
				
			case 'lesson':
				$post['post_title'] = '{{SCREENSTEPSLIVE_LESSON_TITLE}}';
				$post['post_content'] = '{{SCREENSTEPSLIVE_CONTENT}}' . "\n" .
										'{{SCREENSTEPSLIVE_LINK_TO_PREV_LESSON text="Previous Lesson: {{SCREENSTEPSLIVE_PREV_LESSON_TITLE}}"}}' . "\n" .
										'{{SCREENSTEPSLIVE_LINK_TO_NEXT_LESSON text="Next Lesson: {{SCREENSTEPSLIVE_NEXT_LESSON_TITLE}}"}}' . "\n" .
										'<a href="{{SCREENSTEPSLIVE_LINK_TO_MANUAL}}">Return to Manual</a>';
				break;
		}
		
		$postID = wp_insert_post($post);
		
		if (is_wp_error($postID))
			return $post_ID;
	
		if (empty($postID))
			return 0;
			
		return $postID;
	}

//echo <<<END
			
//END;

?>