<?php
/**
 * Created by IntelliJ IDEA.
 * User: Priit
 * Date: 29.01.2019
 * Time: 11:21
 */

namespace Scoro;


class Curl {
    protected $ch;

    protected $result;
    private $url;

    private $headers;

    public function __construct() {
        curl_setopt($this->getCurlHandler(), CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($this->getCurlHandler(), CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
        curl_setopt($this->getCurlHandler(), CURLOPT_HEADER, 1);
        curl_setopt($this->getCurlHandler(), CURLOPT_HTTPGET, TRUE);
        curl_setopt($this->getCurlHandler(), CURLINFO_HEADER_OUT, TRUE);
        curl_setopt($this->getCurlHandler(), CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($this->getCurlHandler(), CURLOPT_AUTOREFERER, TRUE);
        curl_setopt($this->getCurlHandler(), CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 5.1; rv:16.0) Gecko/20100101 Firefox/35.0.1");
        curl_setopt($this->getCurlHandler(), CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($this->getCurlHandler(), CURLOPT_CONNECTTIMEOUT, 10);

    }

    public function setCookiePath($path){
        if (!file_exists($path)) {
            $r = fopen($path,'w');
            fclose($r);
        }
        curl_setopt($this->getCurlHandler(), CURLOPT_COOKIEFILE, $path);
        curl_setopt($this->getCurlHandler(), CURLOPT_COOKIEJAR, $path);
    }

    private function resetHttpMethod(){
        curl_setopt($this->getCurlHandler(), CURLOPT_POST, false);
        curl_setopt($this->getCurlHandler(), CURLOPT_PUT, false);
        curl_setopt($this->getCurlHandler(), CURLOPT_CUSTOMREQUEST, null);
        curl_setopt($this->getCurlHandler(), CURLOPT_HTTPGET, false);
    }

    public function setHeaders(array $headers = []) {
        $this->headers = $headers;
    }

    public function setHeader($name, $value) {
        $this->headers[$name] = $value;
    }

    public function httpGetRequest($url){
        $this->setUrl($url);
        curl_setopt($this->getCurlHandler(), CURLOPT_HTTPGET, true);
        return $this->exec();
    }

    public function httpPostRequest($url, $fields = []){
        $this->setUrl($url);
        curl_setopt($this->getCurlHandler(), CURLOPT_POST, true);
        if (!empty($fields)) {
            curl_setopt($this->getCurlHandler(), CURLOPT_POSTFIELDS, json_encode($fields));
            $this->headers['content-type'] = 'Content-Type: application/json';
        }
        return $this->exec();
    }

    protected function exec() {
        if (isset($this->headers)) {
            curl_setopt($this->getCurlHandler(), CURLOPT_HTTPHEADER, $this->headers);
        }

        $response = curl_exec($this->getCurlHandler());
        $error = curl_error($this->getCurlHandler());

        $header_size = curl_getinfo($this->getCurlHandler(), CURLINFO_HEADER_SIZE);
        $result['header'] = substr($response, 0, $header_size);
        $result['body'] = json_decode(substr($response, $header_size), true);
        $result['rawBody'] = $response;
        $result['http_code'] = curl_getinfo($this->getCurlHandler(), CURLINFO_HTTP_CODE);
        $result['last_url'] = curl_getinfo($this->getCurlHandler(), CURLINFO_EFFECTIVE_URL);

        if ($error != "") {
            $result['curl_error'] = $error;
            return $result;
        }

        $result['cookies'] = [];
        preg_match_all('/^Set-Cookie: (.*?);/m', $response, $m);
        foreach ($m[1] as $cookie) {
            $result['cookies'][] = $cookie;
        }

        $result['headers'] = [];
        preg_match_all('/^(.*): (.*?)$/m', $result['header'], $m);

        foreach ($m[1] as $index => $header) {
            $result['headers'][$header][] = rtrim($m[2][$index], "\r");
        }

        if ($redirectLink = $result['headers']['Location'][0]) {
            if (substr($redirectLink, 0, 4) != 'http') {
                $urlParts = parse_url($result['last_url']);
                $link = $urlParts['scheme'] . '://' . $urlParts['host'] . $redirectLink;
            } else {
                $link = $redirectLink;
            }
            $this->httpGetRequest($link);
            $result = $this->exec();
        }

        $this->result = $result;

        return $this->result;
    }

    /**
     * @return mixed
     */
    public function getCurlHandler() {
        if (!isset($this->ch)) {
            $this->ch = curl_init();
        }
        return $this->ch;
    }

    /**
     * @param $url
     */
    protected function setUrl($url) {
        $this->url = $url;
        $this->resetHttpMethod();
        @curl_setopt($this->getCurlHandler(), CURLOPT_URL, trim($url));
    }

    /**
     * @return mixed
     */
    public function getResult() {
        return $this->result;
    }

    public function addCurlOption($option, $value) {
        curl_setopt($this->getCurlHandler(), $option, $value);
    }

    public function getRequestHeadersSent() {
        return curl_getinfo($this->getCurlHandler())['request_header'];
    }

    /**
     * @return mixed
     */
    public function wgetUrl() {
        return $this->url;
    }

    /**
     * @return mixed
     */
    public function getHeaders() {
        return $this->headers;
    }

}