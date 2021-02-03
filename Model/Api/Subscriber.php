<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */
namespace Emarsys\Emarsys\Model\Api;

use Emarsys\Emarsys\Helper\Cron as EmarsysCronHelper;
use Emarsys\Emarsys\Helper\Data as EmarsysHelper;
use Emarsys\Emarsys\Helper\Logs;
use Emarsys\Emarsys\Logger\Logger as EmarsysLogger;
use Emarsys\Emarsys\Model\AsyncFactory;
use Emarsys\Emarsys\Model\QueueFactory;
use Emarsys\Emarsys\Model\ResourceModel\Customer as customerResourceModel;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Newsletter\Helper\Data as NewsletterHelperData;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Store\Model\StoreManagerInterface;

class Subscriber
{
    const BATCH_SIZE = 1000;

    /**
     * @var Api
     */
    protected $api;

    /**
     * @var customerResourceModel
     */
    protected $customerResourceModel;

    /**
     * @var EmarsysHelper
     */
    protected $emarsysHelper;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var MessageManagerInterface
     */
    protected $messageManager;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var QueueFactory
     */
    protected $queueModel;

    /**
     * @var AsyncFactory
     */
    protected $asyncModel;

    /**
     * @var EmarsysLogger
     */
    protected $emarsysLogger;

    /**
     * @var SubscriberFactory
     */
    protected $subscriberFactory;

    /**
     * @var Logs
     */
    protected $logsHelper;

    /**
     * @var DateTime
     */
    protected $date;

    /**
     * @var NewsletterHelperData
     */
    protected $newsletterHelperData;

    /**
     * @var int
     */
    protected $storeId;

    /**
     * @var int
     */
    protected $websiteId;

    /**
     * @var string
     */
    protected $exportMode;

    /**
     * Subscriber constructor.
     * @param Api $api
     * @param customerResourceModel $customerResourceModel
     * @param DateTime $date
     * @param Logs $logsHelper
     * @param EmarsysHelper $emarsysHelper
     * @param StoreManagerInterface $storeManager
     * @param MessageManagerInterface $messageManager
     * @param ResourceConnection $resourceConnection
     * @param QueueFactory $queueModel
     * @param AsyncFactory $asyncModel
     * @param EmarsysLogger $emarsysLogger
     * @param SubscriberFactory $subscriberFactory
     * @param NewsletterHelperData $newsletterHelperData
     */
    public function __construct(
        Api $api,
        customerResourceModel $customerResourceModel,
        DateTime $date,
        Logs $logsHelper,
        EmarsysHelper $emarsysHelper,
        StoreManagerInterface $storeManager,
        MessageManagerInterface $messageManager,
        ResourceConnection $resourceConnection,
        QueueFactory $queueModel,
        AsyncFactory $asyncModel,
        EmarsysLogger $emarsysLogger,
        SubscriberFactory $subscriberFactory,
        NewsletterHelperData $newsletterHelperData
    ) {
        $this->api = $api;
        $this->emarsysHelper = $emarsysHelper;
        $this->customerResourceModel = $customerResourceModel;
        $this->logsHelper = $logsHelper;
        $this->date = $date;
        $this->storeManager = $storeManager;
        $this->messageManager = $messageManager;
        $this->resourceConnection = $resourceConnection;
        $this->queueModel = $queueModel;
        $this->asyncModel = $asyncModel;
        $this->emarsysLogger = $emarsysLogger;
        $this->subscriberFactory = $subscriberFactory;
        $this->newsletterHelperData = $newsletterHelperData;
    }

    /**
     * @param $subscribeId
     * @param $storeId
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function syncSubscriber(
        $subscribeId,
        $storeId
    ) {
        $store = $this->storeManager->getStore($storeId);
        $websiteId = $store->getWebsiteId();
        $sId = $this->emarsysHelper->getFirstStoreIdOfWebsite($websiteId);

        $logsArray['job_code'] = 'subscriber';
        $logsArray['status'] = 'started';
        $logsArray['messages'] = 'Created Subscriber in Emarsys';
        $logsArray['created_at'] = $this->date->date('Y-m-d H:i:s', time());
        $logsArray['run_mode'] = 'Manual';
        $logsArray['auto_log'] = 'Complete';
        $logsArray['store_id'] = $storeId;
        $logsArray['website_id'] = $websiteId;
        $logId = $this->logsHelper->manualLogs($logsArray);

        $objSubscriber = $this->subscriberFactory->create()->load($subscribeId);

        $buildRequest = [];
        $emailKey = $this->customerResourceModel->getKeyId(EmarsysHelper::CUSTOMER_EMAIL, $sId);
        $buildRequest['key_id'] = $emailKey;
        if ($emailKey && $objSubscriber->getSubscriberEmail()) {
            $buildRequest[$emailKey] = $objSubscriber->getSubscriberEmail();
        }

        $subscriberIdKey = $this->customerResourceModel->getKeyId(EmarsysHelper::SUBSCRIBER_ID, $sId);
        if ($subscriberIdKey && $objSubscriber->getId()) {
            $buildRequest[$subscriberIdKey] = $objSubscriber->getId();
        }

        $customerIdKey = $this->customerResourceModel->getKeyId(EmarsysHelper::CUSTOMER_ID, $sId);
        if ($customerIdKey && $objSubscriber->getCustomerId()) {
            $buildRequest[$customerIdKey] = $objSubscriber->getCustomerId();
        }

        // Query to get opt-in Id in emarsys from magento table
        $optInEmarsysId = $this->customerResourceModel->getKeyId(EmarsysHelper::OPT_IN, $sId);

        //return single / double opt-in
        $optInType = $store->getConfig(EmarsysHelper::XPATH_OPTIN_EVERYPAGE_STRATEGY);

        if ($optInType == 'singleOptIn') {
            $buildRequest[$optInEmarsysId] = 1;
        } elseif ($optInType == 'doubleOptIn') {
            $buildRequest[$optInEmarsysId] = '';
        }

        $errorMsg = 0;
        if ((count($buildRequest) > 0) && (isset($buildRequest['key_id']))) {
            $logsArray['emarsys_info'] = 'Send subscriber to Emarsys';
            $logsArray['action'] = 'Magento to Emarsys';
            $logsArray['message_type'] = 'Success';
            $logsArray['description'] = 'PUT ' . Api::CONTACT_CREATE_IF_NOT_EXISTS . ' ' . \Zend_Json::encode($buildRequest);
            $logsArray['log_action'] = 'sync';
            if ($this->emarsysHelper->isAsyncEnabled()) {
                $this->asyncModel->create()
                    ->setWebsiteId($websiteId)
                    ->setEndpoint(Api::CONTACT_CREATE_IF_NOT_EXISTS)
                    ->setEmail($objSubscriber->getSubscriberEmail())
                    ->setCustomerId($objSubscriber->getCustomerId())
                    ->setSubscriberId($objSubscriber->getId())
                    ->setRequestBody(\Zend_Json::encode($buildRequest))
                    ->save();

                $logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
                $logsArray['emarsys_info'] = 'Added to Async queue';
                $logsArray['description'] = 'Added to Async queue';
                $this->logsHelper->manualLogs($logsArray);
                return true;
            }

            $this->logsHelper->manualLogs($logsArray);

            $this->api->setWebsiteId($websiteId);
            $optInResult = $this->api->createContactInEmarsys($buildRequest);

            $logsArray['id'] = $logId;
            $logsArray['emarsys_info'] = 'Created Subscriber in Emarsys';
            $logsArray['action'] = 'Synced to Emarsys';
            $res = ' [PUT] ' . Api::CONTACT_CREATE_IF_NOT_EXISTS . ' ' . \Zend_Json::encode($optInResult)
                . ' [confirmation url] ' . $this->newsletterHelperData->getConfirmationUrl($objSubscriber)
                . ' [unsubscribe url] ' . $this->newsletterHelperData->getUnsubscribeUrl($objSubscriber);
            if ($optInResult['status'] == '200') {
                $logsArray['message_type'] = 'Success';
                $logsArray['description'] = "Created subscriber '" . $objSubscriber->getSubscriberEmail() . "' in Emarsys successfully " . $res;
            } else {
                $this->emarsysHelper->syncFail($subscribeId, $websiteId, $storeId, 0, 2);
                $logsArray['message_type'] = 'Error';
                $logsArray['description'] = $objSubscriber->getSubscriberEmail() . " - " . $res;
                $errorMsg = 1;
            }
            $logsArray['log_action'] = 'sync';
            $this->logsHelper->manualLogs($logsArray);
        }

        /**
         * Logs for Sync completed with / without Error
         */
        $logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
        $logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
        if ($errorMsg == 1) {
            $logsArray['status'] = 'error';
            $logsArray['message_type'] = 'Error';
            $logsArray['emarsys_info'] = 'Error';
            $logsArray['description'] = 'Error on creating Subscriber';
        } else {
            $logsArray['status'] = 'success';
            $logsArray['message_type'] = 'Success';
            $logsArray['emarsys_info'] = 'Success';
            $logsArray['description'] = 'Created Subscriber in Emarsys';
        }
        $this->logsHelper->manualLogs($logsArray);

        return ($optInResult['status'] == 200) ? true : false;
    }

    /**
     * @param $exportMode
     * @param $params
     * @param null $logId
     * @return bool|void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function syncMultipleSubscriber($exportMode, $params, $logId = null)
    {
        $storeId = $params['storeId'];
        $store = $this->storeManager->getStore($storeId);
        $websiteId = $store->getWebsiteId();
        $sId = $this->emarsysHelper->getFirstStoreIdOfWebsite($websiteId);

        if (!$this->emarsysHelper->isContactsSynchronizationEnable($websiteId)) {
            return;
        }

        $this->exportMode = $exportMode;
        $this->storeId = $storeId;
        $this->websiteId = $websiteId;

        //initial logging of the process
        $logsArray['job_code'] = 'subscriber';
        $logsArray['status'] = 'started';
        $logsArray['messages'] = 'Subscribers are syncing to Emarsys';
        $logsArray['created_at'] = $this->date->date('Y-m-d H:i:s', time());
        $logsArray['run_mode'] = 'Manual';
        $logsArray['auto_log'] = 'Complete';
        $logsArray['store_id'] = $storeId;
        $logsArray['website_id'] = $websiteId;
        if (is_null($logId)) {
            $logId = $this->logsHelper->manualLogs($logsArray, 1);
        }
        $logsArray['id'] = $logId;
        $logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
        $logsArray['log_action'] = 'sync';
        $logsArray['action'] = 'contact sync';
        $success = true;

        //subscriber export starts
        $logsArray['emarsys_info'] = __('Subscriber Export Started');
        $logsArray['description'] = __('Subscriber Export Started for Store ID : %1', $storeId);
        $logsArray['message_type'] = 'Success';
        $this->logsHelper->manualLogs($logsArray);

        //prepare subscribers data
        $emailKey = $this->customerResourceModel->getKeyId(EmarsysHelper::CUSTOMER_EMAIL, $sId);
        $subscriberIdKey = $this->customerResourceModel->getKeyId(EmarsysHelper::SUBSCRIBER_ID, $sId);
        $customerIdKey = $this->customerResourceModel->getKeyId(EmarsysHelper::CUSTOMER_ID, $sId);
        $optInEmarsysId = $this->customerResourceModel->getKeyId(EmarsysHelper::OPT_IN, $sId);

        $currentPageNumber = 1;

        list($subscriberData, $lastPageNumber) = $this->prepareSubscribersInfo(
            $storeId,
            $websiteId,
            $exportMode,
            $emailKey,
            $subscriberIdKey,
            $customerIdKey,
            $optInEmarsysId,
            $currentPageNumber
        );
        while ($currentPageNumber <= $lastPageNumber) {
            if ($currentPageNumber != 1) {
                list($subscriberData, $lastPageNumber) = $this->prepareSubscribersInfo(
                    $storeId,
                    $websiteId,
                    $exportMode,
                    $emailKey,
                    $subscriberIdKey,
                    $customerIdKey,
                    $optInEmarsysId,
                    $currentPageNumber
                );
            }
            $success = $this->processBatch($subscriberData, $subscriberIdKey, $logsArray);

            $logsArray['emarsys_info'] = __('Processing data for store %1', $storeId);
            $logsArray['description'] = __('%1 of %2', $currentPageNumber, $lastPageNumber);
            $logsArray['message_type'] = $success ? 'Success' : 'False';
            $this->logsHelper->manualLogs($logsArray);

            $currentPageNumber++;
        }

        if (!$success) {
            $logsArray['status'] = 'error';
            $logsArray['messages'] = 'Error in creating subscriber !!!';
        } else {
            $logsArray['status'] = 'success';
            $logsArray['messages'] = 'Created subscriber in Emarsys';
        }
        $logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
        $this->logsHelper->manualLogs($logsArray);

        return !$success;
    }

    public function processBatch($subscriberData, $subscriberIdKey, $logsArray)
    {
        if (empty($subscriberData)) {
            //no Subscribers data found
            $logsArray['emarsys_info'] = 'No Subscribers Data Found.';
            $logsArray['action'] = 'Magento to Emarsys';
            $logsArray['message_type'] = 'Success';
            $logsArray['description'] = __('No Subscribers found for the store with store id %1.', $this->storeId);
            $this->logsHelper->manualLogs($logsArray);
            $this->messageManager->addErrorMessage(
                __('No Subscribers found for the store with store id %1.', $this->storeId)
            );
            return false;
        }

        $buildRequest = $this->prepareSubscribersPayload($subscriberData, $subscriberIdKey);

        if (count($buildRequest) > 0) {
            $logsArray['emarsys_info'] = 'Send subscriber to Emarsys';
            $logsArray['action'] = 'Magento to Emarsys';
            $logsArray['message_type'] = 'Success';
            $logsArray['description'] = 'PUT ' . Api::CONTACT_CREATE_IF_NOT_EXISTS
                . ' ' . \Zend_Json::encode($buildRequest);
            $this->logsHelper->manualLogs($logsArray);
            $this->emarsysLogger->info($logsArray['description']);

            //Send request to Emarsys with Customer's Data
            $this->api->setWebsiteId($this->websiteId);
            $result = $this->api->createContactInEmarsys($buildRequest);

            $logsArray['emarsys_info'] = 'Create subscriber in Emarsys';
            $logsArray['action'] = 'Synced to Emarsys';
            $res = 'PUT ' . Api::CONTACT_CREATE_IF_NOT_EXISTS . ' ' . \Zend_Json::encode($result);

            if ($result['status'] == '200') {
                //successful response from emarsys
                $logsArray['message_type'] = 'Success';
                $logsArray['description'] = "Created subscribers in Emarsys succcessfully " . $res;

                if ($this->exportMode == EmarsysCronHelper::CRON_JOB_CUSTOMER_SYNC_QUEUE) {
                    //clean subscribers from the queue
                    foreach ($subscriberData as $value) {
                        $this->queueModel->create()->load($value[$subscriberIdKey], 'entity_id')->delete();
                    }
                }
                $this->messageManager->addSuccessMessage(__('Created subscribers in Emarsys succcessfully!!'));
                $this->logsHelper->manualLogs($logsArray);
                $this->emarsysLogger->info($logsArray['description']);
            } else {
                //error response from emarsys
                $logsArray['message_type'] = 'Error';
                $logsArray['description'] = $res;
                $this->messageManager->addErrorMessage(
                    __('Subscriber export have an error. Please check emarsys logs for more details!!')
                );
                $this->logsHelper->manualLogs($logsArray);
                $this->emarsysLogger->info($logsArray['description']);
                return false;
            }
        }
        return true;
    }

    /**
     * @param $storeId
     * @param $websiteId
     * @param $exportMode
     * @param $emailKey
     * @param $subscriberIdKey
     * @param $customerIdKey
     * @param $optInEmarsysId
     * @param $currentPageNumber
     * @return array
     */
    public function prepareSubscribersInfo(
        $storeId,
        $websiteId,
        $exportMode,
        $emailKey,
        $subscriberIdKey,
        $customerIdKey,
        $optInEmarsysId,
        $currentPageNumber = 0
    ) {
        $subscriberData = [];

        if ($exportMode == EmarsysCronHelper::CRON_JOB_CUSTOMER_SYNC_QUEUE) {
            $newsLetSubTableName = $this->resourceConnection->getTableName('newsletter_subscriber');
            $subscriberCollection = $this->queueModel->create()->getCollection();
            $subscriberCollection->addFieldToSelect('entity_id');
            $subscriberCollection->addFieldToFilter('main_table.entity_type_id', 2);
            $subscriberCollection->addFieldToFilter('main_table.store_id', $storeId);
            $subscriberCollection->getSelect()->joinLeft(
                ['newsletter_subscriber' => $newsLetSubTableName],
                'main_table.entity_id = newsletter_subscriber.subscriber_id',
                ['subscriber_email', 'subscriber_confirm_code', 'subscriber_status', 'customer_id']
            );

            $this->updateLastModifiedContacts($subscriberCollection, $websiteId);
        } else {
            $subscriberCollection = $this->subscriberFactory->create()->getCollection()
                ->addFieldToFilter('store_id', $storeId);
        }

        if ($currentPageNumber) {
            $subscriberCollection->setPageSize(self::BATCH_SIZE)
                ->setCurPage($currentPageNumber);
        }

        $lastPageNumber = $subscriberCollection->getLastPageNumber();

        foreach ($subscriberCollection as $subscriber) {
            $values = [];
            $values[$emailKey] = $subscriber->getSubscriberEmail();
            $values[$subscriberIdKey] = $subscriber->getId();
            if ($subscriber->getCustomerId()) {
                $values[$customerIdKey] = $subscriber->getCustomerId();
            }

            $subscriberStatus = $subscriber->getSubscriberStatus();

            if (in_array($subscriberStatus, [\Magento\Newsletter\Model\Subscriber::STATUS_NOT_ACTIVE, \Magento\Newsletter\Model\Subscriber::STATUS_UNCONFIRMED])) {
                $values[$optInEmarsysId] = '';
            } elseif ($subscriberStatus ==  \Magento\Newsletter\Model\Subscriber::STATUS_SUBSCRIBED) {
                $values[$optInEmarsysId] = 1;
            } else {
                $values[$optInEmarsysId] = 2;
            }

            $subscriberData[] = $values;
        }

        unset($subscriberCollection);

        return [$subscriberData, $lastPageNumber];
    }

    /**
     * @param $subscriberData
     * @param $keyId
     * @return array
     */
    public function prepareSubscribersPayload($subscriberData, $keyId)
    {
        $buildRequest = [];
        if ($keyId) {
            $buildRequest['key_id'] = $keyId;
            $buildRequest['contacts'] = $subscriberData;
        }

        return $buildRequest;
    }

    /**
     * @param $collection
     * @param $websiteId
     */
    public function updateLastModifiedContacts($collection, $websiteId = null)
    {
        try {
            $currentPageNumber = 1;
            $collection->setPageSize(EmarsysHelper::BATCH_SIZE);
            $lastPageNumber = $collection->getLastPageNumber();

            while ($currentPageNumber <= $lastPageNumber) {
                if ($currentPageNumber != 1) {
                    $collection->setPageSize(EmarsysHelper::BATCH_SIZE)
                        ->setCurPage($currentPageNumber);
                }
                if (count($collection)) {
                    $subscriberIds = $collection->getColumnValues('entity_id');
                    if (count($subscriberIds)) {
                        $this->emarsysHelper
                            ->setWebsiteId($websiteId)
                            ->backgroudTimeBasedOptinSync($subscriberIds);
                    }
                }
                $currentPageNumber = $currentPageNumber + 1;
            }
        } catch (\Exception $e) {
            $this->emarsysHelper->addErrorLog(
                EmarsysHelper::LOG_MESSAGE_SUBSCRIBER,
                $e->getMessage(),
                0,
                'updateLastModifiedContacts($collection, $websiteId)'
            );
        }
    }
}
