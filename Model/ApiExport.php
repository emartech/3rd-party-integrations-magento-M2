<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Model;

use Emarsys\Emarsys\Helper\Data;
use Emarsys\Emarsys\Model\ResourceModel\Order as OrderResourceModel;
use Emarsys\Emarsys\Model\ResourceModel\Product as ProductResourceModel;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\File\Csv;
use Magento\Framework\HTTP\ZendClient;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class ApiExport
 */
class ApiExport extends ZendClient
{
    protected $_apiUrl;

    protected $_merchantId;

    protected $_token;

    protected $_gz;

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
     * @var OrderResourceModel
     */
    protected $orderResourceModel;

    /**
     * @var ProductResourceModel
     */
    protected $productResourceModel;

    /**
     * ApiExport constructor.
     *
     * @param Data $emarsysHelper
     * @param RawFactory $resultRawFactory
     * @param Csv $csvWriter
     * @param StoreManagerInterface $storeManagerInterface
     * @param OrderResourceModel $orderResourceModel
     * @param ProductResourceModel $productResourceModel
     */
    public function __construct(
        Data $emarsysHelper,
        RawFactory $resultRawFactory,
        Csv $csvWriter,
        StoreManagerInterface $storeManagerInterface,
        OrderResourceModel $orderResourceModel,
        ProductResourceModel $productResourceModel
    ) {
        $this->emarsysHelper = $emarsysHelper;
        $this->resultRawFactory = $resultRawFactory;
        $this->csvWriter = $csvWriter;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->orderResourceModel = $orderResourceModel;
        $this->productResourceModel = $productResourceModel;
    }

    /**
     * @param $merchantId
     * @param $token
     * @param bool $gz
     */
    public function assignApiCredentials($merchantId, $token, $gz = false)
    {
        $this->_merchantId = $merchantId;
        $this->_token = $token;
        $this->_gz = $gz;
    }

    /**
     * Get API Headers
     *
     * @param $token
     * @return array|bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
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
            $headers[] = "Extension-Version: " . \Emarsys\Emarsys\Helper\Data::VERSION;
            if ($this->_gz) {
                $headers[] = "Content-Encoding: gzip";
            }

            return $headers;
        }
        $storeId = $this->storeManagerInterface->getStore()->getId();
        $this->emarsysHelper->addErrorLog(
            'Api Export',
            'Api Token Not Found',
            $storeId,
            'ApiExport::getApiHeaders()'
        );

        return false;
    }

    /**
     * @param bool $apiUrl
     * @param bool $filePath
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Zend_Http_Client_Exception
     */
    public function apiExport($apiUrl = false, $filePath = false)
    {
        $this->_apiUrl = $apiUrl;
        $storeId = $this->storeManagerInterface->getStore()->getId();
        $result = [];
        $result['result'] = 0;
        $result['status'] = '';
        $result['resultBody'] = 'Api Export Failed.';

        if ($apiUrl && $filePath && file_exists($filePath)) {
            $data = file_get_contents($filePath);
            $response = $this->post($apiUrl, $data);
            if (($response != '')) {
                if ($response->getStatus() == 200) {
                    $result['result'] = 1;
                }
                $result['status'] = $response->getStatus();
                $result['resultBody'] = $response->getBody();
            } else {
                $result['resultBody'] = 'Api Export Failed. Empty response';
            }
        } else {
            $this->emarsysHelper->addErrorLog(
                'Api Export',
                'Api Export Failed. API URL or CSV File Not Found.',
                $storeId,
                'ApiExport::apiExport()'
            );
        }

        return $result;
    }

    /**
     * Requests API call
     *
     * @param $apiCall
     * @param string $method
     * @param array $data
     * @param bool $jsonDecode
     * @return mixed|string|\Zend_Http_Response
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Zend_Http_Client_Exception
     */
    protected function _request($apiCall, $method = \Zend_Http_Client::GET, $data = [], $jsonDecode = true)
    {
        $this->setUri($this->_apiUrl);
        $this->setHeaders($this->getApiHeaders($this->_token));
        $response = '';

        try {
            if ($method == "GET" && !(empty($data))) {
                $this->setParameterGet($data);
            } else {
                if (!empty($data)) {
                    $this->setRawData($data);
                }
            }

            $responseObject = $this->request($method);
            $response = $responseObject;
            if ($jsonDecode) {
                $response = \Zend_Json::decode($response);
            }
        } catch (\Exception $e) {
            $this->emarsysHelper->addErrorLog(
                'API Test Connection. ' . $e->getMessage(),
                'API Test Connection Failed. | ' . $e->getMessage(),
                $this->storeManagerInterface->getStore()->getId(),
                'ApiExport::_request()'
            );
        }

        return $response;
    }

    /**
     * @param $apiCall
     * @param array $data
     * @return mixed|string|\Zend_Http_Response
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Zend_Http_Client_Exception
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
     *
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
        $this->_apiUrl = $emarsysApiUrl . $this->_merchantId . $entityApiUrlKey;

        return $this->_apiUrl;
    }

    /**
     * Get Static Export Array for Emarsys
     *
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
            'price',
        ];
    }

    /**
     * Sample Data for Catalog full export test connection.
     *
     * @param array $headers
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function sampleDataCatalogExport($headers)
    {
        $sampleResult = [];
        $sampleData = [
            'item' => 'test_product_item_1',
            'available' => 'true',
            'title' => 'test_product_title_1',
            'link' => $this->storeManagerInterface->getStore()->getBaseUrl(),
            'image' => $this->storeManagerInterface->getStore()->getBaseUrl(),
            'category' => 'test_category_1',
            'price' => '00.00',
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
     * @return array
     */
    public function sampleDataSmartInsightExport()
    {
        //header ['order', 'timestamp', 'email', 'item', 'price', 'quantity'];
        return [uniqid(), '2017-07-07', 'sample@data.com', 'test_product_item_1','0.00', '0'];
    }

    /**
     * Test Smart Insight API Credentials
     *
     * @param $storeId
     * @return array
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Zend_Http_Client_Exception
     */
    public function testSIExportApi($storeId)
    {
        return $this->testApiExport(\Magento\Sales\Model\Order::ENTITY, $storeId);
    }

    /**
     * Test Catalog Export Api Credentials
     *
     * @param $storeId
     * @return array
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Zend_Http_Client_Exception
     */
    public function testCatalogExportApi($storeId)
    {
        return $this->testApiExport(\Magento\Catalog\Model\Product::ENTITY, $storeId);
    }

    /**
     * @param $entityType
     *
     * @param $storeId
     * @return array
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Zend_Http_Client_Exception
     */
    private function testApiExport($entityType, $storeId)
    {
        if ($entityType == \Magento\Catalog\Model\Product::ENTITY) {
            $emptyFileHeader = [];
            $mappedAttributes = $this->productResourceModel->getMappedProductAttribute($this->emarsysHelper->getFirstStoreIdOfWebsiteByStoreId($storeId));
            foreach ($mappedAttributes as $key => $value) {
                $emarsysFieldNames = $this->productResourceModel->getEmarsysFieldName(
                    $this->emarsysHelper->getFirstStoreIdOfWebsiteByStoreId($storeId),
                    $value['emarsys_attr_code']
                );
                array_push($emptyFileHeader, $emarsysFieldNames);
            }

            if (empty($emptyFileHeader)) {
                $emptyFileHeader = $this->getCatalogExportCsvHeader();
            }

            $sampleData = $this->sampleDataCatalogExport($emptyFileHeader);
        } else {
            //get sales mapped attributes
            $emptyFileHeader = $this->emarsysHelper->getSalesOrderCsvDefaultHeader();
            $sampleData = $this->sampleDataSmartInsightExport();
        }

        $data = [
            $emptyFileHeader,
            $sampleData,
        ];

        $fileName = $entityType . '_test_api_export.csv';
        $fileDirectory = $this->emarsysHelper->getEmarsysMediaDirectoryPath('testconnections');
        $this->emarsysHelper->checkAndCreateFolder($fileDirectory);
        $filePath = $fileDirectory . "/" . $fileName;

        $this->csvWriter
            ->setEnclosure('"')
            ->setDelimiter(',')
            ->saveData($filePath, $data);

        $this->getApiUrl($entityType);
        $result = $this->apiExport($this->_apiUrl, $filePath);

        if (!$result['result'] && $result['status'] == 400) {
            $result['result'] = 1;
        }

        return $result;
    }
}
