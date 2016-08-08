<?php

class Appota_CallApi
{
    private $API_URL = 'https://api.appotapay.com/';
    private $API_KEY;
    private $SECRET_KEY;
    private $LANG;
    private $API_PRIVATE_KEY;
    private $SSL_VERIFY;
    private $VERSION = 'v1';
    private $METHOD = 'POST';

    public function __construct($config)
    {
        // set params
        $this->API_KEY = $config['api_key'];
        $this->LANG = $config['lang'];
        $this->SECRET_KEY = $config['secret_key'];
        $this->SSL_VERIFY = $config['ssl_verify'];
        $this->API_PRIVATE_KEY = $config['private_key'];
    }

    /*
    * function get payment bank url
    */
    public function getPaymentUrl($params)
    {
        // build api url
        $api_url = $this->API_URL.$this->VERSION.'/payment/ecommerce?api_key='.$this->API_KEY.'&lang='.$this->LANG;
        
        if(!$this->SECRET_KEY) {
            return array(
                'error' => 111,
                'message' => 'Website chưa nhập api secret key. Không thể thực hiện thanh toán!'
            );
        }
        if(!$this->hasCurl()) {
            return array(
                'error' => 102,
                'message' => 'Kiểm tra curl trên server'
            );
        }
        // request get payment url
        $result = $this->makeRequest($api_url, $params, $this->METHOD);
        return json_decode($result, true);

    }

    /*
    * function verify signature
    */
    private function verifyOpenSSLSignature($data, $signature, $public_key)
    {
        if (openssl_verify($data, base64_decode($signature), $public_key, OPENSSL_ALGO_SHA1) === 1) {
            return true;
        } else {
            return false;
        }
    }
    
    
    /**
     * Kiểm tra xem server có hỗ trợ curl hay không.
     * @return boolean
     */
    private function hasCurl()
    {
	return function_exists('curl_version');
    }
    
    
    private function createOpenSSLSignature($data, $private_key) {
        // compute signature
        openssl_sign($data, $signature, $private_key, OPENSSL_ALGO_SHA1);
        return base64_encode($signature);
    }
    
    private function createSignature($data, $secret_key) {
        $str_data = serialize($data) . $secret_key;
        $signature = hash('sha256', $str_data);
        return $signature;
    }
    
    private function verifySignature($data, $signature, $secret_key) {
        $str_data = serialize($data) . $secret_key;
        $compare_signature = hash('sha256', $str_data);
        if($compare_signature == $signature) {
            return true;
        }else {
            return false;
        }
    }

    /*
    * function get public key
    */
    private function getPublicKey()
    {
        // set your public key
        return '-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDNk9Fo5g54Wjsbx60jTPx9/13Q
3DgSx8KgrxplDrUGXCusaI4HG4/qiycR9DQQ8P5iH361NPvwbNJRskQtcySYTh54
Weft58ekVdLtw3ljCFM5AjVaGwPNr4G5J7kR4eo88wEkLZ5tgktwhDu8cH741dkG
M1lQGWg1Ezua7THoyQIDAQAB
-----END PUBLIC KEY-----';
    }

    /*
     * function make request
     * url : string | url request
     * params : array | params request
     * method : string(POST,GET) | method request
     */
    private function makeRequest($url, $params, $method = 'POST')
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60); // Time out 60s
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // connect time out 5s

        $result = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (curl_error($ch)) {
            return false;
        }
        
        if ($status != 200) {
            curl_close($ch);
            return false;
        }
        // close curl
        curl_close($ch);
        
        return $result;
    }
	
}