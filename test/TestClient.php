<?php
/**
 * Created by IntelliJ IDEA.
 * User: Priit
 * Date: 29.01.2019
 * Time: 11:15
 */

require __DIR__.'/../vendor/autoload.php';

use Scoro\Client;

class TestClient extends Client {

    private $refreshToken;
    private $accessToken;
    private $expiresAfterSeconds;

    public function getCurrentAccessToken() {
        return $this->accessToken;
    }

    public function getCurrentRefreshToken() {
        return $this->refreshToken;
    }

    public function getCurrentAccessTokenExpiresInSeconds() {
        return $this->expiresAfterSeconds;
    }

    public function saveCurrentAccessToken($accessToken) {
        $this->accessToken = $accessToken;
    }

    public function saveCurrentRefreshToken($refreshToken) {
        $this->refreshToken = $refreshToken;
    }

    public function saveCurrentAccessTokenExpiresIn($expiresAfterSeconds) {
        $this->expiresAfterSeconds;
    }
}