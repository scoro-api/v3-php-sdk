<?php

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
        self::expectException(\ScoroAPI\ScoroException::class);
        new TestClient(self::CLIENT_ID, self::CLIENT_SECRET, 'bad url');

        self::expectException(\ScoroAPI\ScoroException::class);
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


        /* @var $curl \ScoroAPI\Curl*/
        $curl = $this->getMockBuilder(\ScoroAPI\Curl::class)
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
                        'body' => [
							'access_token' => 'newAccessToken',
							'refresh_token' => 'newRefreshToken',
							'expires_in' => 3600,
						]
                    ];

            } else if (empty($_SESSION['tokenRefreshed'])) {
                return [
                        'body' => [
							'status' => 'ERROR',
							'messages' => ['error' => [\ScoroAPI\ErrorType::MESSAGE_OAUTH_ACCESS_TOKEN_EXPIRED]],
						]
                    ];
            } else {
				self::assertEquals(['lang' => 'eng'], $dataToPost);
                return [
                    'body' => [
						'status' => 'OK',
						'data' => [],
					]
                ];
            }
        });

        $client->setCurl($curl);

        $client->list('projects');

        self::assertEquals('newRefreshToken', $client->getCurrentRefreshToken());

    }

    /**
     * @test
     * @backupGlobals enabled
     */
    public function testAuthUrl() {

        $client = new TestClient(self::CLIENT_ID, self::CLIENT_SECRET, self::CLIENT_SITE_URL);


        $url = $client->getAuthUrl('retUrl', 'scrfToken');
        $data = parse_url($url);
        $queryParameters = $this->getQueryArgsAsArray($data);


        self::assertEquals('retUrl', $queryParameters['redirect_uri']);
        self::assertEquals(self::CLIENT_ID, $queryParameters['client_id']);
        self::assertEquals(TestClient::SCOPE_COMPANY, $queryParameters['scope']);
        self::assertEquals('scrfToken', $queryParameters['state']);



        $url = $client->getAuthUrl('retUrl', 'scrfToken', TestClient::SCOPE_USER,'challengeString');
        $data = parse_url($url);
        $queryParameters = $this->getQueryArgsAsArray($data);


        self::assertEquals('retUrl', $queryParameters['redirect_uri']);
        self::assertEquals(self::CLIENT_ID, $queryParameters['client_id']);
        self::assertEquals(TestClient::SCOPE_USER, $queryParameters['scope']);
        self::assertEquals('scrfToken', $queryParameters['state']);
        self::assertEquals('challengeString', $queryParameters['code_challenge']);
        self::assertEquals('S256', $queryParameters['code_challenge_method']);


        $url = $client->getAuthUrl('retUrl', 'scrfToken', 'gibersih','challengeString');
        $data = parse_url($url);
        $queryParameters = $this->getQueryArgsAsArray($data);

        self::assertEquals(TestClient::SCOPE_COMPANY, $queryParameters['scope']);



    }

    /**
     * @param $data
     * @return array
     */
    private function getQueryArgsAsArray($data): array {
        $tmp = explode('&', $data['query']);
        $queryParameters = [];
        foreach ($tmp as $arg) {
            $var = explode('=', $arg);
            $queryParameters[$var[0]] = $var[1];
        }
        return $queryParameters;
    }

}