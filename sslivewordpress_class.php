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
	public $manuals_index_post_id = 0;
	public $manual_post_id = 0;
	public $lesson_post_id = 0;

	private $queryString = '';
	private $xmlobjects = array();
	
	// Constructor
	function __construct ($domain, $api_key, $protocol='http') {
		if (intval($_GET['page_id']) > 0)
			$this->queryString = '?page_id='; // . intval($_GET['page_id']);
		else if (intval($_GET['post']) > 0)
			$this->queryString = '?post='; // . intval($_GET['post']);
		else
			$this->queryString = '?p='; // . intval($_GET['p']);
		
		// Cache
		$this->xmlobjects['manuals'] = NULL;
		$this->xmlobjects['manual'] = NULL;
		$this->xmlobjects['lesson'] = NULL;
		
		// Initialize parent
		parent::__construct($domain, $api_key, $protocol);
	}
	
	function __destruct() {

	}
	
	// PUBLIC
	
	public function GetLinkToManualIndex() {
		return $this->queryString . $this->manuals_index_post_id;
	}
	
	
	public function GetLinkToManual($manual_id) {
		return $this->queryString . $this->manual_post_id . '&manual_id=' . $manual_id;
	}
	
	
	public function GetManualTitle($manual_id) {
		$title = '';
		
		$this->CacheManual($manual_id);
		
		if ($this->xmlobjects['manual']) {
			$title = $this->xmlobjects['manual']->title;
		}
		
		return $title;
	}
	
	
	public function GetLessonTitle($manual_id, $lesson_id) {
		$title = '';
		
		$this->CacheLesson($manual_id, $lesson_id);
		
		if ($this->xmlobjects['lesson']) {
			$title = $this->xmlobjects['lesson']->title;
		}
		
		return $title;
	}
	
	
	public function GetLinkToPrevLesson($manual_id, $lesson_id, $text) {
		$link = '';
		
		$this->CacheLesson($manual_id, $lesson_id);
		
		if ($this->xmlobjects['lesson']) {		
			$prevLessonID = intval($this->xmlobjects['lesson']->manual->previous_lesson->id);
			if ($prevLessonID > 0)
				$link = '<a href="' . $this->queryString . $this->lesson_post_id . '&manual_id=' . $manual_id . '&lesson_id=' . $prevLessonID . '">' .
							$text . '</a>';
		}
		return $link;
	}
	
	
	public function GetPrevLessonTitle($manual_id, $lesson_id) {
		$title = '';
		
		$this->CacheLesson($manual_id, $lesson_id);
		
		if ($this->xmlobjects['lesson']) {
			$prevLessonID = intval($this->xmlobjects['lesson']->manual->previous_lesson->id);
			if ($prevLessonID > 0)
				$title = $this->xmlobjects['lesson']->manual->previous_lesson->title;
		}
		return $title;
	}
	
	
	public function GetLinkToNextLesson($manual_id, $lesson_id, $text) {
		$link = '';
		
		$this->CacheLesson($manual_id, $lesson_id);
		
		if ($this->xmlobjects['lesson']) {
			$nextLessonID = intval($this->xmlobjects['lesson']->manual->next_lesson->id);
			if ($nextLessonID > 0)
				$link = '<a href="' . $this->queryString . $this->lesson_post_id . '&manual_id=' . $manual_id . '&lesson_id=' . $nextLessonID . '">' .
							$text . '</a>';
		}
		return $link;
	}
	
	
	public function GetNextLessonTitle($manual_id, $lesson_id) {
		$title = '';
		
		$this->CacheLesson($manual_id, $lesson_id);
		
		if ($this->xmlobjects['lesson']) {
			$prevLessonID = intval($this->xmlobjects['lesson']->manual->next_lesson->id);
			if ($prevLessonID > 0)
				$title = $this->xmlobjects['lesson']->manual->next_lesson->title;
		}
		return $title;
	}
	
	
	public function GetManualsList() {
		$text = '';
		$manuals = array();
		
		$this->CacheManuals();
		$xmlobject =& $this->xmlobjects['manuals'];
		
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
							$text .= ('<li><a href="' . $this->queryString . $this->manual_post_id . '&manual_id=' . $manual->id . '">' . $manual->title . "</a></li>\n");
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
			$this->CacheManual($manual_id);
			$xmlobject =& $this->xmlobjects['manual'];
		
			if ($xmlobject) {				
				//$text .= ('<h2>'. $xmlobject->title . '</h2>');
		
				if (count($xmlobject->sections->section) == 0) {
					$text .= "<p>Manual has no sections.</p>";
				} else {
					foreach ($xmlobject->sections->section as $section) {
						$text .= ('<h3>' . $section->title . '</h3>');
						
						$text .= ("<ul>\n");
						foreach ($section->lessons->lesson as $lesson) {
							$text .= ('<li><a href="' . $this->queryString . $this->lesson_post_id . '&manual_id=' . $manual_id . '&lesson_id=' . $lesson->id . '">' . 
								$lesson->title . "</a></li>\n");
						}
						$text .= ("</ul>\n");
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
	
	public function GetLessonHTML($manual_id, $lesson_id) {
		$text = '';
		
		// Validate that user can view this manual
		if ($this->UserCanViewManual($this->manual_settings[$manual_id])) {
		
			$this->CacheLesson($manual_id, $lesson_id);
			$xmlobject =& $this->xmlobjects['lesson'];
			
			if ($xmlobject) {
				
				//$text .= ('<h2>' . $xmlobject->title . "</h2>\n");
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
			
		} else {
			$text = $this->unauthorized_msg;
		}
		
		return $text;
	}
	
	
	public function CacheManuals() {
		if (!$this->xmlobjects['manuals'])
			$this->xmlobjects['manuals'] = parent::GetManuals();
	}
	
	
	public function CacheManual($manual_id) {
		if (!$this->xmlobjects['manual'])
			$this->xmlobjects['manual'] = parent::GetManual($manual_id);
	}
	
	
	public function CacheLesson($manual_id, $lesson_id) {
		if (!$this->xmlobjects['lesson'])
			$this->xmlobjects['lesson'] = parent::GetLesson($manual_id, $lesson_id);
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
		return ($permission_setting == 'everyone' || ($permission_setting == 'public' && !$this->user_can_read_private) || ($permission_setting == 'private' && $this->user_can_read_private));
	}
}

?>