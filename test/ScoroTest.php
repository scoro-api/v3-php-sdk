<?php
/**
 * Created by IntelliJ IDEA.
 * User: Priit
 * Date: 29.01.2019
 * Time: 11:02
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/TestClient.php';
class ScoroTest extends \PHPUnit\Framework\TestCase {

    const CLIENT_ID = 'testId';
    const CLIENT_SECRET = 'testHushHush';
    const CLIENT_SITE_URL = 'https://scoro.scoro.com';

    /**
     * @test
     * @backupGlobals enabled
     */

    public function testConstructorValues() {
        self::expectException(\Scoro\ScoroException::class);
        new TestClient(self::CLIENT_ID, self::CLIENT_SECRET, 'bad url');

        self::expectException(\Scoro\ScoroException::class);
        new TestClient(self::CLIENT_ID, self::CLIENT_SECRET, 'http://scoro.scoro.com');
    }

    /**
     * @test
     * @backupGlobals enabled
     */
    public function testRefreshExpiredTokens() {


        /* @var $client \TestClient*/
        $client = $this->getMockBuilder(\TestClient::class)
            ->setConstructorArgs([self::CLIENT_ID, self::CLIENT_SECRET, self::CLIENT_SITE_URL])
            ->setMethods(['saveCurrentAccessTokenExpiresIn'])
            ->getMock();

        $client->method('saveCurrentAccessTokenExpiresIn')->willReturnCallback(function (){
            $_SESSION['tokenRefreshed'] = true;
        });


        /* @var $curl \Scoro\Curl*/
        $curl = $this->getMockBuilder(\Scoro\Curl::class)
            ->setMethods(['httpPostRequest', 'exec'])
            ->getMock();



        $accessToken = 'accessToken';
        $refreshToken = 'refreshToken';
        $client->saveCurrentAccessToken($accessToken);
        $client->saveCurrentRefreshToken($refreshToken);

        $_SESSION['tokenRefreshed'] = false;

        $curl->method('exec')->willReturnCallback(function () {});

        $curl->method('httpPostRequest')->willReturnCallback(function ($url, $dataToPost) {
            if (strpos($url,'/tokens')) {
                return [
                        'access_token' => 'newAccessToken',
                        'refresh_token' => 'newRefreshToken',
                        'expires_in' => 3600,
                    ];

            } else if (empty($_SESSION['tokenRefreshed'])) {
                return [
                        'status' => 'ERROR',
                        'messages' => ['error' => [\Scoro\ErrorType::MESSAGE_OAUTH_ACCESS_TOKEN_EXPIRED]],
                    ];
            } else {
                return [
                    'status' => 'OK',
                    'data' => [],
                ];
            }
        });

        $client->setCurl($curl);

        $client->getList('projects');

        self::assertEquals('newRefreshToken', $client->getCurrentRefreshToken());

    }

}