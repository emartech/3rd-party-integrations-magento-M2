<?php
/**
 * @category   Emarsys
 * @package    Emarsys_Emarsys
 * @copyright  Copyright (c) 2017 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Controller\Adminhtml\Uploadinitialdb;

use Emarsys\Emarsys\Helper\Data;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Sabre\DAV\Client;
use Magento\Customer\Model\CustomerFactory;
use Magento\Backend\App\Action\Context;
use Magento\Store\Model\StoreManagerInterface;
use Emarsys\Emarsys\Model\ResourceModel\Customer;
use Emarsys\Emarsys\Helper\Logs;
use Magento\Config\Model\ResourceModel\Config;
use Emarsys\Emarsys\Helper\Country as EmarsysCountryHelper;

/**
 * Class Index for API credentials
 * @package Emarsys\Emarsys\Controller\Adminhtml\Uploadinitialdb
 */
class Index extends \Magento\Backend\App\Action
{
    /**
     * @var Data
     */
    protected $emarsysHelper;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Logs
     */
    protected $logsHelper;

    /**
     * Index constructor.
     * @param Data $emarsysHelper
     * @param CustomerFactory $customer
     * @param Context $context
     * @param DateTime $date
     * @param StoreManagerInterface $storeManager
     * @param Customer $customerResourceModel
     * @param Logs $logsHelper
     * @param Config $config
     */
    public function __construct(
        Data $emarsysHelper,
        CustomerFactory $customer,
        Context $context,
        DateTime $date,
        StoreManagerInterface $storeManager,
        Customer $customerResourceModel,
        Logs $logsHelper,
        Config $config,
        EmarsysCountryHelper $emarsysCountryHelper
    ) {
        $this->emarsysHelper = $emarsysHelper;
        $this->customer = $customer;
        $this->logsHelper = $logsHelper;
        $this->customerResourceModel = $customerResourceModel;
        $this->storeManager = $storeManager;
        $this->date = $date;
        $this->config = $config;
        $this->emarsysCountryHelper = $emarsysCountryHelper;
        parent::__construct($context);
    }

    public function execute()
    {
        $data = $this->getRequest()->getParams();
        $data['fromDate'] = '';
        $data['toDate'] = '';
        //if the default configuration is set then the website id won't come
        if (!isset($data['website'])) {
            foreach ($this->storeManager->getStores(false) as $storeData) {
                $data['store'] = $storeData->getId();
                $data['website'] = $storeData->getWebsiteId();
                $this->uploadInitialData($data);
            }
            $scopeType = 'default';
            $websiteId = 0;
        } else {
            foreach ($this->storeManager->getStores(false) as $storeData) {
                if ($data['website'] == $storeData->getWebsiteId()) {
                    $data['store'] = $storeData->getId();
                    $this->uploadInitialData($data);
                }
            }
            $scopeType = 'websites';
            $websiteId = $data['website'];
        }

        $initialLoad = $data['initial_load'];
        $attribute = $data['attribute'];
        $attributeValue = $data['attributevalue'];
        $this->config->saveConfig('contacts_synchronization/initial_db_load/initial_db_load', $initialLoad, $scopeType, $websiteId);
        $this->config->saveConfig('contacts_synchronization/initial_db_load/attribute', $attribute, $scopeType, $websiteId);
        $this->config->saveConfig('contacts_synchronization/initial_db_load/attribute_value', $attributeValue, $scopeType, $websiteId);
    }

    /**
     * @param $data
     */
    public function uploadInitialData($data)
    {
        $scope = 'websites';
        $websiteId = $data['website'];
        $scopeId = $this->emarsysHelper->getFirstStoreIdOfWebsite($websiteId);
        if (isset($data['store'])) {
            $scopeId = $data['store'];
        }
        $store = $this->storeManager->getStore($scopeId);
        $storeCode = $store->getCode();
        $websiteCode = $store->getWebsite()->getCode();
        $data['storeId'] = $scopeId;
        $data['fromDate'] = '';
        $data['toDate'] = '';

        $logsArray['job_code'] = 'initialdbload';
        $logsArray['status'] = 'started';
        $logsArray['messages'] = 'Initial DB data initiated';
        $logsArray['created_at'] = $this->date->date('Y-m-d H:i:s', time());
        $logsArray['run_mode'] = 'Manual';
        $logsArray['auto_log'] = 'Complete';
        $logsArray['store_id'] = $scopeId;
        $logsArray['website_id'] = $websiteId;
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
            $logsArray['messages'] = __('Initial DB data Failed');
            $this->messageManager->addErrorMessage(__('Emarsys is disabled for the store %1', $store->getName()));
            $this->logsHelper->manualLogsUpdate($logsArray);

            return;
        }

        //get these values from db
        $webDavUrl = $this->customerResourceModel->getDataFromCoreConfig('emarsys_settings/webdav_setting/webdav_url', $scope, $websiteId);
        $webDavUser = $this->customerResourceModel->getDataFromCoreConfig('emarsys_settings/webdav_setting/webdav_user', $scope, $websiteId);
        $webDavPass = $this->customerResourceModel->getDataFromCoreConfig('emarsys_settings/webdav_setting/webdav_password', $scope, $websiteId);

        $errorCount = 0;
        $notificationErrorCount = 0;
        $errorStatus = 1;
        if ($webDavUrl != '' && $webDavUser != '' && $webDavPass != '') {
            $errorStatus = 0;
        }
        $notificationMessage = '';
        $subscriberMessage = '';

        if ($errorStatus != 1) {
            $settings = [
                'baseUri' => $webDavUrl,
                'userName' => $webDavUser,
                'password' => $webDavPass,
                'proxy' => '',
            ];
            $client = new Client($settings);
            $response = $client->request('GET');
            if ($response['statusCode'] == '200' || $response['statusCode'] == '403') {
                if ($websiteId == '') {
                    $websiteId = 0;
                }

                //Upload Customer CSV first
                $customervalues = [];
                $optInStatus = $data['initial_load'];
                if (isset($scopeId)) {
                    $websiteId = $this->storeManager->getStore($scopeId)->getWebsiteId();
                    $data['website'] = $websiteId;
                    if (isset($data['fromDate']) && $data['fromDate'] != '' && isset($data['toDate']) && $data['toDate'] != '') {
                        $data['fromDate'] = $this->date->date('Y-m-d H:i:s', strtotime($data['fromDate']));
                        $data['toDate'] = $this->date->date('Y-m-d H:i:s', strtotime($data['toDate']));
                    }
                    if ($optInStatus == 'attribute') {
                        $subscribedStatus = $data['attributevalue'];

                        $data['subscribeStatus'] = $subscribedStatus;
                        $customervalues = $this->customerResourceModel->getCustomerCollection($data);
                    } else {
                        $customervalues = $this->customerResourceModel->getCustomerCollection($data);
                    }
                }
                $mappedAttributes = $this->customerResourceModel->getMappedCustomerAttribute($scopeId);
                $headers = array();
                if (isset($mappedAttributes) && count($mappedAttributes) != '') {
                    $headerIndex = array();
                    $indexCount = 0;
                    foreach ($mappedAttributes as $att) {
                        if ($att['emarsys_contact_field'] == NULL)
                            continue;
                        $emarsysField = $this->customerResourceModel->getEmarsysFieldNameContact($att, $scopeId);
                        $headers["$att[magento_custom_attribute_id]"] = $emarsysField['name'];
                        $headerIndex[$indexCount] = $att['magento_custom_attribute_id'];
                        $indexCount++;
                    }
                }
                if (isset($mappedAttributes) && count($mappedAttributes) != '') {
                    if (count($headers) > 0) {
                        $headers['magento_customer_id'] = 'Magento Customer ID';
                        $headerIndex[$indexCount] = 'magento_customer_id';
                        $headers['magento_customer_unique_id'] = 'Magento Customer Unique ID';
                        $indexCount = $indexCount + 1;
                        $headerIndex[$indexCount] = 'magento_customer_unique_id';
                        /*$headers['opt_in'] = 'Opt-In';
                        $indexCount = $indexCount + 1;
                        $headerIndex[$indexCount] = 'opt_in';*/
                        if(!in_array('Email',$headers)){
                                    $headers['customer_email'] = 'Customer Email';
                                    $indexCount = $indexCount + 1;
                                    $headerIndex[$indexCount] = 'customer_email';
                        }
                        $localFilePath = BP . "/var";
                        $outputFile = "customers_" . $this->date->date('YmdHis', time()) . "_" . $storeCode . ".csv";
                        $filePath = $outputFile; //$localFilePath . "/" . $outputFile;
                        $handle = fopen($filePath, 'w');
                        fputcsv($handle, $headers);
                        $customerCollection = $this->customerResourceModel->getCustomerCollection($data, $scopeId);
                        $customerValues = array();
                        foreach ($customerCollection as $customerData) {
                            $customerLoad = $this->customer->create()->load($customerData['entity_id']);
                            $primaryBilling = $customerLoad->getPrimaryBillingAddress();
                            $primaryShipping = $customerLoad->getPrimaryshippingAddress();
                            $mappedCountries = $this->emarsysCountryHelper->getMapping($scopeId);
                            foreach ($headers as $key => $value) {
                                $attributeCode = $this->customerResourceModel->getMagentoAttributeCode($key, $scopeId); //using the custom magento id from the emarsys_magento_customer_attributes table
                                //code for the custom defined attributes in the array starts

                                if ($value == "Magento Customer ID") {
                                    $index = array_search($key, $headerIndex);
                                    $customerValues[$index] = $customerLoad->getId();
                                } elseif ($value == "Magento Customer Unique ID") {
                                    $index = array_search($key, $headerIndex);
                                    $customerValues[$index] = $customerLoad->getEmail() . "#" . $customerLoad->getWebsiteId() . "#" . $customerLoad->getData('store_id');
                                } elseif ($value == "Customer Email") {
                                    $index = array_search($key, $headerIndex);
                                    $customerValues[$index] = $customerLoad->getEmail();
                                } elseif ($attributeCode['entity_type_id'] == 1) {
                                    //code for the custom defined attributes ends here
                                    $index = array_search($key, $headerIndex);
                                    $customerValues[$index] = $customerLoad->getData($attributeCode['attribute_code']);
                                } elseif ($attributeCode['entity_type_id'] == 2) {
                                    $isShippingAttr = (strpos($attributeCode['attribute_code_custom'], 'default_shipping_') !== false) ? true : false;
                                    $isBillingAttr = (strpos($attributeCode['attribute_code_custom'], 'default_billing_') !== false) ? true : false;
                                    $index = array_search($key, $headerIndex);
                                    $attrVal = '';
                                    if ($isShippingAttr) {
                                        if (isset($customerData['default_shipping']) && $customerData['default_shipping'] != NULL && $customerData['default_shipping'] != 0) {
                                            if ($primaryShipping) {
                                                $attrVal = $primaryShipping->getData($attributeCode['attribute_code']);
                                                if ($attributeCode['attribute_code'] == 'country_id') {
                                                    $attrVal = (isset($mappedCountries[$attrVal]) ? $mappedCountries[$attrVal] : '');
                                                } elseif ($attributeCode['attribute_code'] == 'street') {
                                                    $attrVal = str_replace("\n", ',', $attrVal);
                                                }
                                            }
                                        }
                                    } elseif ($isBillingAttr) {
                                        if (isset($customerData['default_billing']) && $customerData['default_billing'] != NULL && $customerData['default_billing'] != NULL) {
                                            if ($primaryBilling) {
                                                $attrVal = $primaryBilling->getData($attributeCode['attribute_code']);
                                                if ($attributeCode['attribute_code'] == 'country_id') {
                                                    $attrVal = (isset($mappedCountries[$attrVal]) ? $mappedCountries[$attrVal] : '');
                                                } elseif ($attributeCode['attribute_code'] == 'street') {
                                                    $attrVal = str_replace("\n", ',', $attrVal);
                                                }
                                            }
                                        }
                                    }
                                    $customerValues[$index] = $attrVal;
                                }
                            }
                            fputcsv($handle, $customerValues);
                            $customerValues = array();
                        }
                        $file = $outputFile;
                        $filePath = BP . "/" . $outputFile;
                        $fileOpen = fopen($filePath, "r");
                        $response = $client->request('PUT', $file, $fileOpen);
                        unlink($outputFile);
                        if ($response['statusCode'] == '201') {
                            $logsArray['id'] = $logId;
                            $logsArray['emarsys_info'] = 'Customer file uploaded to server successfully';
                            $logsArray['description'] = $response['headers']['location']['0'];
                            $logsArray['action'] = 'synced to emarsys';
                            $logsArray['message_type'] = 'Success';
                            $logsArray['log_action'] = 'sync';
                            $this->logsHelper->logs($logsArray);
                            $errorCount = 0;
                            $notificationMessage = 'Customer File uploaded to server successfully';
                        } else {
                            $logsArray['id'] = $logId;
                            $logsArray['emarsys_info'] = 'Failed to upload file on server';
                            $logsArray['description'] = 'Failed to upload file on server';
                            $logsArray['action'] = 'synced to emarsys';
                            $logsArray['message_type'] = 'Error';
                            $logsArray['log_action'] = 'sync';
                            $this->logsHelper->logs($logsArray);
                            $errorCount = 1;
                            $notificationMessage = 'Failed to upload Customer file on server  !!!';
                        }
                    } else {
                        $logsArray['id'] = $logId;
                        $logsArray['emarsys_info'] = 'Attributes are not mapped';
                        $logsArray['description'] = 'Failed to upload file on server. Attributes are not mapped';
                        $logsArray['action'] = 'synced to emarsys';
                        $logsArray['message_type'] = 'Error';
                        $logsArray['log_action'] = 'sync';
                        $this->logsHelper->logs($logsArray);
                        $errorCount = 1;
                        $notificationMessage = 'Customer Attributes are not mapped so failed to upload the customer file!!!';
                    }
                } else {
                    $logsArray['id'] = $logId;
                    $logsArray['emarsys_info'] = 'Attributes are not mapped';
                    $logsArray['description'] = 'Failed to upload Customer file on server. Attributes are not mapped';
                    $logsArray['action'] = 'synced to emarsys';
                    $logsArray['message_type'] = 'Error';
                    $logsArray['log_action'] = 'sync';
                    $this->logsHelper->logs($logsArray);
                    $errorCount = 1;
                    $notificationMessage = 'Customer Attributes are not mapped so failed to upload the customer file!!!';
                }

                // Upload subscriber CSV file to WebDAV server
                $websiteStoreIds = [];
                /*foreach ($this->storeManager->getStores() as $storeData) {
                    if ($data['website'] == $storeData->getWebsiteId()) {
                        $websiteStoreIds[] = $storeData->getStoreId();
                    }
                }*/
                $websiteStoreIds[] = $scopeId;
                $customervalues = [];
                $optInStatus = $data['initial_load'];

                if (isset($scopeId)) {
                    if ($optInStatus == 'attribute') {
                        $subscribedStatus = $data['attributevalue'];

                        $data['subscribeStatus'] = $subscribedStatus;
                        $customervalues = $this->customerResourceModel->getSubscribedCustomerCollection(
                            $data,
                            implode(',', $websiteStoreIds),
                            1
                        );
                    } else {
                        $customervalues = $this->customerResourceModel->getSubscribedCustomerCollection(
                            $data,
                            implode(',', $websiteStoreIds),
                            1
                        );
                    }
                }
                $emarsysFieldNames = ['Email', 'Magento Subscriber ID', 'Magento Customer Unique ID'];
                if ($optInStatus != '') {
                    $emarsysFieldNames[] = 'Opt-In';
                }

                $heading = $emarsysFieldNames;
                $outputFile = "subscribers_" . $this->date->date('YmdHis', time()) . "_" . $storeCode . ".csv";
                $handle = fopen($outputFile, 'w');
                fputcsv($handle, $heading);
                foreach ($customervalues as $value) {
                    $values = [];
                    $values[] = $value['subscriber_email'];
                    $values[] = $value['subscriber_id'];
                    $values[] = $value['subscriber_email'] . "#" . $websiteId . "#" . $value['store_id'];
                    if ($optInStatus == 'true') {
                        $values[] = '1';
                    } elseif ($optInStatus == 'empty') {
                        $values[] = 0;
                    } elseif ($optInStatus == 'attribute') {
                        $values[] = '1';
                    }
                    fputcsv($handle, $values);
                }

                $file = $outputFile;
                $filePath = BP . "/" . $outputFile;
                $fileOpen = fopen($filePath, "r");
                $response = $client->request('PUT', $file, $fileOpen);
                unlink($outputFile);

                if ($response['statusCode'] == '201') {
                    $logsArray['id'] = $logId;
                    $logsArray['emarsys_info'] = 'Subscriber file uploaded to server successfully';
                    $logsArray['description'] = $response['headers']['location']['0'];
                    $logsArray['action'] = 'synced to emarsys';
                    $logsArray['message_type'] = 'Success';
                    $logsArray['log_action'] = 'sync';
                    $this->logsHelper->logs($logsArray);
                    $notificationErrorCount = 0;
                    $subscriberMessage = 'Subscriber File uploaded to server successfully';
                } else {
                    $logsArray['id'] = $logId;
                    $logsArray['emarsys_info'] = 'Failed to upload file on server';
                    $logsArray['description'] = 'Failed to upload file on server';
                    $logsArray['action'] = 'synced to emarsys';
                    $logsArray['message_type'] = 'Error';
                    $logsArray['log_action'] = 'sync';
                    $this->logsHelper->logs($logsArray);
                    $notificationErrorCount = 1;
                    $subscriberMessage = 'Subscriber Failed to upload file on server !!!';
                }
            } else {
                $logsArray['id'] = $logId;
                $logsArray['emarsys_info'] = 'Failed to Login with WebDav Server.';
                $logsArray['description'] = 'Failed to Login with WebDav Server. Please check your settings and try again';
                $logsArray['action'] = 'synced to emarsys';
                $logsArray['message_type'] = 'Error';
                $logsArray['log_action'] = 'sync';
                $this->logsHelper->logs($logsArray);
                $errorCount = 1;
                $this->messageManager->addError("Failed to Login with WebDav Server. Please check your settings and try again !!!");
            }
        } else {
            $logsArray['id'] = $logId;
            $logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
            $logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
            $logsArray['status'] = 'error';
            $logsArray['messages'] = 'Invalid WebDAV credentials.';
            $this->logsHelper->manualLogsUpdate($logsArray);
            $errorCount = 1;
            $notificationMessage = 'Invalid WebDav credentials. Please check your setting.';
        }

        $logsArray['id'] = $logId;
        $logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
        $logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());

        //Display error/success messages
        if ($errorCount == 1 || $notificationErrorCount == 1) {
            $logsArray['status'] = 'error';
            $logsArray['messages'] = 'Error in Uploading files. Please check.';
            $errorMessage = $notificationMessage . " and " . $subscriberMessage . " for store " . $store->getName();
            $this->messageManager->addError($errorMessage);
        } else {
            $logsArray['status'] = 'success';
            $logsArray['messages'] = 'Initial DB data completed';
            $this->messageManager->addSuccess(__('File uploaded to server successfully for store %1.', $store->getName()));
        }

        $this->logsHelper->manualLogsUpdate($logsArray);
    }
}
