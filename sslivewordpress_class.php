<?php

// Version 1.0.1

// Include ScreenSteps Live class file
require_once(dirname(__FILE__) . '/sslive_class.php');


//////
// ScreenSteps Live WordPress class
//////
class SSLiveWordPress extends SSLiveAPI {
	// Public
	var $unauthorized_space_msg = 'You are not authorized to view this space.';
	var $unauthorized_bucket_msg = 'You are not authorized to view this bucket.';
	var $unauthorized_msg = 'You are not authorized to view this manual.';
	var $manual_settings = array();
	var $user_can_read_private = 0;
	var $manuals_index_post_id = 0;
	var $manual_post_id = 0;
	var $lesson_post_id = 0;

	// Private
	var $queryString = '';
	var $arrays = array();
	
	// PHP 4
	function SSLiveWordPress($domain, $protocol='http') {
		$this->__construct($domain, $protocol);
	}
	
	// Constructor
	function __construct ($domain, $protocol='http') {		
		// Cache
		$this->arrays['spaces'] = NULL;
		$this->arrays['space'] = NULL;
		$this->arrays['manuals'] = NULL;
		$this->arrays['manual'] = NULL;
		$this->arrays['lesson'] = NULL;
		
		// Initialize parent
		parent::__construct($domain, $protocol);
	}
	
	function __destruct() {

	}
	
	// PUBLIC
	
	function GetLinkToSpacesIndex($post_id) {
		return $this->GetLinkToWordPressPage($post_id, false);
	}
	
	function GetLinkToSpace($post_id, $space_id) {
		$link_to_space = $this->GetLinkToWordPressPage($post_id);
		return $link_to_space . 'space_id=' . $space_id;
	}	
	
	function GetLinkToManual($post_id, $space_id, $manual_id) {
		$link_to_manual = $this->GetLinkToWordPressPage($post_id);
		return $link_to_manual . 'space_id=' . $space_id . '&manual_id=' . $manual_id;
	}
	
	
	function GetLinkToManualLesson($post_id, $space_id, $manual_id, $lesson_id) {
		$link_to_lesson = $this->GetLinkToWordPressPage($post_id);
		return $link_to_lesson . 'space_id=' . $space_id . '&manual_id=' . $manual_id . '&lesson_id=' . $lesson_id;
	}
	
	
	function GetLinkToBucketLesson($post_id, $space_id, $bucket_id, $lesson_id) {
		$link_to_lesson = $this->GetLinkToWordPressPage($post_id);
		return $link_to_lesson . 'space_id=' . $space_id . '&bucket_id=' . $bucket_id . '&lesson_id=' . $lesson_id;
	}
	
	
	function GetLinkToBucket($post_id, $space_id, $bucket_id) {
		$link_to_bucket = $this->GetLinkToWordPressPage($post_id);
		return $link_to_bucket . 'space_id=' . $space_id . '&bucket_id=' . $bucket_id;
	}
	
	
	function GetSpaceTitle($space_id) {
		$title = '';
		
		$this->CacheSpace($space_id);
		
		if ($this->arrays['space']) {
			$title = $this->arrays['space']['title'];
		}
		
		return $title;
	}
	
	
	function GetBucketTitle($space_id, $bucket_id) {
		$title = '';
		
		$this->CacheBucket($space_id, $bucket_id);
		
		if ($this->arrays['bucket']) {
			$title = $this->arrays['bucket']['title'];
		}
		
		return $title;
	}
	
	
	function GetManualTitle($space_id, $manual_id) {
		$title = '';
		
		$this->CacheManual($space_id, $manual_id);
		
		if ($this->arrays['manual']) {
			$title = $this->arrays['manual']['title'];
		}
		
		return $title;
	}
	
	
	function GetManualLessonTitle($space_id, $manual_id, $lesson_id) {
		$title = '';
		
		$this->CacheManualLesson($space_id, $manual_id, $lesson_id);

		if ($this->arrays['lesson']) {
			$title = $this->arrays['lesson']['title'];
		}
		
		return $title;
	}
	
	
	function GetBucketLessonTitle($space_id, $bucket_id, $lesson_id) {
		$title = '';
		
		$this->CacheBucketLesson($space_id, $bucket_id, $lesson_id);
		
		if ($this->arrays['lesson']) {
			$title = $this->arrays['lesson']['title'];
		}
		
		return $title;
	}
	
	
	function GetLinkToPrevLesson($post_id, $space_id, $type, $manual_id, $lesson_id, $text) {
		$link = '';
		
		if ($type == 'manual') {
			$this->CacheManualLesson($space_id, $manual_id, $lesson_id);
		
			if ($this->arrays['lesson']) {		
				$prevLessonID = intval($this->arrays['lesson']['manual']['previous_lesson']['id']);
				if ($prevLessonID > 0) {
					$link_to_lesson = $this->GetLinkToManualLesson($post_id, $space_id, $manual_id, $prevLessonID);
					$link .= ('<a href="' . $link_to_lesson . '">' . $text . "</a>");
				}
			}
		}
		return $link;
	}
	
	
	function GetPrevLessonTitle($space_id, $type, $manual_id, $lesson_id) {
		$title = '';
		
		if ($type == 'manual') {
			$this->CacheManualLesson($space_id, $manual_id, $lesson_id);
			
			if ($this->arrays['lesson']) {
				$prevLessonID = intval($this->arrays['lesson']['manual']['previous_lesson']['id']);
				if ($prevLessonID > 0)
					$title = $this->arrays['lesson']['manual']['previous_lesson']['title'];
			}
		}
		return $title;
	}
	
	
	function GetLinkToNextLesson($post_id, $space_id, $type, $type_id, $lesson_id, $text) {
		$link = '';
		
		if ($type == 'manual') {
			$this->CacheManualLesson($space_id, $type_id, $lesson_id);
			
			if ($this->arrays['lesson']) {
				$nextLessonID = intval($this->arrays['lesson']['manual']['next_lesson']['id']);
				if ($nextLessonID > 0) {
					$link_to_lesson = $this->GetLinkToManualLesson($post_id, $space_id, $type_id, $nextLessonID);
					$link .= ('<a href="' . $link_to_lesson . '">' . $text . "</a>");
				}
			}
		} else {
			/*$this->CacheBucketLesson($space_id, $type_id, $lesson_id);
			if ($this->arrays['lesson']) {
				$nextLessonID = intval($this->arrays['lesson']['manual']['next_lesson']['id']);
				if ($nextLessonID > 0) {
					$link_to_lesson = $this->GetLinkToManualLesson($post_id, $space_id, $type_id, $nextLessonID);
					$link .= ('<a href="' . $link_to_lesson . '">' . $text . "</a>");
				}
			}*/
		}
		return $link;
	}
	
	
	function GetNextLessonTitle($space_id, $type, $manual_id, $lesson_id) {
		$title = '';
		
		if ($type == 'manual') {
			$this->CacheManualLesson($space_id, $manual_id, $lesson_id);
			
			if ($this->arrays['lesson']) {
				$prevLessonID = intval($this->arrays['lesson']['manual']['next_lesson']['id']);
				if ($prevLessonID > 0)
					$title = $this->arrays['lesson']['manual']['next_lesson']['title'];
			}
		}
		return $title;
	}
	
	
	function GetSpacesList($post_id) {
		$text = '';
		$spaces = array();
		
		$this->CacheSpaces();
		$array =& $this->arrays['spaces'];
		if ($array) {
			$spaces = $this->FilterSpaces($array);
			
			if (count($spaces) == 0) {
				$text .= "<p>No spaces found.</p>";
			} else {
				$link_to_space = $this->GetLinkToWordPressPage($post_id);
				
				print ("<ul>\n");
				foreach ($spaces as $key => $space) {
					$space_id = intval($space['id']);
					if ($this->spaces_settings[$space_id] != '') {
						if ($this->UserCanViewSpace($this->spaces_settings[$space_id])) {
							$text .= ('<li><a href="' . $link_to_space . 'space_id=' . $space_id . '">' . $space['title'] . "</a></li>\n");
						}
					}
				}
				$text .= ("</ul>\n");
			}
		} else {
			$text = "Error:" . $this->last_error;
		}
		
		return $text;
	}
	
	function GetSpaceList($post_id, $space_id) {
		$text = '';
		
		// Validate that user can view this manual
		if ($this->UserCanViewSpace($this->spaces_settings[$space_id])) {
			$this->CacheSpace($space_id);
			$array =& $this->arrays['space'];

			if ($array) {
				if (count($array['assets']['asset']) == 0) {
					$text .= "<p>Space has no assets.</p>";
				} else {
					if ( strtolower($array['assets']['asset'][0]['type']) == 'divider' ) $ulState = NULL;
					else $ulState = 'closed';
					
					foreach ($array['assets']['asset'] as $asset) {
						if ($ulState == 'closed' || ($ulState == NULL && strtolower($asset['type']) != 'divider') ) {
							$text .= "<ul>\n";
							$ulState = 'open';
						}
						
						if (strtolower($asset['type']) == 'manual')
						{
							$link_to_page = $this->GetLinkToManual($post_id, $space_id, $asset['id']);
							$text .= ('<li class="screenstepslive_manual"><a href="' . $link_to_page . '">' . $asset['title'] . "</a></li>\n");
						}
						else if (strtolower($asset['type']) == 'divider')
						{
							if ($ulState == 'open') $text .= "</ul>\n";
							
							$text .= ('<h2 class="screenstepslive_divider">' . $asset['title'] . "</h2>\n");
							
							$ulState = NULL;
						}
						else if (strtolower($asset['type']) == 'bucket')
						{
							$link_to_page = $this->GetLinkToBucket($post_id, $space_id, $asset['id']);
							$text .= ('<li class="screenstepslive_lesson"><a href="' . $link_to_page . '">' . $asset['title'] . "</a></li>\n");
						}							
					}
					
					if ($ulState == 'open') {
						$text .= ("</ul>\n");
					}
				}
				
			} else {
				$text .= "Error:" . $this->last_error;
			}
		} else {
			$text = $this->unauthorized_space_msg;
		}
		
		return $text;
	}
	
	function GetManualList($post_id, $space_id, $manual_id) {
		$text = '';
		
		// Validate that user can view this manual
		if ($this->UserCanViewSpace($this->spaces_settings[$space_id])) {
			$this->CacheManual($space_id, $manual_id);
			$array =& $this->arrays['manual'];
		
			if ($array) {
				if (count($array['chapters']['chapter']) == 0) {
					$text .= "<p>Manual has no chapters.</p>";
				} else {					
					foreach ($array['chapters']['chapter'] as $key => $chapter) {
						$text .= ('<h3>' . $chapter['title'] . '</h3>');
						
						if ($chapter['lessons']['lesson']) {
							$text .= ("<ul>\n");
							foreach ($chapter['lessons']['lesson'] as $key => $lesson) {
								$lessonID = intval($lesson['id']);
								$link_to_lesson = $this->GetLinkToManualLesson($post_id, $space_id, $manual_id, $lessonID);
								
								$text .= ('<li><a href="' . $link_to_lesson . '">' . $lesson['title'] . "</a></li>\n");
							}
							$text .= ("</ul>\n");
						}
					}
				}
				
			} else {
				$text .= "Error:" . $this->last_error;
			}
		} else {
			$text = $this->unauthorized_msg;
		}
		
		return $text;
	}
	
	function GetBucketList($post_id, $space_id, $bucket_id) {
		$text = '';
		
		// Validate that user can view this manual
		if ($this->UserCanViewSpace($this->spaces_settings[$space_id])) {
			$this->CacheBucket($space_id, $bucket_id);
			$array =& $this->arrays['bucket'];
			//print_r($array);
			if ($array) {
				if (count($array['lessons']['lesson']) == 0) {
					$text .= "<p>Bucket has no lessons.</p>";
				} else {
					$text .= ("<ul>\n");
					foreach ($array['lessons']['lesson'] as $key => $lesson) {						
						$lessonID = intval($lesson['id']);
						$link_to_lesson = $this->GetLinkToBucketLesson($post_id, $space_id, $bucket_id, $lessonID);
						
						$text .= ('<li><a href="' . $link_to_lesson . '">' . $lesson['title'] . "</a></li>\n");
					}
					$text .= ("</ul>\n");
				}
				
			} else {
				$text .= "Error:" . $this->last_error;
			}
		} else {
			$text = $this->unauthorized_bucket_msg;
		}
		
		return $text;
	}
	
	function GetLessonHTML($space_id, $type, $type_id, $lesson_id) {
		$text = '';
		
		// Validate that user can view this manual
		if ($this->UserCanViewSpace($this->spaces_settings[$space_id])) {
			
			if (strtolower($type) == 'manual') {
				$this->CacheManualLesson($space_id, $type_id, $lesson_id);
			} else {
				$this->CacheBucketLesson($space_id, $type_id, $lesson_id);
			}
			
			$array =& $this->arrays['lesson'];
			
			if ($array) {
				if ($array['description'] != '' && $array['description'] != '<p></p>') {
					$text .= ('<p>' . $array['description'] . "</p>\n");
				}
				
				if (count($array['steps']['step']) == 0)
				{
					$text .= ("<p>Lesson has no steps.</p>\n");
				} else {
					if ($array['steps']['step']) {
						foreach ($array['steps']['step'] as $key => $step) {
							// $step['media'][0]['type'] = image | text
							// text: has a node "text"
							$text .= ('<h3>' . $step['title'] . "</h3>\n");
							$text .= ('<p>' . $step['instructions'] . "</p>\n");
							
							switch (strtolower($step['media'][0]['type'])) {
								case 'text':
									$text .= '<div class="screenstepslive_mediatext">' . $step['media'][0]['text'] .
										'</div>';
									break;
								case 'image':
								default:
									$text .= ('<div class="screenstepslive_image"><img src="' . $step['media'][0]['url'] . 
										'" width="' . $step['media'][0]['width'] . '" height="' . $step['media'][0]['height'] . '" /></div>' . "\n");
									break;
							}
							$text .= ('<p></p>' . "\n");
						}
					}
				}
			} else {
				$text .= "Error:" . $this->last_error;
			}
			
		} else {
			$text = $this->unauthorized_msg;
		}
		
		return $text;
	}
	
	
	function CacheSpaces() {
		if (!$this->arrays['spaces'])
			$this->arrays['spaces'] = parent::GetSpaces();
	}
	
	function CacheSpace($space_id) {
		if (!$this->arrays['space'])
			$this->arrays['space'] = parent::GetSpace($space_id);
	}
	
	function CacheBucket($space_id, $bucket_id) {
		if (!$this->arrays['bucket'])
			$this->arrays['bucket'] = parent::GetBucket($space_id,$bucket_id);
	}
	
	
	function CacheManual($space_id, $manual_id) {
		if (!$this->arrays['manual'])
			$this->arrays['manual'] = parent::GetManual($space_id, $manual_id);
	}
	
	
	function CacheManualLesson($space_id, $manual_id, $lesson_id) {
		if (!$this->arrays['lesson'])
			$this->arrays['lesson'] = parent::GetManualLesson($space_id, $manual_id, $lesson_id);
	}
	
	function CacheBucketLesson($space_id, $bucket_id, $lesson_id) {
		if (!$this->arrays['lesson'])
			$this->arrays['lesson'] = parent::GetBucketLesson($space_id, $bucket_id, $lesson_id);
	}
	
	// PRIVATE
	
	function GetLinkToWordPressPage($page_id, $prepareForQuery=true) {
		$link = get_permalink($page_id);
		if ($prepareForQuery) {
			$urlParts = parse_url($link);
			if ($urlParts['query'] == '')
				$link .= '?';
			else
				$link .= '&';
		}
		return $link;
	}
	
	function FilterSpaces(&$array) {
		$spaces = array();
		
		foreach ($array['space'] as $key => $space) {
			$space_id = intval($space['id']);
			if ($this->spaces_settings[$space_id] != '') {
				if ($this->UserCanViewSpace($this->spaces_settings[$space_id])) {
					$spaces[] = $space;
				}
			}
		}

		return $spaces;
	}
	
	function UserCanViewManual($permission_setting) {
		return ($permission_setting == 'everyone' || ($permission_setting == 'public' && !$this->user_can_read_private) || ($permission_setting == 'private' && $this->user_can_read_private));
	}
	
	function UserCanViewSpace($permission_setting) {
		return true;
		return ($permission_setting == 'everyone' || ($permission_setting == 'public' && !$this->user_can_read_private) || ($permission_setting == 'private' && $this->user_can_read_private));
	}
}

?>