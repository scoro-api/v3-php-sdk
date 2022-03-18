<?php
namespace ScoroAPI;

class ScoroRequestFailedException extends \Exception {

	public function __construct($response, $errors) {
		$message = json_encode([
			'response' => $response,
			'errors' => $errors,
		]);
		parent::__construct($message);
	}
}
