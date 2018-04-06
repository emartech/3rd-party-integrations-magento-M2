<?php
/**
 * @category   Emarsys
 * @package    Emarsys_Emarsys
 * @copyright  Copyright (c) 2017 Emarsys. (http://www.emarsys.net/)
 */
namespace Emarsys\Emarsys\Model;

use Magento\Framework\HTTP\ZendClient;
use Emarsys\Emarsys\Helper\Data;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\File\Csv;
use Magento\Store\Model\StoreManagerInterface;
use Emarsys\Emarsys\Model\Logs as EmarsysModelLogs;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Emarsys\Emarsys\Model\ResourceModel\Order as OrderResourceModel;
use Emarsys\Emarsys\Model\ResourceModel\Product as ProductResourceModel;

/**
 * Class ApiExport
 * @package Emarsys\Emarsys\Model
 */
class ApiExport extends ZendClient
{
    const MIN_CATALOG_RECORDS_COUNT = 1;

    const API_ERROR_CONNECTION = 'Connection error';

    const API_ERROR_RESPONSE_INVALID = 'Invalid response';

    const DEBUG_KEY = 'EMARSYS_DEBUG_INFO';

    protected $_apiUrl;

    protected $_merchantId;

    protected $_token;

    /**
     * @var Data
     */
    protected $emarsysHelper;

    /**
     * @var Csv
     */
    protected $csvWriter;

    /**
     * @var RawFactory
     */
    protected $resultRawFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManagerInterface;

    /**
     * @var Logs
     */
    protected $emarsysLogs;

    /**
     * @var JsonHelper
     */
    protected $jasonHelper;


    /**
     * ApiExport constructor.
     * @param Data $emarsysHelper
     * @param RawFactory $resultRawFactory
     * @param Csv $csvWriter
     * @param StoreManagerInterface $storeManagerInterface
     * @param Logs $emarsysLogs
     * @param JsonHelper $jsonHelper
     */
    public function __construct(
        Data $emarsysHelper,
        RawFactory $resultRawFactory,
        Csv $csvWriter,
        StoreManagerInterface $storeManagerInterface,
        EmarsysModelLogs $emarsysLogs,
        JsonHelper $jsonHelper,
        OrderResourceModel $orderResourceModel,
        ProductResourceModel $productResourceModel
    ) {
        $this->emarsysHelper = $emarsysHelper;
        $this->resultRawFactory = $resultRawFactory;
        $this->csvWriter = $csvWriter;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->emarsysLogs = $emarsysLogs;
        $this->jasonHelper = $jsonHelper;
        $this->orderResourceModel = $orderResourceModel;
        $this->productResourceModel = $productResourceModel;
    }

    /**
     * @param $merchantId
     * @param $token
     */
    public function assignApiCredentials($merchantId, $token)
    {
        $this->_merchantId = $merchantId;
        $this->_token = $token;
    }

    /**
     * Get API Headers
     * @param $token
     * @return array|bool
     */
    public function getApiHeaders($token)
    {
        if (!isset($token)) {
            $token = $this->_token;
        }
        if ($token) {
            $headers = [];
            $headers[] = "Authorization: bearer " . $token;
            $headers[] = "Content-type: text/csv";
            $headers[] = "Accept: text/plain";

            return $headers;
        }
        $storeId = $this->storeManagerInterface->getStore()->getId();
        $this->emarsysLogs->addErrorLog('Api Token Not Found', $storeId, 'ApiExport::getApiHeaders()');

        return false;
    }

    /**
     * @param $apiUrl
     * @param $filePath
     * @return array
     */
    public function apiExport($apiUrl, $filePath)
    {
        $this->_apiUrl = $apiUrl;
        $storeId = $this->storeManagerInterface->getStore()->getId();
        $result = [];
        $result['result'] = 0;
        $result['status'] = '';
        $result['resultBody'] = 'Api Export Failed. API URL or CSV File Not Found.';

        if (!empty($apiUrl) && !empty($filePath) && (file_exists($filePath))) {
            $data = file_get_contents($filePath);
            $response = $this->post($apiUrl, $data);
            if (($response != '')) {
                if ($response->getStatus() == 200) {
                    $result['result'] = 1;
                }
                $result['status'] =  $response->getStatus();
                $result['resultBody'] =  $response->getBody();
            }
        } else {
            $this->emarsysLogs->addErrorLog('Api Export Failed. API URL or CSV File Not Found.', $storeId, 'ApiExport::apiExport()');
        }

        return $result;
    }

    /**
     * Requests API call
     * @param $apiCall
     * @param string $method
     * @param array $data
     * @param bool $jsonDecode
     * @return mixed|string|\Zend_Http_Response
     * @throws \Zend_Http_Client_Exception
     */
    protected function _request($apiCall, $method = \Zend_Http_Client::GET, $data = [], $jsonDecode = true)
    {
        $this->setUri($this->_apiUrl);
        $this->setHeaders($this->getApiHeaders($this->_token));
        $response = '';

        try {
            if ($method == "GET" && ! (empty($data))) {
                $this->setParameterGet($data);
            } else {
                if (!empty($data)) {
                    $this->setRawData($data);
                }
            }

            $responseObject = $this->request($method);
            $response = $responseObject;
            if ($jsonDecode) {
                $response = $this->jasonHelper->jsonDecode($response);
            }
        } catch (\Exception $e) {
            $storeId = $this->storeManagerInterface->getStore()->getId();
            $this->emarsysLogs->addErrorLog(
                'API Test Connection Failed. ' . $e->getMessage(),
                $storeId,
                'ApiExport::_request()'
            );
        }

        return $response;
    }

    /**
     * @param $apiCall
     * @param array $data
     * @return mixed|string|\Zend_Http_Response
     */
    public function post($apiCall, $data = [])
    {
        if (is_object($data)) {
            $data = $data->toArray();
        }

        return $this->_request($apiCall, \Zend_Http_Client::POST, $data, false);
    }

    /**
     * Get API URL
     * @param string $entityType
     * @return string
     */
    public function getApiUrl($entityType)
    {
        if ($entityType == \Magento\Catalog\Model\Product::ENTITY) {
            $entityApiUrlKey = $this->emarsysHelper->getProductApiUrlKey();
        } else {
            $entityApiUrlKey = $this->emarsysHelper->getOrderApiUrlKey();
        }
        $emarsysApiUrl = $this->emarsysHelper->getEmarsysApiUrl();
        $apiUrl = $emarsysApiUrl . $this->_merchantId . $entityApiUrlKey;

        return $apiUrl;
    }

    /**
     * get Static Export Array for Emarsys
     * @return array
     */
    public function getCatalogExportCsvHeader()
    {
        return [
            'item',
            'available',
            'title',
            'link',
            'image',
            'category',
            'price'
        ];
    }

    /**
     * Sample Data for Catalog full export test connection.
     * @param array $headers
     * @return array
     */
    public function sampleDataCatalogExport($headers)
    {
        $sampleResult = [];
        $sampleData =  [
            'item' => 'test_product_item_1',
            'available' => 'true',
            'title' => 'test_product_title_1',
            'link' => $this->storeManagerInterface->getStore()->getBaseUrl(),
            'image' => $this->storeManagerInterface->getStore()->getBaseUrl(),
            'category' => 'test_category_1',
            'price' => '00.00'
        ];

        foreach ($headers as $item) {
            $itemVal = '';
            if (isset($sampleData[$item])) {
                $itemVal = $sampleData[$item];
            }
            array_push($sampleResult, $itemVal);
        }

        return $sampleResult;
    }

    /**
     * Get Sales Order Sample Data for Test Connection Button.
     *
     * @param array $headers
     * @return array
     */
    public function sampleDataSmartInsightExport($headers)
    {
        $sampleResult = [];
        $sampleData =  [
            'order' => '00000',
            'timestamp' => '2017-07-07T07:07:07Z',
            'customer' => 'customer_id',
            'email' => 'sample@data.com',
            'item' => 'test_product_item_1',
            'price' => '0.00',
            'quantity' => '0'
        ];

        foreach ($headers as $item) {
            $itemVal = '';
            if (isset($sampleData[$item])) {
                $itemVal = $sampleData[$item];
            }
            array_push($sampleResult, $itemVal);
        }

        return $sampleResult;
    }

    /**
     * Test Smart Insight API Credentials
     * @param $storeId
     * @return string
     */
    public function testSIExportApi($storeId)
    {
        return $this->testApiExport(\Magento\Sales\Model\Order::ENTITY, $storeId);
    }

    /**
     * Test Catalog Export Api Credentials
     * @param $storeId
     * @return array
     */
    public function testCatalogExportApi($storeId)
    {
        return $this->testApiExport(\Magento\Catalog\Model\Product::ENTITY, $storeId);
    }

    /**
     * @param $entityType
     * @param $storeId
     * @return array
     */
    private function testApiExport($entityType, $storeId)
    {
        if ($entityType == \Magento\Catalog\Model\Product::ENTITY) {
            //get product mapped attributes
            $mappedAttributes = $this->productResourceModel->getMappedProductAttribute($storeId);
            $emptyFileHeader = [];
            foreach ($mappedAttributes as $key => $value) {
                $emarsysFieldNames = $this->productResourceModel->getEmarsysFieldName($storeId, $value['emarsys_attr_code']);
                array_push($emptyFileHeader, $emarsysFieldNames);
            }
            if (empty($emptyFileHeader)) {
                $emptyFileHeader = $this->getCatalogExportCsvHeader();
            }

            $sampleData = $this->sampleDataCatalogExport($emptyFileHeader);
        } else {
            //get sales mapped attributes
            $emptyFileHeader = $this->orderResourceModel->getSalesMappedAttrs($storeId);
            if (empty($emptyFileHeader)) {
                $emptyFileHeader = $this->emarsysHelper->getSalesOrderCsvDefaultHeader();
            }
            $sampleData = $this->sampleDataSmartInsightExport($emptyFileHeader);
        }

        $data = [
            $emptyFileHeader,
            $sampleData
        ];

        $fileName = $entityType . '_test_api_export.csv';
        $fileDirectory = $this->emarsysHelper->getEmarsysMediaDirectoryPath('testconnections');
        $this->emarsysHelper->checkAndCreateFolder($fileDirectory);
        $filePath =  $fileDirectory . "/" . $fileName;

        $this->csvWriter
            ->setEnclosure('"')
            ->setDelimiter(',')
            ->saveData($filePath, $data);

        $this->_apiUrl = $apiUrl = $this->getApiUrl($entityType);
        $result = $this->apiExport($apiUrl, $filePath);

        unlink($filePath);

        if (!$result['result'] && $result['status'] == 400) {
            $result['result'] = 1;
        }

        return $result;
    }
}
