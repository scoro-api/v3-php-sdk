<?php
namespace Scoro;

abstract class Client {

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

    public function getList($modulePath, $filterArguments = []) {

        $dataToPost = ['lang' => $this->getLanguage()];
        if (!empty($filterArguments)) {
            $dataToPost['filter'] = $filterArguments;
        }

        return $this->makeRequestPost($modulePath, $filterArguments);
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

    protected function makeRequestPost($modulePath, array $data) {
        try{
            $curl = $this->getCurl();

            $url = $this->baseUrl . $modulePath;

            $curl->setHeader('Authorization', $this->getCurrentAccessToken());
            $response = $curl->httpPostRequest($url, $data);
            return $this->handleResponse($response);
        } catch (ScoroAccessTokenExpiredException $e) {
            if ($this->refreshTokens()){
                return $this->makeRequestPost($modulePath, $data);
            } else {
                throw new ScoroAccessTokenRefreshException();
            }
        }
    }

    protected function makeRequestGet($modulePath) {
        $curl = $this->getCurl();
        $url = $this->baseUrl . $modulePath;
        $response = $curl->httpGetRequest($url);
        return $this->handleResponse($response);
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
                case \Scoro\ErrorType::MESSAGE_OAUTH_ACCESS_TOKEN_EXPIRED;
                    throw new ScoroAccessTokenExpiredException();
                default:
                    throw new ScoroException();
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

    private function getLanguage(): string {
        return $this->language;
    }

}

