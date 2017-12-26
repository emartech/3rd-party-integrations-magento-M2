<?php
/**
 * @category   Emarsys
 * @package    Emarsys_Emarsys
 * @copyright  Copyright (c) 2017 Emarsys. (http://www.emarsys.net/)
 */
namespace Emarsys\Emarsys\Controller\Adminhtml\SubscriberExport;

use Magento\Backend\App\Action;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Sabre\DAV\Client;


/**
 * Class SubscriberExport
 * @package Emarsys\Emarsys\Controller\Adminhtml\SubscriberExport
 */
class SubscriberExport extends Action
{
    /**
     * @var \Magento\Framework\App\Response\Http\FileFactory
     */
    protected $customer;

    /**
     * @var \Emarsys\Emarsys\Model\ResourceModel\Customer
     */
    protected $customerResourceModel;

    /**
     * 
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Customer\Model\CustomerFactory $customer
     * @param DateTime $date
     * @param \Emarsys\Emarsys\Helper\Logs $logsHelper
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Emarsys\Emarsys\Model\ResourceModel\Customer $customerResourceModel
     * @param \Magento\Framework\App\Request\Http $request
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Customer\Model\CustomerFactory $customer,
        DateTime $date,
        \Emarsys\Emarsys\Helper\Logs $logsHelper,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Emarsys\Emarsys\Model\ResourceModel\Customer $customerResourceModel,
        \Magento\Framework\App\Request\Http $request
    ) {
    
        $this->customer = $customer;
        $this->storeManager = $storeManager;
        $this->logsHelper = $logsHelper;
        $this->customerResourceModel = $customerResourceModel;
        $this->date = $date;
        $this->request = $request;
        parent::__construct($context);
    }

    /**
     * Export customer grid to CSV format
     *
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function execute()
    {
        set_time_limit(0);
        $data = $this->request->getParams();
        $scope = 'websites';
        $storeId = $data['storeId'];
        $store = $this->storeManager->getStore($storeId);
        $websiteId = $store->getWebsiteId();
        $storeCode = $store->getCode();
        $scopeId = $websiteId;

        $logsArray['job_code'] = 'subscriber';
        $logsArray['status'] = 'started';
        $logsArray['messages'] = 'bulk subscriber export started';
        $logsArray['created_at'] = $this->date->date('Y-m-d H:i:s', time());
        $logsArray['run_mode'] = 'Manual';
        $logsArray['auto_log'] = 'Complete';
        $logsArray['store_id'] = $storeId;
        $logsArray['website_id'] = $websiteId;
        $logId = $this->logsHelper->manualLogs($logsArray);

        $isEmarsysEnabledForStore = $this->customerResourceModel->getDataFromCoreConfig(
            'emarsys_settings/emarsys_setting/enable',
            $scope,
            $websiteId
        );

        if (!$isEmarsysEnabledForStore) {
            $logsArray['id'] = $logId;
            $logsArray['emarsys_info'] = __('Emarsys is disabled');
            $logsArray['description'] = __('Emarsys is disabled for the store %1', $this->storeManager->getStore($scopeId)->getName());
            $logsArray['action'] = 'synced to emarsys';
            $logsArray['message_type'] = 'Error';
            $logsArray['log_action'] = 'sync';
            $this->logsHelper->logs($logsArray);
            $logsArray['status'] = 'error';
            $logsArray['messages'] = __('Subscriber export Failed');
            $this->messageManager->addErrorMessage(__('Emarsys is disabled for the store %1', $this->storeManager->getStore($scopeId)->getName()));
            $this->logsHelper->manualLogsUpdate($logsArray);

            $resultRedirect = $this->resultRedirectFactory->create();
            $url = $this->getUrl("emarsys_emarsys/subscriberexport/index/store/$storeId");
            return $resultRedirect->setPath($url);
        }

        $websiteStoreIds = [];
        $websiteStoreIds[] = $data['storeId'];
        $webDavUrl = $this->customerResourceModel->getDataFromCoreConfig('emarsys_settings/webdav_setting/webdav_url', $scope, $websiteId);
        if ($webDavUrl == '' && $websiteId == 1) {
            $webDavUrl = $this->customerResourceModel->getDataFromCoreConfig('emarsys_settings/webdav_setting/webdav_url');
        }

        $webDavUser = $this->customerResourceModel->getDataFromCoreConfig('emarsys_settings/webdav_setting/webdav_user', $scope, $websiteId);
        if ($webDavUser == '' && $websiteId == 1) {
            $webDavUser = $this->customerResourceModel->getDataFromCoreConfig('emarsys_settings/webdav_setting/webdav_user');
        }

        $webDavPass = $this->customerResourceModel->getDataFromCoreConfig('emarsys_settings/webdav_setting/webdav_password', $scope, $websiteId);
        if ($webDavPass == '' && $websiteId == 1) {
            $webDavPass = $this->customerResourceModel->getDataFromCoreConfig('emarsys_settings/webdav_setting/webdav_password');
        }

        if ($webDavUrl != '' && $webDavUser != '' && $webDavPass != '') {
            $errorStatus = 0;
        } else {
            $errorStatus = 1;
        }

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
                $customervalues = [];

                $optInStatus = $this->customerResourceModel->getDataFromCoreConfig('contacts_synchronization/initial_db_load/initial_db_load', $scope, $websiteId);
                if ($optInStatus == '' && $websiteId == 1) {
                    $optInStatus = $this->customerResourceModel->getDataFromCoreConfig('contacts_synchronization/initial_db_load/initial_db_load');
                }

                if (isset($scopeId)) {
                    if ($optInStatus == 'attribute') {
                        $subscribedStatus = $this->customerResourceModel->getDataFromCoreConfig('contacts_synchronization/initial_db_load/attribute_value', $scope, $websiteId);
                        if ($subscribedStatus == '' && $websiteId == 1) {
                            $subscribedStatus = $this->customerResourceModel->getDataFromCoreConfig('contacts_synchronization/initial_db_load/attribute_value');
                        }
                        $data['subscribeStatus'] = $subscribedStatus;
                        $customervalues = $this->customerResourceModel->getSubscribedCustomerCollection($data,implode(',',$websiteStoreIds),0);
                    } else {
                        $customervalues = $this->customerResourceModel->getSubscribedCustomerCollection($data,implode(',',$websiteStoreIds),0);
                    }
                }

                $emarsysFieldNames = ['Email', 'Magento Subscriber ID', 'Magento Customer Unique ID'];

                if ($optInStatus != '') {
                    $emarsysFieldNames[] = 'Opt-In';
                }

                $heading = $emarsysFieldNames;
                $localFilePath = BP . "/var";
                $outputFile = "subscribers_" . $this->date->date('YmdHis', time()) . "_" . $storeCode . ".csv";
                $filePath = $localFilePath . "/" . $outputFile;
                $handle = fopen($filePath, 'w');
                fputcsv($handle, $heading);
                foreach ($customervalues as $value) {
                    $values = [];
                    $values[] = $value['subscriber_email'];
                    $values[] = $value['subscriber_id'];
                    $values[] = $value['subscriber_email'] . "#" . $websiteId . "#" . $storeId;
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
                $fileOpen = fopen($filePath, "r");
                $response = $client->request('PUT', $file, $fileOpen);
                unlink($filePath);
                $errorCount = 0;
                if ($response['statusCode'] == '201') {
                    $logsArray['id'] = $logId;
                    $logsArray['emarsys_info'] = 'File uploaded to server successfully';
                    $logsArray['description'] = $response['headers']['location']['0'];
                    $logsArray['action'] = 'synced to emarsys';
                    $logsArray['message_type'] = 'Success';
                    $logsArray['log_action'] = 'sync';
                    $this->logsHelper->logs($logsArray);
                    $errorCount = 0;

                    $this->messageManager->addSuccess("File uploaded to server successfully !!!");
                } else {
                    $logsArray['id'] = $logId;
                    $logsArray['emarsys_info'] = 'Failed to upload file on server';
                    $logsArray['description'] = 'Failed to upload file on server';
                    $logsArray['action'] = 'synced to emarsys';
                    $logsArray['message_type'] = 'Error';
                    $logsArray['log_action'] = 'sync';
                    $this->logsHelper->logs($logsArray);
                    $errorCount = 1;

                    $this->messageManager->addError("Failed to upload file on server !!!");
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
            $logsArray['emarsys_info'] = 'Invalid credentials';
            $logsArray['description'] = 'Invalid credential. Please check your settings and try again';
            $logsArray['action'] = 'synced to emarsys';
            $logsArray['message_type'] = 'Error';
            $logsArray['log_action'] = 'sync';
            $this->logsHelper->logs($logsArray);
            $errorCount = 1;

            $this->messageManager->addError("Invalid credential. Please check your settings and try again !!!");
        }

        $logsArray['id'] = $logId;
        $logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
        $logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
        if ($errorCount == 1) {
            $logsArray['status'] = 'error';
            $logsArray['messages'] = 'Subscriber export have an error. Please check';
        } else {
            $logsArray['status'] = 'success';
            $logsArray['messages'] = 'Subscriber export completed';
        }
        $this->logsHelper->manualLogsUpdate($logsArray);

        $resultRedirect = $this->resultRedirectFactory->create();
        $url = $this->getUrl("emarsys_emarsys/subscriberexport/index/store/$storeId");
        return $resultRedirect->setPath($url);
    }
}
