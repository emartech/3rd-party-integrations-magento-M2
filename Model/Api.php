<?php
/**
 * @category   Emarsys
 * @package    Emarsys_Emarsys
 * @copyright  Copyright (c) 2017 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Model;

class Api extends \Magento\Framework\HTTP\ZendClient
{
    /**
     * constructor
     */
    public $_apiUrl;
    public $_username;
    public $_password;

    //public $config;

    public function _construct($params)
    {

        $this->_apiUrl = $params['api_url'];
        $this->_username = $params['api_username'];
        ;
        $this->_password = $params['api_password'];
        ;
        $this->config['timeout'] = 60;
        //$this->_debug = Mage::registry(self::DEBUG_KEY);
        return parent::__construct($this->_apiUrl);
    }

    protected function _getWSSEHeader()
    {
        $nonce = md5(time());
        $timestamp = gmdate("c");
        $passwordDigest = base64_encode(sha1($nonce . $timestamp . $this->_password, false));

        return sprintf(
            'UsernameToken Username="%s", PasswordDigest="%s", Nonce="%s", Created="%s"',
            $this->_username,
            $passwordDigest,
            $nonce,
            $timestamp
        );
    }


    protected function _request($apiCall, $method = 'GET', $data = [], $jsonDecode = true)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->jasonHelper = $objectManager->get('Magento\Framework\Json\Helper\Data');
        $this->setUri($this->_apiUrl . $apiCall);
        $this->setHeaders(
            [
                'Content-Type' => 'application/json',
                'Accept-encoding' => 'utf-8',
                'X-WSSE' => $this->_getWSSEHeader()
            ]
        );
        $response = '';
        try {
            if ($method == "GET" && !(empty($data))) {
                $this->setParameterGet($data);
            } else {
                if (!empty($data)) {
                    $this->setRawData($this->jasonHelper->jsonEncode($data));
                }
            }

            $responseObject = $this->request($method);
            $response = $responseObject->getBody();
            if ($jsonDecode) {
                $response = $this->jasonHelper->jsonDecode($response);
            }
        } catch (\Exception $e) {
            throw $e;
        }
        return $response;
    }


    public function post($apiCall, $data = [])
    {
        if (is_object($data)) {
            $data = $data->toArray();
        }

        return $this->_request($apiCall, \Zend_Http_Client::POST, $data);
    }

    public function put($apiCall, $data = [])
    {
        if (is_object($data)) {
            $data = $data->toArray();
        }

        return $this->_request($apiCall, \Zend_Http_Client::PUT, $data);
    }

    public function get($apiCall, $data = [], $jsonDecode = true)
    {
        return $this->_request($apiCall, \Zend_Http_Client::GET, $data, $jsonDecode);
    }

    public function ping()
    {
        $result = 1;
        // try {
        $response = $this->get('settings');
        // } catch (Zend_Http_Client_Exception $e) {
        //$result = self::API_ERROR_CONNECTION;
        //} catch (Exception $e) {
        //  $result = $e->getMessage();
        //}

        return $response;
    }
}
