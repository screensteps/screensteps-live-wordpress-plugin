<?php
/*
Plugin Name: ScreenSteps Live
Plugin URI: http://screensteps.com/blog/2008/07/screensteps-live-wordpress-plugin/
Description: This plugin will incorporate lessons from your ScreenSteps Live account into your WordPress Pages.
Version: 1.0.5
Author: Blue Mango Learning Systems
Author URI: http://www.screensteps.com
*/

//$result = error_reporting(E_ERRORÊ|ÊE_WARNINGÊ|ÊE_PARSEÊ|ÊE_NOTICE);

// Global var for SSLiveWordPress object. It is shared among multiple wp callbacks.
$screenstepslivewp = NULL;


// This plugin processes content of all posts
add_action('wp_head', 'screenstepslive_addHeader', 100);
add_filter('the_content', 'screenstepslive_parseContent', 100);
add_filter('the_title', 'screenstepslive_parseTitle', 100);
add_filter('wp_list_pages', 'screenstepslive_listPages', 100);
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
			update_option('screenstepslive_pages', '');
		}
		
		require_once(dirname(__FILE__) . '/sslivewordpress_class.php');
		
		// Create ScreenSteps Live object using your domain and API key
		$screenstepslivewp = new SSLiveWordPress(get_option('screenstepslive_domain'),
										get_option('screenstepslive_protocol'));
		$screenstepslivewp->SetUserCredentials(get_option('screenstepslive_reader_name'), get_option('screenstepslive_reader_password'));
		
		$screenstepslivewp->user_can_read_private = current_user_can('read_private_posts') == 1;
	}
	
	// Any caller will just get a reference to this object.
	return $screenstepslivewp;
}


function screenstepslive_addHeader() {
	// Was a comment submitted?
	if ($_POST['screenstepslive_comment_submit'] == 1) {
		////////////
		$sslivewp = screenstepslive_initializeObject();
		
		$postID = $_POST['sslivecommment']['page_id'];
		
		// Find settings for this page
		$pages = get_option('screenstepslive_pages');
		
		foreach ($pages as $key => $page_entry) {
			if ($page_entry['id'] == $postID) {
				$page = $page_entry;
				break;
			}
		}
		
		// Get out if we have nothing to offer.
		if (!isset($page)) wp_die('unable to find page for comment submission');
		
		$space_id = $page['space_id'];
		$manual_id = $sslivewp->CleanseID($_GET['manual_id']);
		$bucket_id = $sslivewp->CleanseID($_GET['bucket_id']);
		if ($page['resource_type'] == 'bucket' && 
			( (is_string($page['resource_id']) && !empty($page['resource_id'])) || (is_int($page['resource_id']) && $page['resource_id'] > 0) )
			)
			
			$bucket_id = $page['resource_id'];
		else if ($page['resource_type'] == 'manual' && 
			( (is_string($page['resource_id']) && !empty($page['resource_id'])) || (is_int($page['resource_id']) && $page['resource_id'] > 0) )
			)
			$manual_id = $page['resource_id'];
		$lesson_id = $sslivewp->CleanseID($_GET['lesson_id']);
		////////////
		
		if ($page['resource_type'] == 'manual') {
			$resource_type = 'manual';
			$resource_id = $manual_id;
		} else {
			$resource_type = 'bucket';
			$resource_id = $bucket_id;
		}
		
		$name = $_POST['sslivecommment']['author'];
		$email = $_POST['sslivecommment']['email'];
		$comment = $_POST['sslivecommment']['comment'];
		
		if (get_magic_quotes_gpc()) {
			$name = stripslashes($name);
			$email = stripslashes($email);
			$comment = stripslashes($comment);
		}
		
		$errors = $sslivewp->SubmitLessonComment($space_id, $resource_type, $resource_id, $lesson_id, 
			$name, $email, $comment, $_POST['sslivecommment']['subscribe']);
		if ( count($errors) > 0 ) {
			foreach ($errors as $key=>$value)
			{
				$error_str .= '<p>' . $value . '</p>';
			}
			wp_die($error_str);
		}
	}

	// CSS
	$plugin_folder = basename(dirname(__FILE__));
	echo '<link type="text/css" rel="stylesheet" href="' . get_bloginfo('wpurl') . '/wp-content/plugins/' . $plugin_folder . '/css/screenstepslive.css" />' . "\n";
}


function screenstepslive_listPages($the_output) {
	// We remove the link to the current SS Live page from the list. It's $title will be rewritten
	// by screenstepslive_parseTitle and since WordPress has one filter for ALL titles we don't have
	// a lot of options.
	$postID = get_the_ID();
	$post = &get_post($postID);
			
	// Find settings for this page
	$pages = get_option('screenstepslive_pages');
	
	foreach ($pages as $key => $page_entry) {
		if ($page_entry['id'] == $post->ID) {
			$page = $page_entry;
			break;
		}
	}
		
	// Get out if we have nothing to offer.
	if (!isset($page)) return ($the_output);
	
	// Include necessary SS Live files
	$sslivewp = screenstepslive_initializeObject();
	
	$space_id = $page['space_id'];
	$manual_id = $sslivewp->CleanseID($_GET['manual_id']);
	$bucket_id = $sslivewp->CleanseID($_GET['bucket_id']);
	if ($page['resource_type'] == 'bucket' && $page['resource_id'] > 0)
		$bucket_id = $page['resource_id'];
	else if ($page['resource_type'] == 'manual' && $page['resource_id'] > 0)
		$manual_id = $page['resource_id'];
	$lesson_id = $sslivewp->CleanseID($_GET['lesson_id']);
	
	
	// What has this page been renamed too?
	if (empty($space_id))
	{
		// nothing to do	
	}  else if (!empty($space_id) && !empty($lesson_id)) {
		if (!empty($manual_id)) {
			if ($page['resource_id'] == 0)
			{
				// Page is a 'space' page.
				$the_title = '<a href="' . $sslivewp->GetLinkToManual($post->ID, $space_id, $manual_id) . '">' . 
								$sslivewp->GetManualTitle($space_id, $manual_id) . '</a>: ' .
							$sslivewp->GetManualLessonTitle($space_id, $manual_id, $lesson_id);			
			} else
			{
				// Page is a 'manual' page.
				$the_title = $sslivewp->GetManualLessonTitle($space_id, $manual_id, $lesson_id);		
			}
		} else if (!empty($bucket_id)) {
			if ($page['resource_id'] == 0)
			{
				// Page is a 'space' page.
				$the_title = '<a href="' . $sslivewp->GetLinkToBucket($post->ID, $space_id, $bucket_id) . '">' . 
								$sslivewp->GetBucketTitle($space_id, $bucket_id) . '</a>: ' .
							$sslivewp->GetBucketLessonTitle($space_id, $bucket_id, $lesson_id);				
			} else
			{
				// Page is a 'manual' page.
				$the_title = $sslivewp->GetBucketLessonTitle($space_id, $bucket_id, $lesson_id);		
			}
		}
			
	} else if (!empty($space_id) && !empty($manual_id)) {
		if ($page['resource_id'] == 0)
		{
			// Page is a 'space' page.
			$the_title = $sslivewp->GetManualTitle($space_id, $manual_id);	
		} else
		{
			// Page is a 'manual' page.
			// Nothing to do.
		}
		
	} else if (!empty($space_id) && !empty($bucket_id)) {		
		if ($page['resource_id'] == 0)
		{
			// Page is a 'space' page.
			$the_title = $sslivewp->GetBucketTitle($space_id, $bucket_id);
		} else
		{
			// Page is a 'bucket' page.
			// Nothing to do.
		}

	} else {
		// Spaces. Not used.
	}
		
	if (empty($the_title) || $the_title == $post->post_title) {
		return ($the_output);
	} else {
		// Now rename the page in the list to the original post title
		$theNewOutput = preg_replace('/>' . preg_quote($the_title, "/") . '\</', '>' . $post->post_title . '<', $the_output, -1, $count);
		if ($count > 0) return $theNewOutput;
		else return ($the_output);
	}
}


function screenstepslive_parseTitle($the_title) {
	if (!is_page( $the_title)) return ($the_title); // cursed wp_list_pages calls this as well.
		
	$postID = get_the_ID();
	$post = &get_post($postID);
				
	// Find settings for this page
	$pages = get_option('screenstepslive_pages');
	
	foreach ($pages as $key => $page_entry) {
		if ($page_entry['id'] == $post->ID) {
			$page = $page_entry;
			break;
		}
	}
		
	// Get out if we have nothing to offer.
	if (!isset($page)) return ($the_title);
	
	// Include necessary SS Live files
	$sslivewp = screenstepslive_initializeObject();
	
	$space_id = $page['space_id'];
	$manual_id = $sslivewp->CleanseID($_GET['manual_id']);
	$bucket_id = $sslivewp->CleanseID($_GET['bucket_id']);
	if ($page['resource_type'] == 'bucket' && $page['resource_id'] > 0)
		$bucket_id = $page['resource_id'];
	else if ($page['resource_type'] == 'manual' && $page['resource_id'] > 0)
		$manual_id = $page['resource_id'];
	$lesson_id = $sslivewp->CleanseID($_GET['lesson_id']);
		
	if (empty($space_id))
	{
		// nothing to do	
	}  else if (!empty($space_id) && !empty($lesson_id)) {
		if (!empty($manual_id)) {
			if ($page['resource_id'] == 0)
			{
				// Default page is a space.
				$the_title = '<a href="' . $sslivewp->GetLinkToManual($post->ID, $space_id, $manual_id) . '">' . 
								$sslivewp->GetManualTitle($space_id, $manual_id) . '</a>: ' .
							$sslivewp->GetManualLessonTitle($space_id, $manual_id, $lesson_id);		
			} else
			{
				// Default page is a manual.
				$the_title = $sslivewp->GetManualLessonTitle($space_id, $manual_id, $lesson_id);		
			}
		} else if (!empty($bucket_id)) {
			if ($page['resource_id'] == 0)
			{
				// Default page is a space.
				$the_title = '<a href="' . $sslivewp->GetLinkToBucket($post->ID, $space_id, $bucket_id) . '">' . 
								$sslivewp->GetBucketTitle($space_id, $bucket_id) . '</a>: ' .
							$sslivewp->GetBucketLessonTitle($space_id, $bucket_id, $lesson_id);		
			} else
			{
				// Default page is a bucket.
				$the_title = $sslivewp->GetBucketLessonTitle($space_id, $bucket_id, $lesson_id);		
			}
		}
			
	} else if (!empty($space_id) && !empty($manual_id)) {
		if ($page['resource_id'] == 0)
		{
			// Default page is a space. Get manual title.
			$the_title = $sslivewp->GetManualTitle($space_id, $manual_id);	
		} else
		{
			// Default page is a manual. Nothing to do.
		}
		
	} else if (!empty($space_id) && !empty($bucket_id)) {
		if ($page['resource_id'] == 0)
		{
			// Default page is a space. Get bucket title.
			$the_title = $sslivewp->GetBucketTitle($space_id, $bucket_id);
		} else
		{
			// Default page is a bucket. Nothing to do.
		}

	} else {
		// Spaces. Not used.
	}
	
	return ($the_title);
}


// Called by WordPress to process content
function screenstepslive_parseContent($the_content)
{	
	$postID = get_the_ID();
	$post = &get_post($postID);

	if (stristr($the_content, '{{SCREENSTEPSLIVE_CONTENT}}') !== FALSE) {
		$text = '';
		$the_content = str_ireplace('<p>', '', $the_content);
		$the_content = str_ireplace('</p>', '', $the_content);
		$the_content = '<div class="screenstepslive">' . "\n" . $the_content . '</div>';
		
		// Find settings for this page
		$pages = get_option('screenstepslive_pages');

		foreach ($pages as $key => $page_entry) {
			if ($page_entry['id'] == $post->ID) {
				$page = $page_entry;
				break;
			}
		}
		
		// Get out if we have nothing to offer.
		if (!isset($page)) return false;
		
		// Include necessary SS Live files
		$sslivewp = screenstepslive_initializeObject();
		
		$space_id = $page['space_id'];
		$manual_id = $sslivewp->CleanseID($_GET['manual_id']);
		$bucket_id = $sslivewp->CleanseID($_GET['bucket_id']);
		if ($page['resource_type'] == 'bucket' && 
			( (is_string($page['resource_id']) && !empty($page['resource_id'])) || (is_int($page['resource_id']) && $page['resource_id'] > 0) )
			)
			
			$bucket_id = $page['resource_id'];
		else if ($page['resource_type'] == 'manual' && 
			( (is_string($page['resource_id']) && !empty($page['resource_id'])) || (is_int($page['resource_id']) && $page['resource_id'] > 0) )
			)
			$manual_id = $page['resource_id'];
		$lesson_id = $sslivewp->CleanseID($_GET['lesson_id']);

		if (empty($space_id))
		{
			// Retrieve list of all spaces
			$text = $sslivewp->GetSpacesList($post->ID);
			
		}  else if (!empty($space_id) && !empty($lesson_id)) {
		
			if ($page['resource_type'] == 'manual') {
				$resource_type = 'manual';
				$resource_id = $manual_id;
			} else {
				$resource_type = 'bucket';
				$resource_id = $bucket_id;
			}
		
			// What content to display?
			if (!empty($manual_id)) {
				$max_len = 30;
			
				$next_title = $sslivewp->GetNextLessonTitle($space_id, 'manual', $manual_id, $lesson_id);
				$prev_title = $sslivewp->GetPrevLessonTitle($space_id, 'manual', $manual_id, $lesson_id);
				if (strlen(utf8_decode($next_title)) > $max_len) $next_title = screenstepslive_utf8_substr($next_title, 0, $max_len-1) . '...';
				if (strlen(utf8_decode($prev_title)) > $max_len) $prev_title = screenstepslive_utf8_substr($prev_title, 0, $max_len-1) . '...';
				
				$next_link = $sslivewp->GetLinkToNextLesson($post->ID, $space_id, 'manual', $manual_id, $lesson_id, $next_title . ' >'); // 'Next Lesson');
				$prev_link = $sslivewp->GetLinkToPrevLesson($post->ID, $space_id, 'manual', $manual_id, $lesson_id, '< ' . $prev_title); //'Previous Lesson');
				
				$text .= '<div class="screenstepslive_navigation">' . "\n";
				if ($prev_link != '') 
					$text .= '<div class="alignleft">' . $prev_link . '</div>' . "\n";
				if (!empty($next_link))
					$text .= '<div class="alignright">' . $next_link . '</div>' . "\n";
				$text .= '<div class="screenstepslive_nav_bottom"></div>';
				$text .= '</div>';
				
				$text .= $sslivewp->GetLessonHTML($space_id, 'manual', $manual_id, $lesson_id);
				
				$text .= '<div class="screenstepslive_navigation">' . "\n";
				if (!empty($prev_link))
					$text .= '<div class="alignleft">' . $prev_link . '</div>' . "\n";
				if (!empty($next_link))
					$text .= '<div class="alignright">' . $next_link . '</div>' . "\n";
				$text .= '<div class="screenstepslive_nav_bottom"></div>';
				$text .= '</div>';
				
			} else if (!empty($bucket_id)) {
	
				$text = $sslivewp->GetLessonHTML($space_id, 'bucket', $bucket_id, $lesson_id);
			}
			
			$text .= $sslivewp->GetLessonComments($post->ID, $space_id, $resource_type, $resource_id, $lesson_id);
			
		} else if (!empty($space_id) && !empty($manual_id)) {
			$text .= $sslivewp->GetManualList($post->ID, $space_id, $manual_id);
			
		} else if (!empty($space_id) && !empty($bucket_id)) {
			$text .= $sslivewp->GetBucketList($post->ID, $space_id, $bucket_id);

		} else {
			$text = $sslivewp->GetSpaceList($post->ID, $space_id);
		}
	}
	
	if ($text != '') $the_content = preg_replace('/{{SCREENSTEPSLIVE_CONTENT}}/i', $text, $the_content);
	
    return $the_content;
}


function screenstepslive_utf8_substr($str,$start) 
{ 
	preg_match_all("/./u", $str, $ar); 

	if(func_num_args() >= 3) { 
		$end = func_get_arg(2); 
		return join("",array_slice($ar[0],$start,$end)); 
	} else { 
		return join("",array_slice($ar[0],$start)); 
	} 
}

function screenstepslive_checkIfDeletedPostIsReferenced($postID) {
	$pages = get_option('screenstepslive_pages');
	foreach ($pages as $i => $value) {
		if ($pages[$i]['id'] == $postID) {
			unset($pages[$i]);
		}
	}
	
	update_option('screenstepslive_pages', array_values($pages)); // array_values reindexes
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
	
	$form_submitted = false; // So we don't create pages twice.
	
	// API form was submitted
	if ($_POST['api_submitted'] == 1) {
		
		if (get_option('screenstepslive_domain') != $_POST['domain']) {
			// Reset caches as account probably changed.
			$pages = get_option('screenstepslive_pages');
			if (is_array($pages) ) {
				foreach ($pages as $key => $page) {
					$pages[$key]['space_id'] = '';
					$pages[$key]['resource_type'] = '';
					$pages[$key]['resource_id'] = '';
				}
				update_option('screenstepslive_pages', $pages);
			}
		}
	
		update_option('screenstepslive_domain', $_POST['domain']);
		update_option('screenstepslive_reader_name', $_POST['reader_name']);
		update_option('screenstepslive_reader_password', $_POST['reader_password']);
		update_option('screenstepslive_protocol', $_POST['protocol']);
		
		$sslivewp->domain = $_POST['domain'];
		$sslivewp->SetUserCredentials($_POST['reader_name'], $_POST['reader_password']);
		$sslivewp->protocol = $_POST['protocol'];
		
		$form_submitted = true;
	}
	
	// Manuals form was subbmited
	if ($_POST['pages_submitted'] == 1&& is_array($_POST['pages'])) {		
		// Loop through posted pages, making sure they still exist. User could have deleted one.		
		$pages = get_option('screenstepslive_pages');

		foreach ($_POST['pages'] as $page_id => $new_page) {
			if (isset($pages[$page_id])) {
				if ($pages[$page_id]['space_id'] != $new_page['space_id']) {
					$pages[$page_id]['resource_id'] = 0;
				} else {
					$pages[$page_id]['resource_id'] = $new_page['resource_id'];
				}
				$pages[$page_id]['space_id'] = $new_page['space_id'];
				$pages[$page_id]['resource_type'] = 'manual';
			}
		}
			
		update_option('screenstepslive_pages', $pages);
		$form_submitted = true;
	}
	
	// Create template pages
	if (!$form_submitted && isset($_GET['ssliveaction'])) {
		switch ($_GET['ssliveaction']) {
			case 'create_page':
				$postID = screenstepslive_createTemplatePage();
				if (intval($postID) > 0) {
					$spaces = $sslivewp->GetSpaces();
					
					$pages = get_option('screenstepslive_pages');
					$pages[$postID]['id'] = $postID;
					$pages[$postID]['space_id'] = $spaces['space'][0]['id'];
					$pages[$postID]['resource_type'] = 'manual';
					$pages[$postID]['resource_id'] = 0;
					
					update_option('screenstepslive_pages', $pages);
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

END;

			// Print WordPress Pages

echo <<<END
	
	<fieldset class="options">
			<legend>WordPress Page Settings</legend>
END;
			
			if (!isset($spaces)) $spaces = $sslivewp->GetSpaces();
			if ($spaces) {
				if (count($spaces['space']) == 0) {
					print "<div>No spaces were returned from the ScreenSteps Live server.</div>";
				} else {				
					// Print FORM and header
					print ('<form method="post" action="' . GETENV('REQUEST_URI') . '">' . "\n");
					print ('<input type="hidden" name="pages_submitted" value="1">' . "\n");
					print ('<table class="optiontable form-table">');
					print ('<tr>' . "\n");
					print ('<th scope="column" style="width:10px;">Page ID</th>' . "\n");
					print ('<th scope="column">Space</th>' . "\n");
					print ('<th scope="column">Manual</th>' . "\n");
					print ('</tr>' . "\n");
					
					$pages = get_option('screenstepslive_pages');
					$manauls = array();
					$buckets = array();
					
					if (is_array($pages) ) {
						foreach ($pages as $key => $page) {
							$i = $page['id'];
							print ('<tr>' . "\n");
								// Page id column
								print ('<td width="30px">');
									print ('<input type="hidden" name="pages[' . $i . '][id]" id="page_' . $i . '"' . 'value="'. $page['id'] . '"/>' . $page['id']);
								print ('</td>' . "\n");
								
								// Spaces select menu column
								if (count($spaces['space']) > 0) {
									print ('<td><select name="pages[' . $i . '][space_id]' . '">');
									foreach ($spaces['space'] as $key => $space) {
										// Determine initial state for visible checkbox and permission settings.
										if ($space['id'] == $page['space_id']) {
											print '<option value="' . $space['id'] . '" selected="selected">' . $space['title'] . '</option>';
										} else {
											print '<option value="' . $space['id'] . '">' . $space['title'] . '</option>';
										}
									}
									print('</select></td>' . "\n");
								} else {
									print ('<td>None</td>' . "\n");
								}						
								
								// Manual select menu column
								if ($page['space_id'] > 0) {
									if (!isset($manuals[ $page['space_id'] ])) {
										$space = $sslivewp->GetSpace($page['space_id']);
										if (is_array($space['assets']['asset'])) {
											foreach ($space['assets']['asset'] as $asset) {
												if (strtolower($asset['type']) == 'manual') {
													$manuals[ $page['space_id'] ][] = array('id'=>$asset['id'], 'title'=>$asset['title']);
												} elseif (strtolower($asset['type']) == 'bucket') {
													$buckets[ $page['space_id'] ][] = array('id'=>$asset['id'], 'title'=>$asset['title']);
												}
											}
										}
									}
	
									if (count($manuals[ $page['space_id'] ]) > 0) {
										print ('<td><select name="pages[' . $i . '][resource_id]' . '">');
											print ('<option value="0">None</option>');
										foreach ($manuals[ $page['space_id'] ] as $manual) {
											// Determine initial state for visible checkbox and permission settings.
											if ($manual['id'] == $page['resource_id']) {
												print '<option value="' . $manual['id'] . '" selected="selected">' . $manual['title'] . '</option>';
											} else {
												print '<option value="' . $manual['id'] . '">' . $manual['title'] . '</option>';
											}
										}
										print('</select></td>' . "\n");
									} else {
										print ('<input type="hidden" name="pages[' . $i . '][resource_id]" value="0" />');
										print ('<td>No manuals in space</td>' . "\n");
									}		
	
								} else {
									print ('<input type="hidden" name="pages[' . $i . '][resource_id]" value="0" />');
									print ('<td>None</td>' . "\n");
								}
								
							print ('</tr>' . "\n");
						}
					}
					
						print ('<tr><td colspan="3">');
								print ('<p><a href="' . GETENV('REQUEST_URI') . '&ssliveaction=create_page">Create ScreenSteps Live Page</a></p>');
						print ('</td></tr>');
					
					print ("</table>\n");
		
		echo <<<END
					<div class="submit">
						<input type="submit" id="submit_page_settings" value="Save WordPress Page Settings"/>
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


function screenstepslive_createTemplatePage($type='') {
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
			
		default:
			$post['post_title'] = 'ScreenSteps Live Page';
			$post['post_content'] = '{{SCREENSTEPSLIVE_CONTENT}}';
			break;
	}
	
	$postID = wp_insert_post($post);
	
	if (is_wp_error($postID))
		return $post_ID;

	if (empty($postID))
		return 0;
		
	return $postID;
}

?>