<?php
/**
 * @category   Emarsys
 * @package    Emarsys_Emarsys
 * @copyright  Copyright (c) 2017 Emarsys. (http://www.emarsys.net/)
 */
namespace Emarsys\Emarsys\Model\Api;

use Magento\Payment\Model\Method\Logger;
use Zend_Http_Client;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Zend_Json;

/**
 * Class Api
 * API class for Emarsys API wrappers
 * @package Emarsys\Emarsys\Model\Api
 */
class Api extends \Magento\Framework\DataObject
{
    protected $_config;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * @var Logger
     */
    protected $customLogger;

    protected $apiUrl;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfigInterface;

    /**
     * Api constructor.
     * By default is looking for first argument as array and assigns it as object
     * attributes This behavior may change in child classes
     * @param \Psr\Log\LoggerInterface $logger
     * @param Logger $customLogger
     * @param ScopeConfigInterface $scopeConfigInterface
     * @param array $data
     */
    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        Logger $customLogger,
        ScopeConfigInterface $scopeConfigInterface,
        array $data = []
    ) {
        $this->_logger = $logger;
        $this->customLogger = $customLogger;
        $this->scopeConfigInterface = $scopeConfigInterface;
        parent::__construct($data);
    }

    public function _construct()
    {
        $this->websiteId = '';
        $this->scope = '';
    }

    /**
     * @param $websiteId
     */
    public function setWebsiteId($websiteId)
    {
        $this->websiteId = $websiteId;
        $this->scope = 'websites';
    }

    /**
     * Return Emarsys Api user name based on config data
     * @return string
     */
    public function getApiUsername()
    {
        $username = $this->scopeConfigInterface->getValue('emarsys_settings/emarsys_setting/emarsys_api_username', $this->scope, $this->websiteId);
        if ($username == '' && $this->websiteId == 1) {
            $username = $this->scopeConfigInterface->getValue('emarsys_settings/emarsys_setting/emarsys_api_username');
        }
        return $username;
    }

    /**
     * Return Emarsys Api password based on config data
     * @return string
     */
    public function getApiPassword()
    {
        $password = $this->scopeConfigInterface->getValue('emarsys_settings/emarsys_setting/emarsys_api_password', $this->scope, $this->websiteId);
        if ($password == '' && $this->websiteId == 1) {
            $password = $this->scopeConfigInterface->getValue('emarsys_settings/emarsys_setting/emarsys_api_password');
        }
        return $password;
    }

    /**
     * set Emarsys API URL
     * @return string
     */
    public function setApiUrl()
    {
        $endpoint = $this->scopeConfigInterface->getValue('emarsys_settings/emarsys_setting/emarsys_api_endpoint', $this->scope, $this->websiteId);
        if ($endpoint == '' && $this->websiteId == 1) {
            $endpoint = $this->scopeConfigInterface->getValue('emarsys_settings/emarsys_setting/emarsys_api_endpoint');
        }
        if ($endpoint == 'custom') {
            $url = $this->scopeConfigInterface->getValue('emarsys_settings/emarsys_setting/emarsys_custom_url', $this->scope, $this->websiteId);
            if ($url == '' && $this->websiteId == 1) {
                $url = $this->scopeConfigInterface->getValue('emarsys_settings/emarsys_setting/emarsys_custom_url');
            }
            return $this->apiUrl = rtrim($url, '/') . "/";
        } elseif ($endpoint == 'cdn') {
            return $this->apiUrl = "https://api-cdn.emarsys.net/api/v2/";
        } elseif ($endpoint == 'default') {
            return $this->apiUrl = "https://api.emarsys.net/api/v2/";
        }
    }

    /**
     * Return Emarsys API URL
     * @return string
     */
    public function getApiUrl()
    {
        return $this->setApiUrl();
    }

    /**
     * Returns emarsys enabled based on the current scope
     * @return boolean
     */
    protected function _isEnabled()
    {
        $enable = $this->scopeConfigInterface->getValue('emarsys_settings/emarsys_setting/enable', $this->scope, $this->websiteId);
        if ($enable == '' && $this->websiteId == 1) {
            $enable = $this->scopeConfigInterface->getValue('emarsys_settings/emarsys_setting/enable');
        }
        return $enable;
    }

    /**
     * @param $requestType
     * @param $urlParam
     * @param array $requestBody
     * @return array
     * @throws \Zend_Http_Client_Exception
     * @throws \Zend_Json_Exception
     */
    public function sendRequestOrig($requestType, $urlParam, $requestBody = [])
    {
        $client = new Zend_Http_Client();
        $requestUrl = $this->getApiUrl() . $urlParam;
        $client->setUri($requestUrl);
        switch ($requestType) {
            case 'GET':
                $client->setMethod(Zend_Http_Client::GET);
                $client->setParameterGet($requestBody);
                break;
            case 'POST':
                $client->setMethod(Zend_Http_Client::POST);
                $client->setRawData(Zend_Json::encode($requestBody));
                break;
            case 'PUT':
                $client->setMethod(Zend_Http_Client::PUT);
                $client->setRawData(Zend_Json::encode($requestBody));
                break;
            case 'DELETE':
                $client->setMethod(Zend_Http_Client::DELETE);
                $client->setRawData(Zend_Json::encode($requestBody));
                break;
        }
        $nonce = time();
        $timestamp = gmdate("c");
        $passwordDigest = base64_encode(sha1($nonce . $timestamp . $this->getApiPassword(), false));
        $header = [
            'Content-Type' => 'application/json',
            'Accept-encoding' => 'utf-8',
            'X-WSSE' => [
                'X-WSSE: UsernameToken' .
                'Username="' . $this->getApiUsername() . '", ' .
                'PasswordDigest="' . $passwordDigest . '", ' .
                'Nonce="' . $nonce . '", ' .
                'Created="' . $timestamp . '"',
                'Content-type: application/json;charset="utf-8"',
            ]
        ];
        $client->setHeaders($header);
        $response = $client->request();
        $returnData = [
            'status' => Zend_Json::decode($response->getStatus()),
            'body' => Zend_Json::decode($response->getBody())
        ];
        return $returnData;
    }

    /**
     * @param $requestType
     * @param null $endPoint
     * @param array $requestBody
     * @return array
     * @throws \Exception
     */
    public function sendRequest($requestType, $endPoint = null, $requestBody = [])
    {
        if ($endPoint == 'custom') {
            $endPoint = '';
        }
        if (!in_array($requestType, ['GET', 'POST', 'PUT', 'DELETE'])) {
            throw new \Exception('Send first parameter must be "GET", "POST", "PUT" or "DELETE"');
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        switch ($requestType) {
            case 'GET':
                curl_setopt($ch, CURLOPT_HTTPGET, 1);
                break;
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, Zend_Json::encode($requestBody));
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                curl_setopt($ch, CURLOPT_POSTFIELDS, Zend_Json::encode($requestBody));
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                curl_setopt($ch, CURLOPT_POSTFIELDS, Zend_Json::encode($requestBody));
                break;
        }
        curl_setopt($ch, CURLOPT_HEADER, false);

        $requestUri = $this->getApiUrl() . $endPoint;
        curl_setopt($ch, CURLOPT_URL, $requestUri);

        /**
         * We add X-WSSE header for authentication.
         * Always use random 'nonce' for increased security.
         * timestamp: the current date/time in UTC format encoded as
         * an ISO 8601 date string like '2010-12-31T15:30:59+00:00' or '2010-12-31T15:30:59Z'
         * passwordDigest looks sg like 'MDBhOTMwZGE0OTMxMjJlODAyNmE1ZWJhNTdmOTkxOWU4YzNjNWZkMw=='
         */
        $nonce = md5(time());
        $timestamp = gmdate("c");
        $passwordDigest = base64_encode(sha1($nonce . $timestamp . $this->getApiPassword(), false));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'X-WSSE: UsernameToken ' .
                'Username="' . $this->getApiUsername() . '", ' .
                'PasswordDigest="' . $passwordDigest . '", ' .
                'Nonce="' . $nonce . '", ' .
                'Created="' . $timestamp . '"',
                'Content-type: application/json;charset="utf-8"',
            ]);
        $response = curl_exec($ch);

        $http_code = 200;
        if (!curl_errno($ch)) {
		    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		}
        curl_close($ch);
        /* Debug Log for Response */
        #$this->_logger->addDebug('response: ');
        #$this->_logger->addDebug(print_r($response, true));

        $returnData = [
            'status' => $http_code,
            'body' => json_decode($response, true)
        ];
        #$this->_logger->addDebug(print_r($returnData, true));
        return $returnData;
    }
}
