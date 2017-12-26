<?php
/**
 * @category   Emarsys
 * @package    Emarsys_Emarsys
 * @copyright  Copyright (c) 2017 Emarsys. (http://www.emarsys.net/)
 */
namespace Emarsys\Emarsys;

use Magento\Framework\App\Action\Context;
use Sabre\DAV\Client;
use Emarsys\Emarsys\Helper\Data as EmarsysDataHelper;
use Emarsys\Emarsys\Model\Observer\RealTimeCustomer;
use Emarsys\Emarsys\Model\ResourceModel\Customer as EmarsysCustomerResourceModel;
use Psr\Log\LoggerInterface;
use Emarsys\Emarsys\Model\Api\Contact;
use Emarsys\Emarsys\Helper\Event;
use Magento\Backend\App\Action\Context as BackendContext;
use Magento\Customer\Model\CustomerFactory;
use Emarsys\Emarsys\Model\Api as EmarsysModelApi;
use Emarsys\Emarsys\Model\ResourceModel\Order;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\StoreManagerInterface;
use Emarsys\Emarsys\Model\QueueFactory;
use Magento\Framework\App\Request\Http;
use Emarsys\Emarsys\Helper\Logs;
use Magento\Config\Model\ResourceModel\Config;
use Emarsys\Emarsys\Model\Logs as EmarsysModelLogs;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\Product;
use Emarsys\Emarsys\Model\ResourceModel\Product as EmarsysProductResourceModel;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Model\Order\CreditmemoRepository;
use Magento\Sales\Model\OrderFactory;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Eav\Model\Config as EavConfigModel;
use Magento\Framework\Registry;
use Emarsys\Emarsys\Model\Api\Api as EmarsysApiApi;
use Magento\Framework\App\Cache\TypeListInterface;
use Emarsys\Emarsys\Model\Product as EmarsysProductModel;


/**
 * Class CronSync
 * @package Emarsys\Emarsys
 */
class CronSync
{
    protected $customerObserver;

    protected $logger;

    protected $orderResourceModel;

    const BATCH_SIZE = 1000;

    protected $categoryFactory;

    /**
     * @var \Magento\Eav\Model\Config
     */
    protected $eavConfig;

    /**
     * @var EmarsysProductModel
     */
    protected $emarsysProductModel;

    /**
     * @var
     */
    protected $emarsysHelper;

    /**
     * CronSync constructor.
     *
     * @param RealTimeCustomer $customerObserver
     * @param EmarsysCustomerResourceModel $customerResourceModel
     * @param LoggerInterface $logger
     * @param Contact $contactModel
     * @param Event $eventHelper
     * @param BackendContext $context
     * @param CustomerFactory $customer
     * @param EmarsysModelApi $modelApi
     * @param Order $orderResourceModel
     * @param DateTime $date
     * @param StoreManagerInterface $storeManager
     * @param QueueFactory $queueModel
     * @param Http $request
     * @param Logs $logsHelper
     * @param Config $resourceConfig
     * @param EmarsysModelLogs $emarsysLogs
     * @param ProductFactory $productCollectionFactory
     * @param Product $productModel
     * @param EmarsysProductResourceModel $productResourceModel
     * @param ScopeConfigInterface $scopeConfig
     * @param CreditmemoRepository $creditmemoRepository
     * @param OrderFactory $salesOrderCollectionFactory
     * @param CategoryFactory $categoryFactory
     * @param EmarsysDataHelper $emarsysDataHelper
     * @param EavConfigModel $eavConfig
     * @param Registry $registry
     * @param EmarsysApiApi $api
     * @param TypeListInterface $cacheTypeList
     * @param EmarsysProductModel $emarsysProductModel
     */
    public function __construct(
        RealTimeCustomer $customerObserver,
        EmarsysCustomerResourceModel $customerResourceModel,
        LoggerInterface $logger,
        Contact $contactModel,
        Event $eventHelper,
        BackendContext $context,
        CustomerFactory $customer,
        EmarsysModelApi $modelApi,
        Order $orderResourceModel,
        DateTime $date,
        StoreManagerInterface $storeManager,
        QueueFactory $queueModel,
        Http $request,
        Logs $logsHelper,
        Config $resourceConfig,
        EmarsysModelLogs $emarsysLogs,
        ProductFactory $productCollectionFactory,
        Product $productModel,
        EmarsysProductResourceModel $productResourceModel,
        ScopeConfigInterface $scopeConfig,
        CreditmemoRepository $creditmemoRepository,
        OrderFactory $salesOrderCollectionFactory,
        CategoryFactory $categoryFactory,
        EmarsysDataHelper $emarsysDataHelper,
        EavConfigModel $eavConfig,
        Registry $registry,
        EmarsysApiApi $api,
        TypeListInterface $cacheTypeList,
        EmarsysProductModel $emarsysProductModel,
        \Emarsys\Emarsys\Model\Order $emarsysOrderModel
    ) {
        $this->customerObserver = $customerObserver;
        $this->customerResourceModel = $customerResourceModel;
        $this->contactModel = $contactModel;
        $this->logger = $logger;
        $this->emarsysLogs = $emarsysLogs;
        $this->eventHelper = $eventHelper;
        $this->customer = $customer;
        $this->modelApi = $modelApi;
        $this->queueModel = $queueModel;
        $this->request = $request;
        $this->orderResourceModel = $orderResourceModel;
        $this->date = $date;
        $this->storeManager = $storeManager;
        $this->logsHelper = $logsHelper;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productModel = $productModel;
        $this->productResourceModel = $productResourceModel;
        $this->scopeConfig = $scopeConfig;
        $this->creditmemoRepository = $creditmemoRepository;
        $this->salesOrderCollectionFactory = $salesOrderCollectionFactory;
        $this->categoryFactory = $categoryFactory;
        $this->emarsysDataHelper = $emarsysDataHelper;
        $this->eavConfig = $eavConfig;
        $this->registry = $registry;
        $this->resourceConfig = $resourceConfig;
        $this->UrlInterface = $context->getUrl();
        $this->_cacheTypeList = $cacheTypeList;
        $this->api = $api;
        $this->emarsysProductModel =  $emarsysProductModel;
        $this->emarsysOrderModel = $emarsysOrderModel;
    }

    /**
     * @param $schedule
     */
    public function cronOrderSyncQueue($schedule)
    {
        $allStores = $this->orderResourceModel->getStores();

        foreach ($allStores as $store) {
            $storeId = $store['store_id'];
            if ($storeId == 0) {
                continue;
            }
            $this->emarsysOrderModel->syncOrders(
                $storeId,
                EmarsysDataHelper::ENTITY_EXPORT_MODE_AUTOMATIC
            );
        }
    }

    /**
     * @param $schedule
     */
    public function cronProductSync($schedule)
    {
		set_time_limit(0);
        $allStores = $this->orderResourceModel->getStores();
        foreach ($allStores as $store) {
            if ($store['store_id'] == 0) {
                continue;
            }
            $storeId = $store['store_id'];
            $this->emarsysProductModel->syncProducts(
                $storeId,
                EmarsysDataHelper::ENTITY_EXPORT_MODE_AUTOMATIC
            );
        }
    }

    /**
     * @param $schedule
     */
    public function cronCustomerSyncQueue($schedule)
    {
        try {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $storeManager = $objectManager->get('Magento\Store\Model\StoreManagerInterface');
            $stores = $storeManager->getStores($withDefault = false);
            $scopeConfig = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface');

            foreach ($stores as $store) {
                $storeId = $store->getData()['store_id'];
                $storeCode = $store->getData()['code'];
                $scope = 'websites';
                $websiteId = $store->getData()['website_id'];
                $scopeId = $websiteId;
                $websiteCode = $storeManager->getStore($storeId)->getWebsite()->getCode();

                $logsArray['job_code'] = 'subscriber';
                $logsArray['status'] = 'started';
                $logsArray['messages'] = __('bulk subscriber export started');
                $logsArray['created_at'] = $this->date->date('Y-m-d H:i:s', time());
                $logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
                $logsArray['run_mode'] = 'Automatic';
                $logsArray['auto_log'] = 'Complete';
                $logsArray['website_id'] = $websiteId;
                $logsArray['store_id'] = $storeId;
                $logId = $this->logsHelper->manualLogs($logsArray, 1);
                $logsArray['id'] = $logId;
                $logsArray['emarsys_info'] = __('Subscriber Export Started');
                $logsArray['description'] = __('Subscriber Export Started for Store ID : %1', $storeId);
                $logsArray['action'] = 'synced to emarsys';
                $logsArray['message_type'] = 'Success';
                $logsArray['log_action'] = 'sync';
                $logsArray['website_id'] = $websiteId;
                $this->logsHelper->logs($logsArray);

                $webDavUrl = $this->customerResourceModel->getDataFromCoreConfig('emarsys_settings/webdav_setting/webdav_url', $scope, $scopeId);
                $webDavUser = $this->customerResourceModel->getDataFromCoreConfig('emarsys_settings/webdav_setting/webdav_user', $scope, $scopeId);
                $webDavPass = $this->customerResourceModel->getDataFromCoreConfig('emarsys_settings/webdav_setting/webdav_password', $scope, $scopeId);
                if ($webDavUrl != '' && $webDavUser != '' && $webDavPass != '') {
                    $errorStatus = 0;
                } else {
                    $errorStatus = 1;
                }
                if ($errorStatus != 1) {
                    $settings = array(
                        'baseUri' => $webDavUrl,
                        'userName' => $webDavUser,
                        'password' => $webDavPass,
                        'proxy' => '',
                    );
                    $client = new Client($settings);
                    $customervalues = array();
                    $optInStatus = $this->customerResourceModel->getDataFromCoreConfig('contacts_synchronization/initial_db_load/initial_db_load', $scope, $scopeId);
                    $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
                    $newsLetSubTableName = $resource->getTableName('newsletter_subscriber');

                    $queueCollection = $this->queueModel->create()->getCollection();
                    $queueCollection->addFieldToSelect('entity_id');
                    $queueCollection->addFieldToFilter('main_table.entity_type_id', 2);
                    $queueCollection->addFieldToFilter('main_table.store_id', $storeId);
                    $queueCollection->getSelect()->joinLeft(
                        ['newsletter_subscriber' => $newsLetSubTableName],
                        'main_table.entity_id = newsletter_subscriber.subscriber_id', array('subscriber_email', 'subscriber_confirm_code', 'subscriber_status'));
                    //echo $queueCollection->getSelect(); exit;
                    if ($queueCollection->getData()) {
                        $this->updateLastModifiedContacts($queueCollection, $storeId);
                    }
                    if (isset($scopeId)) {
                            $customervalues = $queueCollection;
                    }
                    $emarsysFieldNames = array();
                    $emarsysFieldId = 3;
                    $emarsysFieldNames[] = $this->customerResourceModel->getEmarsysFieldName($storeId, $emarsysFieldId);
                    if ($optInStatus != '') {
                        $emarsysFieldNames[] = 'Opt-In';
                    }
                    $heading = $emarsysFieldNames;
                    $localFilePath = BP . "/var";
                    $outputFile = "subscribers_" . $this->date->date('YmdHis', time()) . "_" . $storeCode.".csv";
                    $filePath = $localFilePath . "/" . $outputFile;
                    $handle = fopen($filePath, 'w');
                    fputcsv($handle, $heading);

                    foreach ($customervalues as $value) {
                        $values = array();
                        $values[] = $value['subscriber_email'];
                        if ($value['subscriber_status'] != 1) {
                            $values[] = '2';
                        } else {
                            $values[] = '1';
                        }
                        fputcsv($handle, $values);
                    }
                    $file = $outputFile;
                    $fileOpen = fopen($filePath, "r");
                    $response = $client->request('PUT', $file, $fileOpen);
                    unlink($filePath);
                    $errorCount = 0;
                    if ($response['statusCode'] == '201') {
                        $fileLoc = $response['headers']['location']['0'];
                        $logsArray['id'] = $logId;
                        $logsArray['emarsys_info'] = __('File uploaded to server successfully');
                        $logsArray['description'] = $fileLoc;
                        $logsArray['action'] = 'synced to emarsys';
                        $logsArray['website_id'] = $websiteId;
                        $logsArray['message_type'] = 'Success';
                        $logsArray['log_action'] = 'sync';
                        $this->logsHelper->logs($logsArray);
                        $logsArray['website_id'] = $websiteId;
                        $logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
                        $errorCount = 0;
                        foreach ($customervalues as $value) {
                            $test = $this->queueModel->create()->load($value['entity_id'],'entity_id')->delete();
                        }
                    } else {

                        $logsArray['id'] = $logId;
                        $logsArray['emarsys_info'] = __('Failed to upload file on server');
                        $logsArray['description'] = strip_tags($response['body']);
                        $logsArray['action'] = 'synced to emarsys';
                        $logsArray['message_type'] = 'Error';
                        $logsArray['log_action'] = 'sync';
                        $logsArray['website_id'] = $websiteId;
                        $this->logsHelper->logs($logsArray);
                        $logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
                        $errorCount = 1;
                    }
                } else {
                    $logsArray['id'] = $logId;
                    $logsArray['emarsys_info'] = 'Invalid credentials';
                    $logsArray['description'] = __('Invalid credential. Please check your settings and try again');
                    $logsArray['action'] = 'synced to emarsys';
                    $logsArray['message_type'] = 'Error';
                    $logsArray['website_id'] = $websiteId;
                    $logsArray['log_action'] = 'sync';
                    $this->logsHelper->logs($logsArray);
                    $logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
                    $errorCount = 1;
                }
                $logsArray['id'] = $logId;
                $logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
                $logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
                if ($errorCount == 1) {
                    $logsArray['status'] = 'error';
                    $logsArray['messages'] = __('Subscriber export have an error. Please check');
                } else {
                    $logsArray['status'] = 'success';
                    $logsArray['messages'] = __('Subscriber export completed');
                }
                $this->logsHelper->manualLogsUpdate($logsArray);
                // customer export start
                $queueCollection = $this->queueModel->create()->getCollection();
                $queueCollection->addFieldToSelect('entity_id');
                $queueCollection->addFieldToFilter('entity_type_id', 1);
                $cusArray = array();
                $cusColl = $queueCollection->getData();
                foreach ($cusColl as $key => $value) {
                    $cusArray[] = $value['entity_id'];
                }
                $custModel = $this->customer->create();
                $cusColl = '';
                if($cusArray) {
                    $cusColl = $custModel->getCollection();
                    $cusColl->addAttributeToFilter('entity_id', $cusArray);
                }
                $logsArray['job_code'] = 'customer';
                $logsArray['status'] = 'started';
                $logsArray['messages'] = __('bulk customer export started');
                $logsArray['created_at'] = $this->date->date('Y-m-d H:i:s', time());
                $logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
                $logsArray['run_mode'] = 'Automatic';
                $logsArray['auto_log'] = 'Complete';
                $logsArray['store_id'] = $storeId;
                $logId1 = $this->logsHelper->manualLogs($logsArray, 1);
                $logsArray['id'] = $logId1;
                $logsArray['emarsys_info'] = 'Customer Export Started';
                $logsArray['description'] = __('Customer Export Started for Store ID : %1', $storeId);
                $logsArray['action'] = 'synced to emarsys';
                $logsArray['message_type'] = 'Success';
                $logsArray['log_action'] = 'sync';
                $logsArray['website_id'] = $websiteId;
                $this->logsHelper->logs($logsArray);
                if ($errorStatus != 1) {
                    $settings = array(
                        'baseUri' => $webDavUrl,
                        'userName' => $webDavUser,
                        'password' => $webDavPass,
                        'proxy' => '',
                    );

                    $client = new Client($settings);
                    $customervalues = [];
                    $customerData = $this->customer->create();

                    if (isset($scopeId)) {
                        $websiteId = $this->storeManager->getStore($storeId)->getWebsiteId();
                        $data['website'] = $websiteId;
                        $customervalues = $cusColl;
                    }
                    $mappedAttributes = $this->customerResourceModel->getMappedCustomerAttribute($scopeId);
                    $emarsysFieldNames = [];
                    if (isset($mappedAttributes) && count($mappedAttributes) != '') {
                        foreach ($mappedAttributes as $mapAttribute) {
                            if ($mapAttribute['emarsys_contact_field'] == NULL) { continue; }
                            $emarsysFieldId = $mapAttribute['emarsys_contact_field'];
                            $magentoFieldIds[] = $mapAttribute['magento_attribute_id'];
                            $emarsysFieldName = $this->customerResourceModel->getEmarsysFieldName($scopeId, $emarsysFieldId);
                            $emarsysFieldNames[] = $emarsysFieldName;
                            $indexCount++;
                        }
                        $emarsysFieldNames['magento_customer_id'] = 'Magento Customer ID';
                        $emarsysFieldNamesIndex[$indexCount] = 'magento_customer_id';
                        $emarsysFieldNames['magento_customer_unique_id'] = 'Magento Customer Unique ID';
                        $indexCount = $indexCount + 1;
                        $emarsysFieldNamesIndex[$indexCount] = 'magento_customer_unique_id';
                        $emarsysFieldNames['customer_email'] = 'Customer Email';
                        $indexCount = $indexCount + 1;
                        $emarsysFieldNamesIndex[$indexCount] = 'customer_email';
                        $heading = $emarsysFieldNames;
                        $localFilePath = BP . "/var";
                        $outputFile = "customers_" . $this->date->date('YmdHis', time()) . "_" . $websiteCode . ".csv";
                        $filePath = $localFilePath . "/" . $outputFile;
                        $handle = fopen($filePath, 'w');
                        fputcsv($handle, $heading);
                        $magentoFieldCodes = [];
                        foreach ($magentoFieldIds as $field) {
                            $attData = $this->customerResourceModel->getAttributeCodeById($field);
                            if ($attData['attribute_code']) {
                                $magentoFieldCodes[$attData['attribute_code']] = $attData['attribute_code'];
                            }else{
                                $magentoFieldCodes[$attData['attribute_code']] ='';
                            }
                        }
                        $allAttsUsed = [];
                        if($customervalues) {
                            foreach ($customervalues as $value) {
                                $values = [];
                                $value = array_replace(array_flip($magentoFieldCodes), $value->getData());
                                foreach ($value as $key => $cusData) {
                                    $attData = $this->customerResourceModel->getAttributeIdByCode($key);
                                    if (in_array($attData['attribute_id'], $magentoFieldIds)) {
                                        $allAttsUsed[] = $key;
                                        if (isset($cusData)) {
                                            $values[] = $cusData;
                                        } else {
                                            $values[] = '';
                                        }
                                    }
                                }
                                if (isset($value['default_billing']) && $value['default_billing'] != NULL) {
                                    $magentoAddFields = array();
                                    $customerBillingAddress = $this->customerResourceModel->getCustPriBillAddress($value['entity_id']);
                                    if ($customerBillingAddress)
                                        foreach ($customerBillingAddress->getData() as $key => $dataValue) {
                                            $attData = $this->customerResourceModel->getAttributeIdByCode($key);
                                            if (!is_array($dataValue) && in_array($attData['attribute_id'], $magentoFieldIds)) {
                                                $magentoAddFields[$key] = $dataValue;
                                            }

                                        }
                                    $magentoFlipFields = array_replace(array_flip($magentoFieldCodes), $magentoAddFields);
                                    foreach ($magentoFlipFields as $key => $cusData) {
                                        $attData = $this->customerResourceModel->getAttributeIdByCode($key);
                                        if (in_array($attData['attribute_id'], $magentoFieldIds)) {
                                            if (isset($magentoAddFields[$key])) {
                                                $attKey = array_search($key, $allAttsUsed);
                                                $values[$attKey] = $magentoAddFields[$key];
                                            }
                                        }
                                    }
                                }
                                if (isset($value['default_shipping']) && $value['default_shipping'] != NULL) {
                                    $magentoAddFields = array();
                                    $customerBillingAddress = $this->customerResourceModel->getCustPriShipAddress($value['entity_id']);
                                    if ($customerBillingAddress)
                                        foreach ($customerBillingAddress->getData() as $key => $dataValue) {
                                            $attData = $this->customerResourceModel->getAttributeIdByCode($key);
                                            if (!is_array($dataValue) && in_array($attData['attribute_id'], $magentoFieldIds)) {
                                                $magentoAddFields[$key] = $dataValue;
                                            }

                                        }
                                    $magentoFlipFields = array_replace(array_flip($magentoFieldCodes), $magentoAddFields);
                                    foreach ($magentoFlipFields as $key => $cusData) {
                                        $attData = $this->customerResourceModel->getAttributeIdByCode($key);
                                        if (in_array($attData['attribute_id'], $magentoFieldIds)) {
                                            if (isset($magentoAddFields[$key])) {
                                                $attKey = array_search($key, $allAttsUsed);
                                                $values[$attKey] = $magentoAddFields[$key];
                                            }
                                        }

                                    }
                                }
                                $values[] = $value['entity_id'];
                                $values[] = $value['email'] . "#" . $value['website_id'] . "#" . $value['store_id'];
                                $values[] = $value['email'];
                                fputcsv($handle, $values);
                            }
                        }
                        $file = $outputFile;
                        $fileOpen = fopen($filePath, "r");
                        $response = $client->request('PUT', $file, $fileOpen);
                        unlink($filePath);
                        $errorCount = 0;
                        if ($response['statusCode'] == '201') {
                            $fileLoc = $response['headers']['location']['0'];
                            $logsArray['id'] = $logId1;
                            $logsArray['emarsys_info'] = __('File uploaded to server successfully');
                            $logsArray['description'] = $fileLoc;
                            $logsArray['action'] = 'synced to emarsys';
                            $logsArray['message_type'] = 'Success';
                            $logsArray['log_action'] = 'sync';
                            $logsArray['website_id'] = $websiteId;
                            $this->logsHelper->logs($logsArray);
                            $logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
                            $errorCount = 0;
                            if ($customervalues) {
                                foreach ($customervalues as $value) {
                                    $test = $this->queueModel->create()->load($value['entity_id'],'entity_id')->delete();
                                }
                            }
                        } else {
                            $logsArray['id'] = $logId1;
                            $logsArray['emarsys_info'] = __('Failed to upload file on server');
                            $logsArray['description'] = strip_tags($response['body']);
                            $logsArray['action'] = 'synced to emarsys';
                            $logsArray['message_type'] = 'Error';
                            $logsArray['log_action'] = 'sync';
                            $logsArray['website_id'] = $websiteId;
                            $this->logsHelper->logs($logsArray);
                            $logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
                            $errorCount = 1;
                        }
                    } else {
                        $logsArray['id'] = $logId1;
                        $logsArray['emarsys_info'] = __('Attributes are not mapped');
                        $logsArray['description'] = __('Failed to upload file on server. Attributes are not mapped');
                        $logsArray['action'] = 'synced to emarsys';
                        $logsArray['message_type'] = 'Error';
                        $logsArray['log_action'] = 'sync';
                        $logsArray['website_id'] = $websiteId;
                        $this->logsHelper->logs($logsArray);
                        $logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
                        $errorCount = 1;
                    }
                } else {
                    $logsArray['id'] = $logId1;
                    $logsArray['emarsys_info'] = 'Invalid credentials';
                    $logsArray['description'] = __('Invalid credential. Please check your settings and try again');
                    $logsArray['action'] = 'synced to emarsys';
                    $logsArray['message_type'] = 'Error';
                    $logsArray['log_action'] = 'sync';
                    $logsArray['website_id'] = $websiteId;
                    $this->logsHelper->logs($logsArray);
                    $logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
                    $errorCount = 1;
                }
                $logsArray['id'] = $logId1;
                $logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
                $logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
                if ($errorCount == 1) {
                    $logsArray['status'] = 'error';
                    $logsArray['messages'] = __('Customer export have an error. Please check');
                } else {
                    $logsArray['status'] = 'success';
                    $logsArray['messages'] = __('Customer export completed');
                }
                $this->logsHelper->manualLogsUpdate($logsArray);
            }
        } catch(\Excepiton $e) {
            $this->emarsysLogs->addErrorLog($e->getMessage(),$storeId,'SaveSchema(Product)');
        }
    }

    /**
     * @param $schedule
     * @throws \Exception
     */
    public function cronEmarsysSchemaCheck($schedule)
    {
        $websites = $this->storeManager->getWebsites();
        foreach($websites as $website){
            $websiteId = $website->getWebsiteId();
            $emarsysApiIds = array();
            $this->api->setWebsiteId($websiteId);
            $response = $this->api->sendRequest('GET', 'event');
            if($response['body']['data']){
                foreach($response['body']['data'] as $eventInfo)
                $emarsysApiIds[] = $eventInfo['id'];
            }
            $emarsysLocalIds = $this->eventHelper->getLocalEmarsysEvents($websiteId);
            $result = array_diff($emarsysApiIds, $emarsysLocalIds);
            if(count($result)){
                $this->eventHelper->saveEmarsysEventSchemaNotification();
                break;
            }
        }
    }

    /**
     * @param $collection
     * @param $storeId
     */
    public function updateLastModifiedContacts($collection, $storeId)
    {
        try {
            $currentPageNumber = 1;
            $collection->setPageSize(self::BATCH_SIZE);
            $lastPageNumber = $collection->getLastPageNumber();

            while ($currentPageNumber <= $lastPageNumber) {
                $subscriberIds = [];
                if ($currentPageNumber != 1) {
                    $collection->setPageSize(self::BATCH_SIZE)
                        ->setCurPage($currentPageNumber);
                }
                if (count($collection)) {
                    $subscriberIds = $collection->getColumnValues('entity_id');
                    if (count($subscriberIds)) {
                        $this->emarsysDataHelper->backgroudTimeBasedOptinSync($subscriberIds, $storeId);
                    }
                }
                $currentPageNumber = $currentPageNumber + 1;
            }
        } catch (\Exception $e) {
            $this->emarsysLogs->addErrorLog($e->getMessage(), $storeId, 'updateLastModifiedContacts($collection,$storeId)');
        }
    }

    /**
     * syncContactsSubscriptionData for import emsrays optin changes to magento once in a day.
     */
    public function syncContactsSubscriptionData()
    {
        $queue = [];
        $websites = $this->storeManager->getWebsites();
        foreach ($websites as $website) {
            $logsArray['job_code'] = 'Sync contact Export';
            $logsArray['status'] = 'started';
            $logsArray['messages'] = __('Running Sync Contacts Subscription Data');
            $logsArray['description'] = __('Started Sync Contacts Subscription Data');
            $logsArray['created_at'] = $this->date->date('Y-m-d H:i:s', time());
            $logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
            $logsArray['run_mode'] = 'Automatic';
            $logsArray['auto_log'] = 'Complete';
            $logsArray['website_id'] = $website->getWebsiteId();
            $logsArray['store_id'] = $website->getDefaultStore()->getId();
            $logId = $this->logsHelper->manualLogs($logsArray);
            try {
                $enable = $this->scopeConfig->getValue('emarsys_settings/emarsys_setting/enable', 'websites', $website->getWebsiteId());
                if ($enable) {
                    $emarsysUserName = $this->scopeConfig->getValue('emarsys_settings/emarsys_setting/emarsys_api_username', 'websites', $website->getWebsiteId());
                    if (!array_key_exists($emarsysUserName, $queue)) {
                        $queue[$emarsysUserName] = array();
                    }
                    $queue[$emarsysUserName][] = $website->getWebsiteId();
                }
            } catch (\Exception $e) {
                $this->emarsysLogs->addErrorLog($e->getMessage(), $this->storeManager->getStore()->getId(), 'syncContactsSubscriptionData(helper/data)');
            }
        }
        if (count($queue) > 0) {
            foreach ($queue as $websiteId) {
                $this->requestSubscriptionUpdates($websiteId, true);
            }
        }
    }

     /**
     * API Request to get updates
     */
    public function requestSubscriptionUpdates(array $websiteId, $isTimeBased = false)
    {
        try {
            $logsArray['job_code'] = 'Sync contact Export';
            $logsArray['status'] = 'started';
            $logsArray['messages'] = __('Running requestSubscriptionUpdates');
            $logsArray['description'] = __('Started Sync Contacts Subscription Data');
            $logsArray['created_at'] = $this->date->date('Y-m-d H:i:s', time());
            $logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
            $logsArray['run_mode'] = 'Automatic';
            $logsArray['auto_log'] = 'Complete';
            $logsArray['website_id'] = current($websiteId);
            $logsArray['store_id'] = $this->storeManager->getWebsite(current($websiteId))->getDefaultStore()->getId();
            $logId = $this->logsHelper->manualLogs($logsArray);
            $client = $this->emarsysDataHelper->getClient();
            $dt = (new \Zend_Date());
            if ($isTimeBased) {
                $timeRange = array($dt->subHour(1)->toString('YYYY-MM-dd'), $dt->addHour(1)->toString('YYYY-MM-dd'));
            }
            $storeId = $this->storeManager->getWebsite(current($websiteId))->getDefaultStore()->getId();
            $key_id = $this->customerResourceModel->getEmarsysFieldId('Magento Subscriber ID', $storeId);
            $optinFiledId = $this->customerResourceModel->getEmarsysFieldId('Opt-In', $storeId);
            $payload = [
                'distribution_method' => 'local',
                'origin' => 'all',
                'origin_id' => '0',
                'contact_fields' => array($key_id, $optinFiledId),
                'add_field_names_header' => 1,
                'time_range' => $timeRange,
                'notification_url' => $this->getExportsNotificationUrl($websiteId, $isTimeBased, $storeId)
            ];
            $logsArray['id'] = $logId;
            $logsArray['emarsys_info'] = 'test';
            $logsArray['description'] = $this->getExportsNotificationUrl($websiteId, $isTimeBased, $storeId);
            $logsArray['action'] = 'synced to emarsys';
            $logsArray['message_type'] = 'Success';
            $logsArray['log_action'] = 'sync';
            $this->logsHelper->logs($logsArray);
            $this->emarsysDataHelper->getEmarsysAPIDetails($storeId);
            $this->emarsysDataHelper->getClient();
            $response = $this->modelApi->post('contact/getchanges', $payload);

            $logsArray['id'] = $logId;
            $logsArray['emarsys_info'] = 'test';
            $logsArray['description'] = print_r($response,true);
            $logsArray['action'] = 'synced to emarsys';
            $logsArray['message_type'] = 'Success';
            $logsArray['log_action'] = 'sync';
            $this->logsHelper->logs($logsArray);
            if (isset($response['data']['id'])) {
                $this->setValue('export_id', $response['data']['id'], current($websiteId));
                $logsArray['id'] = $logId;
                $logsArray['emarsys_info'] = 'test';
                $logsArray['description'] = $response['data']['id'];
                $logsArray['action'] = 'synced to emarsys';
                $logsArray['message_type'] = 'Success';
                $logsArray['log_action'] = 'sync';
                $this->logsHelper->logs($logsArray);
            }
        } catch (\Exception $e) {
            $this->emarsysLogs->addErrorLog($e->getMessage(), $storeId, 'requestSubscriptionUpdates(helper/data)');
        }
    }

    /**
     * @param int $websiteId
     * @param bool $isTimeBased
     * @param $storeId
     * @return string
     */
    public function getExportsNotificationUrl($websiteId = 0, $isTimeBased = false, $storeId)
    {
        try {
            $oldEntryPoint = $this->registry->registry('custom_entry_point');
            if ($oldEntryPoint) {
                $this->registry->unregister('custom_entry_point');
            }
            $this->registry->register('custom_entry_point', 'index.php');

            if ($isTimeBased) {
                $url = $this->storeManager->getStore($storeId)->getBaseUrl().'emarsys/index/sync?_store='.$storeId.'&secret='.$this->scopeConfig->getValue('contacts_synchronization/emarsys_emarsys/notification_secret_key').'&website_ids='.implode(',', $websiteId).'&timebased=1';
            }
            $this->registry->unregister('custom_entry_point');

            if ($oldEntryPoint) {
                $this->registry->register('custom_entry_point', $oldEntryPoint);
            }

            return $url;
        } catch (\Exception $e) {
            $this->emarsysLogs->addErrorLog($e->getMessage(), $storeId, 'getExportsNotificationUrl(helper/data)');
        }
    }

    /**
     * @param $key
     * @param $value
     * @param $websiteId
     */
    public function setValue($key, $value, $websiteId)
    {
        try {
            $this->resourceConfig->saveConfig('emarsys_suite2/storage/' . $key, $value, 'websites', $websiteId);
            $this->_cacheTypeList->cleanType('config');
        } catch (\Exception $e) {
            $this->emarsysLogs->addErrorLog($e->getMessage(), $this->storeManager->getStore()->getId(), 'setValue(helper/data)');
        }
    }
}
