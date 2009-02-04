<?php
/*
Plugin Name: ScreenSteps Live
Plugin URI: http://screensteps.com/blog/2008/07/screensteps-live-wordpress-plugin/
Description: This plugin will incorporate lessons from your ScreenSteps Live account into your WordPress Pages.
Version: 0.9.5
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
		if (get_option('screenstepslive_domain') == '' && get_option('screenstepslive_reader_name') == '') {
			update_option('screenstepslive_domain', 'example.screenstepslive.com');
			update_option('screenstepslive_reader_name', 'example');
			update_option('screenstepslive_reader_password', 'example');
			update_option('screenstepslive_protocol', 'http');
		}
		
		require_once(dirname(__FILE__) . '/sslivewordpress_class.php');
		
		// Create ScreenSteps Live object using your domain and API key
		$screenstepslivewp = new SSLiveWordPress(get_option('screenstepslive_domain'),
										get_option('screenstepslive_protocol'));
		$screenstepslivewp->SetUserCredentials(get_option('screenstepslive_reader_name'), get_option('screenstepslive_reader_password'));
		//$screenstepslivewp->show_protected = true;
		$screenstepslivewp->spaces_settings = get_option('screenstepslive_spaces_settings');
		//$screenstepslivewp->manual_settings = get_option('screenstepslive_manual_settings');
		$screenstepslivewp->user_can_read_private = current_user_can('read_private_posts') == 1;
		
		$screenstepslivewp->spaces_index_post_id = get_option('screenstepslive_spaces_index_post_id');
		$screenstepslivewp->space_post_id = get_option('screenstepslive_space_post_id');
		$screenstepslivewp->manual_post_id = get_option('screenstepslive_manual_post_id');
		$screenstepslivewp->bucket_post_id = get_option('screenstepslive_bucket_post_id');
		$screenstepslivewp->lesson_post_id = get_option('screenstepslive_lesson_post_id'); // For manuals. We used this before spaces.
		$screenstepslivewp->bucket_lesson_post_id = get_option('screenstepslive_bucket_lesson_post_id');
	}
	
	// Any caller will just get a reference to this object.
	return $screenstepslivewp;
}


function screenstepslive_parseTitle($the_title) {
	if (strpos($the_title, '{{SCREENSTEPSLIVE_SPACE_TITLE}}') !== FALSE ) {
		$sslivewp = screenstepslive_initializeObject();
				
		$space_id = intval($_GET['space_id']);
		if ($space_id > 0)
			$the_title = preg_replace('/{{SCREENSTEPSLIVE_SPACE_TITLE}}/i', $sslivewp->GetSpaceTitle($space_id), $the_title);
		
	} else if (strpos($the_title, '{{SCREENSTEPSLIVE_MANUAL_TITLE}}') !== FALSE ) {
		$sslivewp = screenstepslive_initializeObject();
		
		$space_id = intval($_GET['space_id']);
		$manual_id = intval($_GET['manual_id']);
		if ($manual_id > 0)
			$the_title = preg_replace('/{{SCREENSTEPSLIVE_MANUAL_TITLE}}/i', $sslivewp->GetManualTitle($space_id, $manual_id), $the_title);
			
	} else if (strpos($the_title, '{{SCREENSTEPSLIVE_BUCKET_TITLE}}') !== FALSE ) {
		$sslivewp = screenstepslive_initializeObject();
		
		$space_id = intval($_GET['space_id']);
		$bucket_id = intval($_GET['bucket_id']);
		if ($bucket_id > 0)
			$the_title = preg_replace('/{{SCREENSTEPSLIVE_BUCKET_TITLE}}/i', $sslivewp->GetBucketTitle($space_id, $bucket_id), $the_title);
		
	} else if (strpos($the_title, '{{SCREENSTEPSLIVE_LESSON_TITLE}}') !== FALSE ) {
		$sslivewp = screenstepslive_initializeObject();
		$space_id = intval($_GET['space_id']);
		$manual_id = intval($_GET['manual_id']);
		$bucket_id = intval($_GET['bucket_id']);
		$lesson_id = intval($_GET['lesson_id']);
		if ($manual_id > 0 && $lesson_id > 0)
			$the_title = preg_replace('/{{SCREENSTEPSLIVE_LESSON_TITLE}}/i', $sslivewp->GetManualLessonTitle($space_id, $manual_id, $lesson_id), $the_title);
		else if ($bucket_id > 0 && $lesson_id > 0)
			$the_title = preg_replace('/{{SCREENSTEPSLIVE_LESSON_TITLE}}/i', $sslivewp->GetBucketLessonTitle($space_id, $bucket_id, $lesson_id), $the_title);
	}
	
	return ($the_title);
}


// Called by WordPress to process content
function screenstepslive_parseContent($the_content)
{	
	if (stristr($the_content, '{{SCREENSTEPSLIVE_CONTENT}}') !== FALSE) {
		$text = '';
		
		$space_id = intval($_GET['space_id']);
		$bucket_id = intval($_GET['bucket_id']);
		$manual_id = intval($_GET['manual_id']);
		$lesson_id = intval($_GET['lesson_id']);
				
		// Include necessary SS Live files
		$sslivewp = screenstepslive_initializeObject();
		
		if (!$space_id > 0)
		{
			// Retrieve list of all spaces
			$text = $sslivewp->GetSpacesList();
			
		}  else if ($space_id > 0 && $lesson_id > 0) {
			if ($manual_id > 0) {
				$text = $sslivewp->GetLessonHTML($space_id, 'manual', $manual_id, $lesson_id);
				$the_content = preg_replace('/{{SCREENSTEPSLIVE_LINK_TO_MANUAL}}/i', $sslivewp->GetLinkToManual($space_id, $manual_id), $the_content);
				$the_content = preg_replace('/{{SCREENSTEPSLIVE_MANUAL_TITLE}}/i', $sslivewp->GetManualTitle($space_id, $manual_id), $the_content);
				$the_content = preg_replace('/{{SCREENSTEPSLIVE_PREV_LESSON_TITLE}}/i', $sslivewp->GetPrevLessonTitle($space_id, 'manual', $manual_id, $lesson_id), $the_content);
				$the_content = preg_replace('/{{SCREENSTEPSLIVE_NEXT_LESSON_TITLE}}/i', $sslivewp->GetNextLessonTitle($space_id, 'manual', $manual_id, $lesson_id), $the_content);
			} else if ($bucket_id > 0) {
				$text = $sslivewp->GetLessonHTML($space_id, 'bucket', $bucket_id, $lesson_id);
				$the_content = preg_replace('/{{SCREENSTEPSLIVE_LINK_TO_BUCKET}}/i', $sslivewp->GetLinkToBucket($space_id, $bucket_id), $the_content);
				$the_content = preg_replace('/{{SCREENSTEPSLIVE_BUCKET_TITLE}}/i', $sslivewp->GetBucketTitle($space_id, $bucket_id), $the_content);
			}
			
			$the_content = preg_replace('/{{SCREENSTEPSLIVE_LINK_TO_SPACES_INDEX}}/i', $sslivewp->GetLinkToSpacesIndex(), $the_content);
			
			if ($manual_id > 0) {
				// Prev lesson link
				$result = preg_match('/{{SCREENSTEPSLIVE_LINK_TO_PREV_LESSON text=(?:&#8221;|&quot;)(.*?)(?:&#8221;|&quot;)}}/i', $the_content, $matches);
				if ($result) {
					$the_content = preg_replace('/{{SCREENSTEPSLIVE_LINK_TO_PREV_LESSON text=(?:&#8221;|&quot;)(.*?)(?:&#8221;|&quot;)}}/i', 
									$sslivewp->GetLinkToPrevLesson($space_id, 'manual', $manual_id, $lesson_id, $matches[1]), $the_content);
				}
				
				// Next lesson link
				$result = preg_match('/{{SCREENSTEPSLIVE_LINK_TO_NEXT_LESSON text=(?:&#8221;|&quot;)(.*?)(?:&#8221;|&quot;)}}/i', $the_content, $matches);
				if ($result) {
					$the_content = preg_replace('/{{SCREENSTEPSLIVE_LINK_TO_NEXT_LESSON text=(?:&#8221;|&quot;)(.*?)(?:&#8221;|&quot;)}}/i', 
									$sslivewp->GetLinkToNextLesson($space_id, 'manual', $manual_id, $lesson_id, $matches[1]), $the_content);
				}
			}
			
		} else if ($space_id > 0 && $manual_id > 0) {
			$text = $sslivewp->GetManualList($space_id, $manual_id);
						
			$the_content = preg_replace('/{{SCREENSTEPSLIVE_LINK_TO_SPACES_INDEX}}/i', $sslivewp->GetLinkToSpacesIndex(), $the_content);
			$the_content = preg_replace('/{{SCREENSTEPSLIVE_LINK_TO_SPACE}}/i', $sslivewp->GetLinkToSpace($space_id), $the_content);
			$the_content = preg_replace('/{{SCREENSTEPSLIVE_SPACE_TITLE}}/i', $sslivewp->GetSpaceTitle($space_id), $the_content);
			$the_content = preg_replace('/{{SCREENSTEPSLIVE_MANUAL_TITLE}}/i', $sslivewp->GetManualTitle($space_id, $manual_id), $the_content);
		
		} else if ($space_id > 0 && $bucket_id > 0) {
			$text = $sslivewp->GetBucketList($space_id, $bucket_id);
			$the_content = preg_replace('/{{SCREENSTEPSLIVE_LINK_TO_SPACES_INDEX}}/i', $sslivewp->GetLinkToSpacesIndex(), $the_content);
			$the_content = preg_replace('/{{SCREENSTEPSLIVE_LINK_TO_SPACE}}/i', $sslivewp->GetLinkToSpace($space_id), $the_content);
			$the_content = preg_replace('/{{SCREENSTEPSLIVE_SPACE_TITLE}}/i', $sslivewp->GetSpaceTitle($space_id), $the_content);
			$the_content = preg_replace('/{{SCREENSTEPSLIVE_BUCKET_TITLE}}/i', $sslivewp->GetBucketTitle($space_id, $bucket_id), $the_content);

		} else {
			$text = $sslivewp->GetSpaceList($space_id);
			$the_content = preg_replace('/{{SCREENSTEPSLIVE_LINK_TO_SPACES_INDEX}}/i', $sslivewp->GetLinkToSpacesIndex(), $the_content);
			$the_content = preg_replace('/{{SCREENSTEPSLIVE_SPACE_TITLE}}/i', $sslivewp->GetSpaceTitle($space_id), $the_content);
		}
	}
	
	if ($text != '') $the_content = preg_replace('/{{SCREENSTEPSLIVE_CONTENT}}/i', $text, $the_content);
	
    return $the_content;
}


function screenstepslive_checkIfDeletedPostIsReferenced($postID) {
	if ($postID == get_option('screenstepslive_spaces_index_post_id'))
		update_option('screenstepslive_spaces_index_post_id', '');
	else if ($postID == get_option('screenstepslive_space_post_id'))
		update_option('screenstepslive_space_post_id', '');
	else if ($postID == get_option('screenstepslive_manual_post_id'))
		update_option('screenstepslive_manual_post_id', '');
	else if ($postID == get_option('screenstepslive_bucket_post_id'))
		update_option('screenstepslive_bucket_post_id', '');
	else if ($postID == get_option('screenstepslive_lesson_post_id'))
		update_option('screenstepslive_lesson_post_id', '');
	else if ($postID == get_option('screenstepslive_bucket_lesson_post_id'))
		update_option('screenstepslive_bucket_lesson_post_id', '');
}


// Use to replace page title
//$the_content = preg_replace('/{{SCREENSTEPSLIVE_LESSON_TITLE}}/i', $sslivewp->GetLessonTitle($manual_id, $lesson_id), $the_content);

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
		update_option('screenstepslive_reader_name', $_POST['reader_name']);
		update_option('screenstepslive_reader_password', $_POST['reader_password']);
		update_option('screenstepslive_protocol', $_POST['protocol']);
		
		$sslivewp->SetUserCredentials($_POST['reader_name'], $_POST['reader_password']);
		$sslivewp->protocol = $_POST['protocol'];
	}
	
	if ($_POST['post_ids_submitted'] == 1) {
		update_option('screenstepslive_spaces_index_post_id', $_POST['spaces_index_post_id']);
		update_option('screenstepslive_space_post_id', $_POST['space_post_id']);
		update_option('screenstepslive_manual_post_id', $_POST['manual_post_id']);
		update_option('screenstepslive_bucket_post_id', $_POST['bucket_post_id']);
		update_option('screenstepslive_lesson_post_id', $_POST['lesson_post_id']);
		update_option('screenstepslive_bucket_lesson_post_id', $_POST['bucket_lesson_post_id']);
	}
	
	// Manuals form was subbmited
	if ($_POST['spaces_submitted'] == 1) {
		$spaces_settings = array();
		
		// Array has keys that are ids of visible lessons. Value is permissions.
		if (is_array($_POST['space_visible'])) {
			foreach($_POST['space_visible'] as $space_id => $value) {
				$spaces_settings[$space_id] = $_POST['space_permission'][$space_id];
			}
		}
		
		update_option('screenstepslive_spaces_settings', $spaces_settings);
	}
	
	// Create template pages
	if (isset($_GET['ssliveaction'])) {
		switch ($_GET['ssliveaction']) {
			case 'create_spaces_index_page':
				// Don't process resubmitted pages
				if (intval(get_option('screenstepslive_spaces_index_post_id')) < 1) {
					$postID = screenstepslive_createTemplatePage('spaces');
					if (intval($postID) > 0) {
						update_option('screenstepslive_spaces_index_post_id', $postID);
					}
				}
				break;
			case 'create_space_page':
				if (intval(get_option('screenstepslive_space_post_id')) < 1) {
					$postID = screenstepslive_createTemplatePage('space');
					if (intval($postID) > 0) {
						update_option('screenstepslive_space_post_id', $postID);
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
			case 'create_bucket_page':
				if (intval(get_option('screenstepslive_bucket_post_id')) < 1) {
					$postID = screenstepslive_createTemplatePage('bucket');
					if (intval($postID) > 0) {
						update_option('screenstepslive_bucket_post_id', $postID);
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
			case 'create_bucket_lesson_page':
				if (intval(get_option('screenstepslive_bucket_lesson_post_id')) < 1) {
					$postID = screenstepslive_createTemplatePage('bucket lesson');
					if (intval($postID) > 0) {
						update_option('screenstepslive_bucket_lesson_post_id', $postID);
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
			/*print ('<tr><th scope="row">ScreenSteps Live API Key:</th><td>' . 
					'<input type="text" name="api_key" id="api_key" value="'. get_option('screenstepslive_api_key') . '"></td></tr>');
			*/
			print ('<tr><th scope="row">ScreenSteps Live Reader Account username:</th><td>' . 
					'<input type="text" name="reader_name" id="reader_name" value="'. get_option('screenstepslive_reader_name') . '"></td></tr>');
			print ('<tr><th scope="row">ScreenSteps Live Reader Account password:</th><td>' . 
					'<input type="password" name="reader_password" id="reader_password" value="'. get_option('screenstepslive_reader_password') . '"></td></tr>');
			print ('<tr><th scope="row">Protocol:</th><td>' . 
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
			print ('<tr><th scope="row" style="width:200px;">Spaces Index Page ID:</th><td width="30px">' . 
					'<input type="text" name="spaces_index_post_id" id="spaces_index_post_id" value="'. get_option('screenstepslive_spaces_index_post_id') . '"></td>');
			print ('<td>');
			if (intval(get_option('screenstepslive_spaces_index_post_id')) < 1) {
				print ('Enter page id or <a href="' . GETENV('REQUEST_URI') . '&ssliveaction=create_spaces_index_page">Create Spaces Index Page</a>');
			}
			print ('</td>');
			print ('</td></tr>');
			print ('<tr><th scope="row">Space Page ID:</th><td width="30px">' . 
					'<input type="text" name="space_post_id" id="space_post_id" value="'. get_option('screenstepslive_space_post_id') . '"></td>');
			print ('<td>');
			if (intval(get_option('screenstepslive_space_post_id')) < 1) {
				print ('Enter page id or <a href="' . GETENV('REQUEST_URI') . '&ssliveaction=create_space_page">Create Space Page</a>');
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
			print ('<tr><th scope="row">Bucket Page ID:</th><td width="30px">' . 
					'<input type="text" name="bucket_post_id" id="bucket_post_id" value="'. get_option('screenstepslive_bucket_post_id') . '"></td>');
			print ('<td>');
			if (intval(get_option('screenstepslive_bucket_post_id')) < 1) {
				print ('Enter page id or <a href="' . GETENV('REQUEST_URI') . '&ssliveaction=create_bucket_page">Create Bucket Page</a>');
			}
			print ('</td>');
			print ('</td></tr>');
			print ('<tr><th scope="row">Manual Lesson Page ID:</th><td width="30px">' . 
					'<input type="text" name="lesson_post_id" id="lesson_post_id" value="'. get_option('screenstepslive_lesson_post_id') . '"></td>');
			print ('<td>');
			if (intval(get_option('screenstepslive_lesson_post_id')) < 1) {
				print ('Enter page id or <a href="' . GETENV('REQUEST_URI') . '&ssliveaction=create_lesson_page">Create Manual Lesson Page</a>');
			}
			print ('</td>');
			print ('</tr>');
			print ('<tr><th scope="row">Bucket Lesson Page ID:</th><td width="30px">' . 
					'<input type="text" name="bucket_lesson_post_id" id="bucket_lesson_post_id" value="'. get_option('screenstepslive_bucket_lesson_post_id') . '"></td>');
			print ('<td>');
			if (intval(get_option('screenstepslive_bucket_lesson_post_id')) < 1) {
				print ('Enter page id or <a href="' . GETENV('REQUEST_URI') . '&ssliveaction=create_bucket_lesson_page">Create Bucket Lesson Page</a>');
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
			<legend>ScreenSteps Live Spaces</legend>
END;
			
			
	$array = $sslivewp->GetSpaces();
	if ($array) {
		if (count($array['space']) == 0) {
			print "<div>No spaces were returned from the ScreenSteps Live server.</div>";
		} else {
			// Get stored settings
			$spaces_settings = get_option('screenstepslive_spaces_settings');
			
			// Print manual setings		
			print ('<form method="post" action="' . GETENV('REQUEST_URI') . '">' . "\n");
			print ('<input type="hidden" name="spaces_submitted" value="1">' . "\n");
			print ('<table class="optiontable form-table">' . "\n");
			print ('<tr>' . "\n");
			print ('<th scope="column" style="width:10px;">Active</th>' . "\n");
			print ('<th scope="column">Space</th>' . "\n");
			print ('<th scope="column">Permissions</th>' . "\n");
			print ('</tr>' . "\n");
			
			foreach ($array['space'] as $key => $space) {
				// Determine initial state for visible checkbox and permission settings.
				$space_id = intval($space['id']); // arrays don't like object props as key refs
				$checked = ($spaces_settings[$space_id] != '') ? ' checked' : '';
				$private_option = '';
				$public_option = '';
				$everyone_option = '';
				if ($checked == ' checked') {
					$private_option = $spaces_settings[$space_id] == 'private' ? ' selected="selected"' : '';
					$public_option = $spaces_settings[$space_id] == 'public' ? ' selected="selected"' : '';
					$everyone_option = $spaces_settings[$space_id] == 'everyone' ? ' selected="selected"' : '';
				}
				
				print ("<tr>\n");
				print ('<td><input type="checkbox" name="space_visible[' . $space_id . ']"' . $checked . '></td>' . "\n");
				print ('<td>' . $space['title'] . "</td>\n");
				print ('<td><select name="space_permission[' . $space_id . ']"><option value="private"' . $private_option . 
						'>Private</option><option value="public"' . $public_option . '>Public</option>' . 
						'<option value="everyone"' . $everyone_option . '>Everyone</option></select></td>' . "\n");
				print ("</tr>\n");
			}
			print ("</table>\n");

echo <<<END
			<div class="submit">
				<input type="submit" id="submit_spaces_settings" value="Save ScreenSteps Live Manual Permissions"/>
			</div>
		</form>
END;

		}
	} else {
		print ("<div>Error:" . $sslivewp->last_error . "</div>\n");
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
			case 'spaces':
				$post['post_title'] = 'Spaces';
				$post['post_content'] = '{{SCREENSTEPSLIVE_CONTENT}}';
				break;
			
			case 'space':
				$post['post_title'] = '{{SCREENSTEPSLIVE_SPACE_TITLE}}';
				$post['post_content'] = '{{SCREENSTEPSLIVE_CONTENT}}' . "\n" .
										'<a href="{{SCREENSTEPSLIVE_LINK_TO_SPACES_INDEX}}">Return to spaces</a>';
				break;
				
			case 'manual':
				$post['post_title'] = '{{SCREENSTEPSLIVE_MANUAL_TITLE}}';
				$post['post_content'] = '{{SCREENSTEPSLIVE_CONTENT}}' . "\n" .
										'<a href="{{SCREENSTEPSLIVE_LINK_TO_SPACE}}">Return to space</a>';
				break;
			
			case 'bucket':
				$post['post_title'] = '{{SCREENSTEPSLIVE_BUCKET_TITLE}}';
				$post['post_content'] = '{{SCREENSTEPSLIVE_CONTENT}}' . "\n" .
										'<a href="{{SCREENSTEPSLIVE_LINK_TO_SPACE}}">Return to space</a>';
				break;
				
			case 'lesson':
				$post['post_title'] = '{{SCREENSTEPSLIVE_LESSON_TITLE}}';
				$post['post_content'] = '{{SCREENSTEPSLIVE_CONTENT}}' . "\n" .
										'{{SCREENSTEPSLIVE_LINK_TO_PREV_LESSON text="Previous Lesson: {{SCREENSTEPSLIVE_PREV_LESSON_TITLE}}"}}' . "\n" .
										'{{SCREENSTEPSLIVE_LINK_TO_NEXT_LESSON text="Next Lesson: {{SCREENSTEPSLIVE_NEXT_LESSON_TITLE}}"}}' . "\n" .
										'<a href="{{SCREENSTEPSLIVE_LINK_TO_MANUAL}}">Return to Manual</a>';
				break;
			case 'bucket lesson':
				$post['post_title'] = '{{SCREENSTEPSLIVE_LESSON_TITLE}}';
				$post['post_content'] = '{{SCREENSTEPSLIVE_CONTENT}}' . "\n" .
										'<a href="{{SCREENSTEPSLIVE_LINK_TO_BUCKET}}">Return to Lesson Bucket</a>';
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