<?php

// Version 0.9.2.1

// Include ScreenSteps Live class file
require_once('sslive_class.php');


//////
// ScreenSteps Live WordPress class
//////
class SSLiveWordPress extends SSLiveAPI {
	// Public
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
	function SSLiveWordPress($domain, $api_key, $protocol='http') {
		$this->__construct($domain, $api_key, $protocol);
	}
	
	// Constructor
	function __construct ($domain, $api_key, $protocol='http') {		
		// Cache
		$this->arrays['manuals'] = NULL;
		$this->arrays['manual'] = NULL;
		$this->arrays['lesson'] = NULL;
		
		// Initialize parent
		parent::__construct($domain, $api_key, $protocol);
	}
	
	function __destruct() {

	}
	
	// PUBLIC
	
	function GetLinkToManualIndex() {
		return $this->GetLinkToWordPressPage($this->manuals_index_post_id, false);
	}
	
	
	function GetLinkToManual($manual_id) {
		$link_to_manual = $this->GetLinkToWordPressPage($this->manual_post_id);
		return $link_to_manual . 'manual_id=' . $manual_id;
	}
	
	
	function GetManualTitle($manual_id) {
		$title = '';
		
		$this->CacheManual($manual_id);
		
		if ($this->arrays['manual']) {
			$title = $this->arrays['manual']['title'];
		}
		
		return $title;
	}
	
	
	function GetLessonTitle($manual_id, $lesson_id) {
		$title = '';
		
		$this->CacheLesson($manual_id, $lesson_id);
		
		if ($this->arrays['lesson']) {
			$title = $this->arrays['lesson']['title'];
		}
		
		return $title;
	}
	
	
	function GetLinkToPrevLesson($manual_id, $lesson_id, $text) {
		$link = '';
		
		$this->CacheLesson($manual_id, $lesson_id);
		
		if ($this->arrays['lesson']) {		
			$prevLessonID = intval($this->arrays['lesson']['manual']['previous_lesson']['id']);
			if ($prevLessonID > 0) {
				$link_to_lesson = $this->GetLinkToWordPressPage($this->lesson_post_id);
				$link = '<a href="' . $link_to_lesson . 'manual_id=' . $manual_id . '&lesson_id=' . $prevLessonID . '">' .
							$text . '</a>';
			}
		}
		return $link;
	}
	
	
	function GetPrevLessonTitle($manual_id, $lesson_id) {
		$title = '';
		
		$this->CacheLesson($manual_id, $lesson_id);
		
		if ($this->arrays['lesson']) {
			$prevLessonID = intval($this->arrays['lesson']['manual']['previous_lesson']['id']);
			if ($prevLessonID > 0)
				$title = $this->arrays['lesson']['manual']['previous_lesson']['title'];
		}
		return $title;
	}
	
	
	function GetLinkToNextLesson($manual_id, $lesson_id, $text) {
		$link = '';
		
		$this->CacheLesson($manual_id, $lesson_id);
		
		if ($this->arrays['lesson']) {
			$nextLessonID = intval($this->arrays['lesson']['manual']['next_lesson']['id']);
			if ($nextLessonID > 0) {
				$link_to_lesson = $this->GetLinkToWordPressPage($this->lesson_post_id);
				$link = '<a href="' . $link_to_lesson . 'manual_id=' . $manual_id . '&lesson_id=' . $nextLessonID . '">' .
							$text . '</a>';
			}
		}
		return $link;
	}
	
	
	function GetNextLessonTitle($manual_id, $lesson_id) {
		$title = '';
		
		$this->CacheLesson($manual_id, $lesson_id);
		
		if ($this->arrays['lesson']) {
			$prevLessonID = intval($this->arrays['lesson']['manual']['next_lesson']['id']);
			if ($prevLessonID > 0)
				$title = $this->arrays['lesson']['manual']['next_lesson']['title'];
		}
		return $title;
	}
	
	
	function GetManualsList() {
		$text = '';
		$manuals = array();
		
		$this->CacheManuals();
		$array =& $this->arrays['manuals'];
		if ($array) {
			$manuals = $this->FilterManuals($array);
			
			if (count($manuals) == 0) {
				$text .= "<p>No manuals found.</p>";
			} else {
				$link_to_manual = $this->GetLinkToWordPressPage($this->manual_post_id);
				
				print ("<ul>\n");
				foreach ($manuals as $key => $manual) {
					$manual_id = intval($manual['id']);
					if ($this->manual_settings[$manual_id] != '') {
						if ($this->UserCanViewManual($this->manual_settings[$manual_id])) {
							$text .= ('<li><a href="' . $link_to_manual . 'manual_id=' . $manual_id . '">' . $manual['title'] . "</a></li>\n");
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
	
	function GetManualList($manual_id) {
		$text = '';
		
		// Validate that user can view this manual
		if ($this->UserCanViewManual($this->manual_settings[$manual_id])) {
			$this->CacheManual($manual_id);
			$array =& $this->arrays['manual'];
		
			if ($array) {
				if (count($array['sections']['section']) == 0) {
					$text .= "<p>Manual has no sections.</p>";
				} else {
					$link_to_lesson = $this->GetLinkToWordPressPage($this->lesson_post_id);
					
					foreach ($array['sections']['section'] as $key => $section) {
						$text .= ('<h3>' . $section['title'] . '</h3>');
						
						if ($section['lessons']['lesson']) {
							$text .= ("<ul>\n");
							foreach ($section['lessons']['lesson'] as $key => $lesson) {
								$lessonID = intval($lesson['id']);
								$text .= ('<li><a href="' . $link_to_lesson . $this->lesson_post_id . '&manual_id=' . $manual_id . '&lesson_id=' . $lessonID . '">' . 
									$lesson['title'] . "</a></li>\n");
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
	
	function GetLessonHTML($manual_id, $lesson_id) {
		$text = '';
		
		// Validate that user can view this manual
		if ($this->UserCanViewManual($this->manual_settings[$manual_id])) {
		
			$this->CacheLesson($manual_id, $lesson_id);
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
							$text .= ('<h3>' . $step['title'] . "</h3>\n");
							$text .= ('<p>' . $step['instructions'] . "</p>\n");
							$text .= ('<p><img src="' . $step['media'][0]['url'] . 
								'" width="' . $step['media'][0]['width'] . '" height="' . $step['media'][0]['height'] . '" />' . "\n");
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
	
	
	function CacheManuals() {
		if (!$this->arrays['manuals'])
			$this->arrays['manuals'] = parent::GetManuals();
	}
	
	
	function CacheManual($manual_id) {
		if (!$this->arrays['manual'])
			$this->arrays['manual'] = parent::GetManual($manual_id);
	}
	
	
	function CacheLesson($manual_id, $lesson_id) {
		if (!$this->arrays['lesson'])
			$this->arrays['lesson'] = parent::GetLesson($manual_id, $lesson_id);
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
	
	function FilterManuals(&$array) {
		$manuals = array();
		
		foreach ($array['manual'] as $key => $manual) {
			$manual_id = intval($manual['id']);
			if ($this->manual_settings[$manual_id] != '') {
				if ($this->UserCanViewManual($this->manual_settings[$manual_id])) {
					$manuals[] = $manual;
				}
			}
		}

		return $manuals;
	}
	
	function UserCanViewManual($permission_setting) {
		return ($permission_setting == 'everyone' || ($permission_setting == 'public' && !$this->user_can_read_private) || ($permission_setting == 'private' && $this->user_can_read_private));
	}
}

?>