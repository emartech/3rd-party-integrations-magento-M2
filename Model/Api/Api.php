<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Model\Api;

use Emarsys\Emarsys\Helper\Data as EmarsysHelper;
use Magento\Store\Model\StoreManagerInterface as StoreManager;
use Zend_Json;

class Api extends \Magento\Framework\DataObject
{
    const CONTACT_CREATE_IF_NOT_EXISTS = 'contact/?create_if_not_exists=1';
    const CONTACT_GETDATA = 'contact/getdata';

    protected $apiUrl;

    /**
     * @var StoreManager
     */
    protected $storeManager;

    /**
     * Api constructor.
     * By default is looking for first argument as array and assigns it as object
     * attributes This behavior may change in child classes
     *
     * @param StoreManager $storeManager
     * @param array $data
     */
    public function __construct(
        StoreManager $storeManager,
        array $data = []
    ) {
        $this->storeManager = $storeManager;
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
     *
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getApiUsername()
    {
        /** @var \Magento\Store\Api\Data\WebsiteInterface $website */
        $website = $this->storeManager->getWebsite($this->websiteId);
        return $website->getConfig('emartech/emarsys_setting/emarsys_api_username');
    }

    /**
     * Return Emarsys Api password based on config data
     *
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getApiPassword()
    {
        /** @var \Magento\Store\Api\Data\WebsiteInterface $website */
        $website = $this->storeManager->getWebsite($this->websiteId);
        return $website->getConfig('emartech/emarsys_setting/emarsys_api_password');
    }

    /**
     * set Emarsys API URL
     *
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function setApiUrl()
    {
        /** @var \Magento\Store\Api\Data\WebsiteInterface $website */
        $website = $this->storeManager->getWebsite($this->websiteId);
        $endpoint = $website->getConfig('emartech/emarsys_setting/emarsys_api_endpoint');
        if ($endpoint == 'custom') {
            $url = $website->getConfig('emartech/emarsys_setting/emarsys_custom_url');
            if (trim($url) == '') {
                $url = EmarsysHelper::EMARSYS_DEFAULT_API_URL;
            }
            $this->apiUrl = rtrim($url, '/') . "/";
        } elseif ($endpoint == 'cdn') {
            $this->apiUrl = EmarsysHelper::EMARSYS_CDN_API_URL;
        } else {
            $this->apiUrl = EmarsysHelper::EMARSYS_DEFAULT_API_URL;
        }

        return $this->apiUrl;
    }

    /**
     * Return Emarsys API URL
     *
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getApiUrl()
    {
        return $this->setApiUrl();
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
        $nonce = hash('sha256', time());
        $timestamp = gmdate("c");
        $passwordDigest = base64_encode(sha1($nonce . $timestamp . $this->getApiPassword(), false));
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            [
                'X-WSSE: UsernameToken ' .
                'Username="' . $this->getApiUsername() . '", ' .
                'PasswordDigest="' . $passwordDigest . '", ' .
                'Nonce="' . $nonce . '", ' .
                'Created="' . $timestamp . '"',
                'Content-type: application/json;charset="utf-8"',
                'Extension-Version: ' . EmarsysHelper::VERSION,
            ]
        );

        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Zend_Http_Client');
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

        $response = curl_exec($ch);
        $header = curl_getinfo($ch);

        $http_code = 200;
        if (!curl_errno($ch)) {
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        }
        curl_close($ch);

        try {
            $decodedResponse = \Zend_Json::decode($response);
        } catch (\Exception $e) {
            $decodedResponse = false;
        }

        if ($decodedResponse) {
            $response = $decodedResponse;
        }

        return [
            'status' => $http_code,
            'header' => $header,
            'body' => $response,
        ];
    }

    /**
     * @param $arrCustomerData
     * @return mixed|string
     * @throws \Exception
     */
    public function createContactInEmarsys($arrCustomerData)
    {
        return $this->sendRequest('PUT', self::CONTACT_CREATE_IF_NOT_EXISTS, $arrCustomerData);
    }

    /**
     * @param $arrCustomerData
     * {
     *  "keyId": "12596",
     *  "keyValues": [
     *   "1"
     *  ],
     *  "fields": [
     *   "3",
     *   "12596",
     *   "15912",
     *   "12597"
     *  ]
     * }
     * @return array
     * @throws \Exception
     */
    public function getContactData($arrCustomerData)
    {
        return $this->sendRequest('POST', self::CONTACT_GETDATA, $arrCustomerData);
    }
}
