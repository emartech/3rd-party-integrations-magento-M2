<?php
/**
 * @category   Emarsys
 * @package    Emarsys_Emarsys
 * @copyright  Copyright (c) 2017 Emarsys. (http://www.emarsys.net/)
 */
namespace Emarsys\Emarsys\Model;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\Product as ProductModel;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Emarsys\Emarsys\Helper\Logs as EmarsysHelperLogs;
use Emarsys\Emarsys\Model\ResourceModel\Customer as EmarsysResourceModelCustomer;
use Emarsys\Emarsys\Model\ResourceModel\Product as ProductResourceModel;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Eav\Model\Config as EavConfig;
use Emarsys\Emarsys\Helper\Data as EmarsysDataHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Product extends AbstractModel
{
    /**
     * @var ProductFactory
     */
    protected $productCollectionFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $messageManager;

    /**
     * @var Customer
     */
    protected $customerResourceModel;

    /**
     * @var ProductResourceModel
     */
    protected $productResourceModel;

    /**
     * @var ProductModel
     */
    protected $productModel;

    /**
     * @var Logs
     */
    protected $logsHelper;

    /**
     * @var DateTime
     */
    protected $date;

    /**
     * @var CategoryFactory
     */
    protected $categoryFactory;

    /**
     * @var EavConfig
     */
    protected $eavConfig;

    /**
     * @var EmarsysDataHelper
     */
    protected $emarsysHelper;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * Product constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param MessageManagerInterface $messageManager
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param ProductFactory $productCollectionFactory
     * @param ProductModel $productModel
     * @param DateTime $date
     * @param Logs $logsHelper
     * @param Customer $customerResourceModel
     * @param ProductResourceModel $productResourceModel
     * @param CategoryFactory $categoryFactory
     * @param StoreManagerInterface $storeManager
     * @param EavConfig $eavConfig
     * @param EmarsysDataHelper $emarsysHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        MessageManagerInterface $messageManager,
        ProductFactory $productCollectionFactory,
        ProductModel $productModel,
        DateTime $date,
        EmarsysHelperLogs $logsHelper,
        EmarsysResourceModelCustomer $customerResourceModel,
        ProductResourceModel $productResourceModel,
        CategoryFactory $categoryFactory,
        StoreManagerInterface $storeManager,
        EavConfig $eavConfig,
        EmarsysDataHelper $emarsysHelper,
        ScopeConfigInterface $scopeConfig,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->storeManager = $storeManager;
        $this->messageManager = $messageManager;
        $this->customerResourceModel = $customerResourceModel;
        $this->productResourceModel = $productResourceModel;
        $this->productModel = $productModel;
        $this->logsHelper = $logsHelper;
        $this->date = $date;
        $this->categoryFactory = $categoryFactory;
        $this->eavConfig =  $eavConfig;
        $this->emarsysHelper =  $emarsysHelper;
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * constructor
     */
    public function _construct()
    {
        parent::_construct();
        $this->_init('Emarsys\Emarsys\Model\ResourceModel\Product');
    }

    /**
     * @param $storeId
     * @param $mode
     * @param null $includeBundle
     * @param null $excludedCategories
     */
    public function syncProducts($storeId, $mode, $includeBundle = null, $excludedCategories = null)
    {
        $store = $this->storeManager->getStore($storeId);
        $websiteId = $store->getWebsiteId();
        $storeCode = $store->getCode();
        $scope = 'websites';

        $logsArray['job_code'] = 'product';
        $logsArray['status'] = 'started';
        $logsArray['messages'] = __('Bulk product export started');
        $logsArray['created_at'] = $this->date->date('Y-m-d H:i:s', time());
        $logsArray['run_mode'] = $mode;
        $logsArray['auto_log'] = 'Complete';
        $logsArray['store_id'] = $storeId;
        $logsArray['website_id'] = $websiteId;
        $logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
        $logId = $this->logsHelper->manualLogs($logsArray, 1);

        $isEmarsysEnabledForStore = $this->customerResourceModel->getDataFromCoreConfig(
            'emarsys_settings/emarsys_setting/enable',
            $scope,
            $websiteId
        );

        if (!$isEmarsysEnabledForStore) {
            $logsArray['id'] = $logId;
            $logsArray['emarsys_info'] = __('Emarsys is disabled');
            $logsArray['description'] = __('Emarsys is disabled for the store %1', $store->getName());
            $logsArray['action'] = 'synced to emarsys';
            $logsArray['message_type'] = 'Error';
            $logsArray['log_action'] = 'sync';
            $this->logsHelper->logs($logsArray);
            $logsArray['status'] = 'error';
            $logsArray['messages'] = __('Catalog Bulk Export Failed');
            $this->messageManager->addErrorMessage(__('Emarsys is disabled for the store %1', $store->getName()));
            $this->logsHelper->manualLogsUpdate($logsArray);

            return;
        }

        if (is_null($includeBundle)) {
            $includeBundle = $this->scopeConfig->getValue('emarsys_predict/feed_export/include_bundle_product', $scope, $websiteId);
        }
        if (is_null($excludedCategories)) {
            $excludedCategories = $this->scopeConfig->getValue('emarsys_predict/feed_export/excludedcategories', $scope, $websiteId);
        }

        $hostname = $this->customerResourceModel->getDataFromCoreConfig(EmarsysDataHelper::XPATH_EMARSYS_FTP_HOSTNAME, $scope, $websiteId);
        $port = $this->customerResourceModel->getDataFromCoreConfig(EmarsysDataHelper::XPATH_EMARSYS_FTP_PORT, $scope, $websiteId);
        $username = $this->customerResourceModel->getDataFromCoreConfig(EmarsysDataHelper::XPATH_EMARSYS_FTP_USERNAME, $scope, $websiteId);
        $password = $this->customerResourceModel->getDataFromCoreConfig(EmarsysDataHelper::XPATH_EMARSYS_FTP_PASSWORD, $scope, $websiteId);
        $bulkDir = $this->customerResourceModel->getDataFromCoreConfig(EmarsysDataHelper::XPATH_EMARSYS_FTP_BULK_EXPORT_DIR, $scope, $websiteId);
        $ftpSsl = $this->customerResourceModel->getDataFromCoreConfig(EmarsysDataHelper::XPATH_EMARSYS_FTP_USEFTP_OVER_SSL, $scope, $websiteId);
        $passiveMode = $this->customerResourceModel->getDataFromCoreConfig(EmarsysDataHelper::XPATH_EMARSYS_FTP_USE_PASSIVE_MODE, $scope, $websiteId);

        if ($hostname != '' && $port != '' && $username != '' && $password != '') {
            $errorStatus = 0;
        } else {
            $errorStatus = 1;
        }

        if ($errorStatus != 1) {
            //check ftp connection using ftp credentials
            $checkFtpConnection = $this->emarsysHelper->checkFtpConnection(
                $hostname,
                $username,
                $password,
                $port,
                $ftpSsl,
                $passiveMode
            );

            if ($checkFtpConnection) {
                $mappedAttributes = $this->productResourceModel->getMappedProductAttribute($storeId);
                $emarsysFieldNames = [];
                $magentoAttributeNames = [];
                if (isset($mappedAttributes) && count($mappedAttributes) != '') {
                    $mappingField = 0;
                    foreach ($mappedAttributes as $mapAttribute) {
                        $emarsysFieldId = $mapAttribute['emarsys_attr_code'];
                        if ($emarsysFieldId != 0) {
                            $mappingField = 1;
                        }
                        $emarsysFieldName = $this->productResourceModel->getEmarsysFieldName($storeId, $emarsysFieldId);
                        $emarsysFieldNames[] = $emarsysFieldName;
                        $magentoAttributeNames[] = $mapAttribute['magento_attr_code'];
                    }
                    if ($mappingField == 1) {
                        $heading = $emarsysFieldNames;
                        $localFilePath = BP . "/var";
                        $outputFile = "products_" . $this->date->date('YmdHis', time()) . "_" . $storeCode . ".csv";
                        $filePath = $localFilePath . "/" . $outputFile;
                        $handle = fopen($filePath, 'w');
                        fputcsv($handle, $heading);
                        $excludeCategories = explode(',', $excludedCategories);
                        $productCollection = $this->productCollectionFactory->create()->getCollection()
                            ->addStoreFilter($storeId)
                            ->addWebsiteFilter($websiteId)
                            ->addAttributeToFilter('visibility', ["neq" => 1]);

                        foreach ($productCollection as $product) {
                            $excludeCatFlag = 0;
                            $productData = $this->productCollectionFactory->create()->setStoreId($storeId)->load($product['entity_id']);
                            $productType = $productData->getTypeId();
                            $catIds = $productData->getCategoryIds();
                            $categoryNames = [];
                            foreach ($catIds as $catId) {
                                if (in_array($catId, $excludeCategories)) {
                                    $excludeCatFlag = 1;
                                    break;
                                }
                                $cateData   = $this->categoryFactory->create()->load($catId);
                                $categoryPath = $cateData->getPath();
                                $categoryPathIds = explode('/', $categoryPath);
                                $childCats = [];
                                if (count($categoryPathIds) > 2) {
                                    $pathIndex = 0;
                                    foreach($categoryPathIds as $categoryPathId) {
                                        if($pathIndex <= 1) {
                                            $pathIndex++;
                                            continue;
                                        }
                                        $childCateData = $this->categoryFactory->create()->load($categoryPathId);
                                        $childCats[] = $childCateData->getName();
                                    }
                                    $categoryNames[] = implode(" > ", $childCats);
                                }
                            }
                            if (($includeBundle == 0 && $productType == 'bundle') || $excludeCatFlag == 1) {
                                continue;
                            }

                            $attributeData = [];
                            foreach ($magentoAttributeNames as $attributeName) {
                                $attributeOption = $productData->getData($attributeName);
                                if (!is_array($attributeOption)) {
                                    $attribute = $this->eavConfig->getAttribute('catalog_product', $attributeName);
                                    if ($attribute->getFrontendInput() == 'boolean' || $attribute->getFrontendInput() == 'select'  || $attribute->getFrontendInput() == 'multiselect' ) {
                                        $attributeOption = $productData->getAttributeText($attributeName);
                                    }
                                }
                                if (isset($attributeOption) && $attributeOption != '') {
                                    if (is_array($attributeOption)) {
                                        if ($attributeName == 'category_ids') {
                                            $attributeData[] = implode('|', $categoryNames);
                                        } elseif ($attributeName == 'quantity_and_stock_status') {
                                            if ($productData->getData('quantity_and_stock_status')['is_in_stock'] == 1) {
                                                $attributeData[] =  'TRUE';
                                            } else {
                                                $attributeData[] = 'FALSE';
                                            }
                                        } else {
                                            $attributeData[] = implode(',', $attributeOption);
                                        }
                                    } else {
                                        if ($attributeName == 'image') {
                                            $imgUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/product' . $attributeOption;
                                            $attributeData[] = str_replace('pub/', '', $imgUrl);
                                        } elseif ($attributeName == 'url_key') {
                                            $attributeData[] = $productData->getProductUrl();
                                        } else {
                                            $attributeData[] = $attributeOption;
                                        }
                                    }
                                } else {
                                    if ($attributeName == 'url_key') {
                                        $attributeData[] = $productData->getProductUrl();
                                    } else {
                                        $attributeData[] = '';
                                    }
                                }
                            }
                            fputcsv($handle, $attributeData);
                        }
                        $fileOpen = fopen($filePath, "r");
                        $remoteDirPath = $bulkDir;
                        if ($remoteDirPath == '/') {
                            $remoteFileName = $outputFile;
                        } else {
                            $remoteDirPath = rtrim($remoteDirPath, '/');
                            $remoteFileName = $remoteDirPath . "/" . $outputFile;
                        }
                        if ($ftpSsl == 1) {
                            $ftpConnection = @ftp_ssl_connect($hostname, $port);
                        } else {
                            $ftpConnection = @ftp_connect($hostname, $port);
                        }
                        //Login to FTP
                        $ftpLogin = @ftp_login($ftpConnection, $username, $password);
                        if ($passiveMode == 1) {
                            @ftp_pasv($ftpConnection, true);
                        }
                        if (!@ftp_chdir($ftpConnection, $remoteDirPath)) {
                            @ftp_mkdir($ftpConnection, $remoteDirPath);
                        }
                        @ftp_chdir($ftpConnection, '/');

                        if (@ftp_put($ftpConnection, $remoteFileName, $filePath, FTP_ASCII)) {
                            $logsArray['id'] = $logId;
                            $logsArray['emarsys_info'] = __('File uploaded to FTP server successfully');
                            $logsArray['description'] = $remoteFileName;
                            $logsArray['action'] = 'synced to emarsys';
                            $logsArray['message_type'] = 'Success';
                            $logsArray['log_action'] = 'sync';
                            $this->logsHelper->logs($logsArray);
                            $errorCount = 0;
                            if ($mode == EmarsysDataHelper::ENTITY_EXPORT_MODE_MANUAL) {
                                $this->messageManager->addSuccessMessage(
                                    __("File uploaded to FTP server successfully !!!")
                                );
                            }
                        } else {
                            $errorMessage = error_get_last();
                            $msg = isset($errorMessage['message']) ? $errorMessage['message'] : '';
                            $logsArray['id'] = $logId;
                            $logsArray['emarsys_info'] = __('Failed to upload file on FTP server');
                            $logsArray['description'] = __('Failed to upload file on FTP server %1' , $msg);
                            $logsArray['action'] = 'synced to emarsys';
                            $logsArray['message_type'] = 'Error';
                            $logsArray['log_action'] = 'sync';
                            $this->logsHelper->logs($logsArray);
                            $errorCount = 1;
                            if ($mode == EmarsysDataHelper::ENTITY_EXPORT_MODE_MANUAL) {
                                $this->messageManager->addErrorMessage(
                                    __("Failed to upload file on FTP server !!! " . $msg)
                                );
                            }
                        }
                        unlink($filePath);
                        $errorCount = 0;
                    } else {
                        $logsArray['id'] = $logId;
                        $logsArray['emarsys_info'] = __('Attributes are not mapped');
                        $logsArray['description'] = __('Failed to upload file on server. Attributes are not mapped');
                        $logsArray['action'] = 'synced to emarsys';
                        $logsArray['message_type'] = 'Error';
                        $logsArray['log_action'] = 'sync';
                        $this->logsHelper->logs($logsArray);
                        $errorCount = 1;
                        if ($mode == EmarsysDataHelper::ENTITY_EXPORT_MODE_MANUAL) {
                            $this->messageManager->addErrorMessage(
                                __("Attributes are not mapped !!!")
                            );
                        }
                    }
                } else {
                    $logsArray['id'] = $logId;
                    $logsArray['emarsys_info'] = __('Attributes are not mapped');
                    $logsArray['description'] = __('Failed to upload file on server. Attributes are not mapped');
                    $logsArray['action'] = 'synced to emarsys';
                    $logsArray['message_type'] = 'Error';
                    $logsArray['log_action'] = 'sync';
                    $this->logsHelper->logs($logsArray);
                    $errorCount = 1;
                    if ($mode == EmarsysDataHelper::ENTITY_EXPORT_MODE_MANUAL) {
                        $this->messageManager->addErrorMessage(
                            __("Product Attributes are not mapped !!!")
                        );
                    }
                }
            } else {
                $logsArray['id'] = $logId;
                $logsArray['emarsys_info'] = __('Failed to connect with FTP server.');
                $logsArray['description'] = __('Failed to connect with FTP server.');
                $logsArray['action'] = 'synced to FTP';
                $logsArray['message_type'] = 'Error';
                $logsArray['log_action'] = 'sync';
                $this->logsHelper->logs($logsArray);
                $errorCount = 1;
                if ($mode == EmarsysDataHelper::ENTITY_EXPORT_MODE_MANUAL) {
                    $this->messageManager->addErrorMessage(
                        __("Failed to connect with FTP server. Please check your settings and try again !!!")
                    );
                }
            }
        } else {
            $logsArray['id'] = $logId;
            $logsArray['emarsys_info'] = __('Invalid FTP credentials');
            $logsArray['description'] = __('Invalid FTP credential. Please check your settings and try again');
            $logsArray['action'] = 'synced to emarsys';
            $logsArray['message_type'] = 'Error';
            $logsArray['log_action'] = 'sync';
            $this->logsHelper->logs($logsArray);
            $errorCount = 1;
            if ($mode == EmarsysDataHelper::ENTITY_EXPORT_MODE_MANUAL) {
                $this->messageManager->addErrorMessage(
                    __('Invalid FTP credential. Please check your settings and try again !!!')
                );
            }
        }
        $logsArray['id'] = $logId;
        $logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
        if ($errorCount == 1) {
            $logsArray['status'] = 'error';
            $logsArray['messages'] = __('Product export have an error. Please check');
        } else {
            $logsArray['status'] = 'success';
            $logsArray['messages'] = __('Product export completed');
        }

        $this->logsHelper->manualLogsUpdate($logsArray);

        return;
    }
}
