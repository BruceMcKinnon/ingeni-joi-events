<?php

//
// IngeniJoiEventsApi - Class to connect and retrive JSON event feed from the Joi server.
//

class IngeniJoiEventsApi {
	private $joi_api_private_token;
	private $joi_api_url;

	public function __construct( $url, $private_token ) {
		$this->joi_api_private_token = $private_token;
		$this->joi_api_url = $url;
	}

	private function is_local() {
		$local_install = false;
		if ( ($_SERVER['SERVER_NAME']=='localhost') || ( stripos($_SERVER['SERVER_NAME'],'dev.local') !== false ) ) {
			$local_install = true;
		}
		return $local_install;
	}


	public function fb_log($msg, $filename = "", $overwrite = false) {
		$upload_dir = wp_upload_dir();
		$outFile = $upload_dir['basedir'];
	
		if ( $this->is_local() ) {
			$outFile .= DIRECTORY_SEPARATOR;
		} else {
			$outFile .= DIRECTORY_SEPARATOR;
		}

		if ($filename == "") {
			$filename = basename(__DIR__);
		}
		$outFile .= $filename.'.txt';
		
		date_default_timezone_set("Australia/Sydney");

		$write_mode = "a";
		if ($overwrite) {
			$write_mode = "w+";
		}

		// Now write out to the file
		$log_handle = fopen($outFile, $write_mode);
		if ($log_handle !== false) {
			fwrite($log_handle, date("Y-m-d H:i:s").": ".$msg."\r\n");
			fclose($log_handle);
		}
	}	

	// Connect to the Joi server
	private function ingeni_joi_connect( $url, &$errMsg ) {
		try {
			$return_json = "";

			$request_headers = [
				'Authorization: Bearer '. $this->joi_api_private_token
			];

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			//curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);

			$return_data = curl_exec($ch);

			if (curl_errno($ch)) {
				$errMsg = curl_error($ch);
			} else {
				$return_json = json_decode($return_data, true);
			}

			// Show me the result
			curl_close($ch);

		} catch (Exception $ex) {
			$errMsg = $ex->Message;
		}
		return $return_json;
	}

	// Return the Joi JSON structure
	public function get_joi_events( $url = "", &$errMsg ) {
		$json = "";
		if ($url == "") {
			$url = $this->joi_api_url;
		}

		try {
			$json = $this->ingeni_joi_connect( $url, $errMsg );

		} catch (Exception $ex) {
			$errMsg = $ex->Message;
		}
		return $json;
	}

} ?>