<?php

// Version 0.5.0.1

// You need to get this from PEAR
// http://pear.php.net/package/Crypt_HMAC

## Use this line if you put the HMAC.php file in the same directory
require_once('HMAC.php');
## Use the following line if you keep HMAC.php in the PEAR repository.
//require_once('Crypt/HMAC.php');


class SSLiveAPI {
	public $domain = '';
	public $api_key = '';
	public $last_error = '';
	public $protocol = 'http';
	public $show_protected = false;
	
	function __construct($domain, $api_key, $protocol='http') {
		$this->domain = $domain;
		$this->api_key = $api_key;
		$this->protocol = $protocol;
	}
	
	function __destruct() {
	
	}
	
	// PUBLIC
	
	public function GetManuals() {
		// Example URL: http://example.screensteps.com/api/manuals
		$data = '';

		$this->last_error = $this->requestURLData($this->getCompleteURL('/api/manuals/'), $data);
		if ($this->last_error == '')
			return simplexml_load_string($data);
		else
			return NULL;
	}
	
	public function GetManual($manual_id) {
		// Example URL: http://example.screensteps.com/api/manuals/46
		$data = '';
		
		$manual_id = intval($manual_id);
		$this->last_error = $this->requestURLData($this->getCompleteURL('/api/manuals/'. $manual_id), $data);
		if ($this->last_error == '')
			return simplexml_load_string($data);
		else
			return NULL;
	}
	
	public function GetLesson($manual_id, $lesson_id) {
		// Example URL: http://example.screensteps.com/api/manuals/46/lessons/169
		$data = '';
		
		$manual_id = intval($manual_id);
		$lesson_id = intval($lesson_id);
		$this->last_error = $this->requestURLData($this->getCompleteURL('/api/manuals/'. $manual_id . '/lessons/' . $lesson_id), $data);
		if ($this->last_error == '')
			return simplexml_load_string($data);
		else
			return NULL;
	}
	
	// PRIVATE
	
	private function getCompleteURL($request) {
		$url = $this->protocol . '://' . $this->domain . $request;
		if ($this->show_protected) $url .= '?show_protected=true';
		return $url;
	}
	
	private function requestURLData($url, &$data) {
		$parsed_url = parse_url($url);
		$path_query = $parsed_url['path'];
		if ($this->show_protected) $path_query .= '?show_protected=true';
		$httpDate = gmdate("D, d M Y H:i:s T");
	
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
				
		## Build authentication header
		$header[] = "Content-Type: application/xml";
		$header[] = "Date: " . $httpDate;
		$header[] = "Authorization: " . $this->encode($this->domain . ':' . $path_query . ':' . $httpDate);
		
		curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
		
		$data = curl_exec($curl);
		$error = curl_error($curl);
		curl_close($curl);
		
		if (strcmp($data, "Couldn't authenticate you") == 0)
			$error = 'bad authentication';
			
		return $error;
	}

	private function encode($data) {
		$hasher =& new Crypt_HMAC($this->api_key, "sha1");
		$digest = $hasher->hash($data);
		// hash_mac isn't installed on two systems I tried so we use PEAR library
		// $digest = hash_mac("sha1", $data, $this->api_key, true);
		return base64_encode(pack('H*', $digest));
	}
}

?>