<?php
namespace ScoroAPI;

class ScoroRequestFailedException extends \Exception {

	private $response;
	private $errors;

	public function __construct($response, $errors) {
		$message = json_encode([
			'response' => $response,
			'errors' => $errors,
		]);
		$this->response = $response;
		$this->errors = $errors;
		parent::__construct($message);
	}

	/**
	 * @return mixed
	 */
	public function getResponse() {
		return $this->response;
	}

	/**
	 * @return mixed
	 */
	public function getErrors() {
		return $this->errors;
	}
}
