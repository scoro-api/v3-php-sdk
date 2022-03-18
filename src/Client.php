<?php
namespace ScoroAPI;

abstract class Client {

    const SCOPE_USER = 'user';
    const SCOPE_COMPANY = 'company';

    private $clientId;
    private $clientSecret;
    private $baseUrl;
    private $language;

    private $curl;

    public function __construct($clientId, $clientSecret, $baseUrl, $language = 'eng') {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;

        $baseUrl = strtolower($baseUrl);
        $urlInfo = parse_url($baseUrl);
        if (strpos($urlInfo['host'], '.scoro.') === false) {
            throw new ScoroException('baseUrl must be set to *.scoro.*');
        }

        if ($urlInfo['scheme'] !== 'https') {
            throw new ScoroException('baseUrl must use https');
        }
        $this->baseUrl = rtrim($baseUrl, '/') . '/';

        $this->language = $language;
    }

    abstract public function getCurrentAccessToken();
    abstract public function getCurrentRefreshToken();

    abstract public function saveCurrentAccessToken($accessToken);
    abstract public function saveCurrentAccessTokenExpiresIn($expiresAfterSeconds);
    abstract public function saveCurrentRefreshToken($refreshToken);

    /**
     * @param mixed $curl
     */
    public function setCurl($curl) {
        $this->curl = $curl;
    }

    public function list(string $modulePath, array $filterArguments = []) {

        $dataToPost = ['lang' => $this->getLanguage()];
        if (!empty($filterArguments)) {
            $dataToPost['filter'] = $filterArguments;
        }

        return $this->makeRequestPost($modulePath, $dataToPost);
    }

	public function view(string $modulePath, int $id) {
		$modulePath .= '/view/' . $id;
		$dataToPost = ['lang' => $this->getLanguage()];

		return $this->makeRequestPost($modulePath, $dataToPost);
	}

	public function create(string $modulePath, array $data = []) {
		return $this->update($modulePath, 0, $data);
	}

	public function update(string $modulePath, int $id, array $data = []) {
		$modulePath .= '/modify/' . $id;
		$dataToPost = ['lang' => $this->getLanguage()];

		if (!empty($data)) {
			$dataToPost['request'] = $data;
		}

		return $this->makeRequestPost($modulePath, $dataToPost);
	}

	public function delete(string $modulePath, int $id) {
		$modulePath .= '/delete/' . $id;

		$dataToPost = ['lang' => $this->getLanguage()];

		return $this->makeRequestPost($modulePath, $dataToPost);
	}

    /**
     * @return mixed
     */
    public function getClientId() {
        return $this->clientId;
    }

    /**
     * @param mixed $clientId
     */
    public function setClientId($clientId) {
        $this->clientId = $clientId;
    }

    /**
     * @return mixed
     */
    public function getClientSecret() {
        return $this->clientSecret;
    }

    /**
     * @param mixed $clientSecret
     */
    public function setClientSecret($clientSecret) {
        $this->clientSecret = $clientSecret;
    }

    /**
     * @param $redirectUrl
     * @param $state - This is CSRF token that will be returned from
     * @param string $scope - company wide or user based
     * @param string $codeChallenge - for PKCE(Proof Key for Code Exchange) approach
     * @return string
     */

    public function getAuthUrl($redirectUrl, $state, $scope = self::SCOPE_COMPANY, $codeChallenge = '') {
        if (!in_array($scope, [Client::SCOPE_COMPANY, Client::SCOPE_USER])) {
            $scope = Client::SCOPE_COMPANY;
        }

        $queryAddOn = 'redirect_uri=' . $redirectUrl . '&client_id=' . $this->getClientId() . '&scope=' . $scope . '&state=' . $state;
        if ($codeChallenge) {
            $queryAddOn .= '&code_challenge=' . $codeChallenge . '&code_challenge_method=S256';
        }
        return $this->baseUrl . 'apiAuth?'.$queryAddOn;
    }

    public function makeRequestPost($modulePath, array $data) {
        try{
            $curl = $this->getCurl();

            $url = $this->baseUrl . $modulePath;

            $curl->setHeader('Authorization', 'Authorization: Bearer ' . $this->getCurrentAccessToken());
            $response = $curl->httpPostRequest($url, $data);
            return $this->handleResponse($response['body']);
        } catch (ScoroAccessTokenExpiredException $e) {
            if ($this->refreshTokens()){
                return $this->makeRequestPost($modulePath, $data);
            } else {
                throw new ScoroAccessTokenRefreshException();
            }
        }
    }

    public function makeRequestGet($modulePath) {
        $curl = $this->getCurl();
        $url = $this->baseUrl . $modulePath;
        $response = $curl->httpGetRequest($url);
        return $this->handleResponse($response['body']);
    }

    protected function getCurl() {
        if (!isset($this->curl)) {
            $this->curl = new Curl();
        }
        return $this->curl;
    }

    protected function handleResponse($response) {

        if (isset($response['access_token'])) {
            return $response;

        } else if (empty($response['status']) || $response['status'] === 'ERROR') {

            $errorType = $response['messages']['error'][0] ?? null;
            switch ($errorType) {
                case \ScoroAPI\ErrorType::MESSAGE_OAUTH_ACCESS_TOKEN_EXPIRED;
                    throw new ScoroAccessTokenExpiredException();
                default:
                    throw new ScoroRequestFailedException(print_r($response, true), print_r($response['errors'], true));
            }

        } else {
            return $response['data'];
        }

    }

    private function refreshTokens() {

        $dataToPost = [
            'client_id' => $this->getClientId(),
            'client_secret' => $this->getClientSecret(),
            'refresh_token' => $this->getCurrentRefreshToken(),
            'grant_type' => 'authorization_code',
        ];

        $response = $this->makeRequestPost('tokens', $dataToPost);
        if (isset($response['access_token'])) {
            $this->saveCurrentRefreshToken($response['refresh_token']);
            $this->saveCurrentAccessToken($response['access_token']);
            $this->saveCurrentAccessTokenExpiresIn($response['expire_in']);
            return true;
        } else {
            return false;
        }

    }

    public function getLanguage(): string {
        return $this->language;
    }

	public function setLanguage(string $language) {
		$this->language = $language;
	}

}

