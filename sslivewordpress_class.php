<?php

// Include ScreenSteps Live class file
require_once('sslive_class.php');


//////
// ScreenSteps Live WordPress class
//////
class SSLiveWordPress extends SSLiveAPI {
	public $unauthorized_msg = 'You are not authorized to view this manual.';
	public $manual_settings = array();
	public $user_can_read_private = 0;

	private $queryString = '';	
	
	// Constructor
	function __construct ($domain, $api_key, $protocol='http') {
		if (intval($_GET['page_id']) > 0)
			$this->queryString = '?page_id=' . intval($_GET['page_id']);
		else
			$this->queryString = '?p=' . intval($_GET['p']);
		
		// Initialize parent
		parent::__construct($domain, $api_key, $protocol);
	}
	
	function __destruct() {
	
	}
	
	// PUBLIC
	
	public function GetManualsList() {
		$text = '';
		$manuals = array();
		
		// Fetch manuals
		$xmlobject = parent::GetManuals();	
		if ($xmlobject) {
			$manuals = $this->FilterManuals($xmlobject);
			
			if (count($manuals) == 0) {
				$text .= "<p>No manuals found.</p>";
			} else {			
				print ("<ul>\n");
				foreach ($manuals as $manual) {
					$manual_id = intval($manual->id);
					if ($this->manual_settings[$manual_id] != '') {
						if ($this->UserCanViewManual($this->manual_settings[$manual_id])) {
							$text .= ('<li><a href="' . $this->queryString . '&manual_id=' . $manual->id . '">' . $manual->title . "</a></li>\n");
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
	
	public function GetManualList($manual_id) {
		$text = '';
		
		// Validate that user can view this manual
		if ($this->UserCanViewManual($this->manual_settings[$manual_id])) {
			$xmlobject = parent::GetManual($manual_id);
		
			if ($xmlobject) {
				$text .= '<div><a href="' . $this->queryString . '">Return to manuals</a></div>';
				
				$text .= ('<h2>'. $xmlobject->title . '</h2>');
		
				if (count($xmlobject->sections->section) == 0) {
					$text .= "<p>Manual has no sections.</p>";
				} else {
					foreach ($xmlobject->sections->section as $section) {
						$text .= ('<h3>' . $section->title . '</h3>');
						
						$text .= ("<ul>\n");
						foreach ($section->lessons->lesson as $lesson) {
							$text .= ('<li><a href="' . $this->queryString . '&manual_id=' . $manual_id . '&lesson_id=' . $lesson->id . '">' . 
								$lesson->title . "</a></li>\n");
						}
						$text .= ("</ul>\n");
					}
				}
				
				$text .= '<div><a href="' . $this->queryString . '">Return to manuals</a></div>';
			} else {
				$text .= "Error:" . $this->last_error;
			}
		} else {
			$text = $this->unauthorized_msg;
		}
		
		return $text;
	}
	
	public function GetLessonHTML($manual_id, $lesson_id) {
		$text = '';
		
		// Validate that user can view this manual
		if ($this->UserCanViewManual($this->manual_settings[$manual_id])) {
			$text .= '<div><a href="' . $this->queryString . '&manual_id=' . $manual_id . '">Return to manual</a></div>';
			
			// Fetch manual
			$xmlobject = parent::GetLesson($manual_id, $lesson_id);
			
			if ($xmlobject) {
				
				$text .= ('<h2>' . $xmlobject->title . "</h2>\n");
				$text .= ('<p>' . $xmlobject->description . "</p>\n");
				
				if (count($xmlobject->steps->step) == 0)
				{
					$text .= ("<p>Lesson has no steps.</p>\n");
				} else {
					foreach ($xmlobject->steps->step as $step) {
						$text .= ('<h3>' . $step->title . "</h3>\n");
						$text .= ('<p>' . $step->instructions . "</p>\n");
						$text .= ('<p><img src="' . $step->media->url . 
							'" width="' . $step->media->width . '" height="' . $step->media->height . '" />' . "\n");
						$text .= ('<p></p>' . "\n");
					}
				}
			} else {
				$text .= "Error:" . $this->last_error;
			}
			
			$text .= '<div><a href="' . $this->queryString . '&manual_id=' . $manual_id . '">Return to manual</a></div>';
		} else {
			$text = $this->unauthorized_msg;
		}
		
		return $text;
	}
	
	// PRIVATE
	
	private function FilterManuals(&$xmlobject) {
		$manuals = array();
		
		foreach ($xmlobject as $manual) {
			$manual_id = intval($manual->id);
			if ($this->manual_settings[$manual_id] != '') {
				if ($this->UserCanViewManual($this->manual_settings[$manual_id])) {
					$manuals[] = $manual;
				}
			}
		}
		
		return $manuals;
	}
	
	private function UserCanViewManual($permission_setting) {
		return ($permission_setting == 'all' || ($permission_setting == 'public' && !$this->user_can_read_private) || ($permission_setting == 'private' && $this->user_can_read_private));
	}
}

?>