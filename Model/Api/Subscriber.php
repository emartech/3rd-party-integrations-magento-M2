<?php
/**
 * @category   Emarsys
 * @package    Emarsys_Emarsys
 * @copyright  Copyright (c) 2017 Emarsys. (http://www.emarsys.net/)
 */
namespace Emarsys\Emarsys\Model\Api;

use Emarsys\Emarsys\Model\Api\Api;
use Magento\Newsletter\Model\Subscriber as subscriberModel;
use Emarsys\Emarsys\Model\ResourceModel\Customer as customerResourceModel;
use Emarsys\Emarsys\Model\ResourceModel\Field;
use Emarsys\Emarsys\Helper\Data;
use Magento\Framework\Stdlib\DateTime\DateTime;
use \Emarsys\Emarsys\Helper\Logs;
use \Psr\Log\LoggerInterface;

/**
 * API class for Emarsys API wrappers
 */
class Subscriber
{
    /**
     * @var Api
     */
    protected $api;
    /**
     * @var
     */
    protected $subscriber;
    /**
     * @var
     */
    protected $customerResourceModel;
    /**
     * @var
     */
    protected $dataHelper;
    /**
     * @var
     */
    protected $fieldResourceModel;
    /**
     * @var
     */
    protected $logger;

    /**
     * @param Api $api
     * @param subscriberModel $subscriber
     * @param customerResourceModel $customerResourceModel
     * @param Field $fieldResourceModel
     * @param DateTime $date
     * @param Logs $logsHelper
     * @param Data $dataHelper
     * @param LoggerInterface $logger
     */
    public function __construct(
        Api $api,
        subscriberModel $subscriber,
        customerResourceModel $customerResourceModel,
        Field $fieldResourceModel,
        DateTime $date,
        Logs $logsHelper,
        Data $dataHelper,
        \Psr\Log\LoggerInterface $logger
    ) {
    
        $this->subscriber = $subscriber;
        $this->api = $api;
        $this->dataHelper = $dataHelper;
        $this->customerResourceModel = $customerResourceModel;
        $this->fieldResourceModel = $fieldResourceModel;
        $this->logsHelper = $logsHelper;
        $this->date = $date;
        $this->logger = $logger;
    }

    /**
     * @param $subscribeId
     * @param $storeId
     * @param null $frontendFlag
     * @param null $pageHandle
     * @return array
     */
    public function syncSubscriber($subscribeId, $storeId, $frontendFlag = null, $pageHandle = null, $websiteId = 1, $cron = 0)
    {
        $externalEventStatusAfterConfirm = '';
        $optInMagentoStatus = '';
        $logsArray['job_code'] = 'subscriber';
        $logsArray['status'] = 'started';
        $logsArray['messages'] = 'Subscriber is sync to Emarsys';
        $logsArray['created_at'] = $this->date->date('Y-m-d H:i:s', time());
        $logsArray['run_mode'] = 'Manual';
        $logsArray['auto_log'] = 'Complete';
        $logsArray['store_id'] = $storeId;
        $logsArray['website_id'] = $websiteId;
        $logId = $this->logsHelper->manualLogs($logsArray);

        $this->api->setWebsiteId($websiteId);

        $objCustomer = $this->subscriber->load($subscribeId);
        $arrCustomer = $objCustomer->getData();
        $scope = 'websites';

        $buildRequest = [];
        $keyField = $this->dataHelper->getContactUniqueField($websiteId);
        if ($keyField == 'email') {
            $buildRequest['key_id'] = $this->customerResourceModel->getKeyId('Email', $storeId);
            $buildRequest[$buildRequest['key_id']] = $arrCustomer['subscriber_email'];
        } elseif ($keyField == 'magento_id') {
            $buildRequest['key_id'] = $this->customerResourceModel->getKeyId('Magento Subscriber ID', $storeId);
            $buildRequest[$buildRequest['key_id']] = $subscribeId;
        } elseif ($keyField == 'unique_id') {
            $buildRequest['key_id'] = $this->customerResourceModel->getKeyId('Magento Customer Unique ID', $storeId);
            $buildRequest[$buildRequest['key_id']] = $arrCustomer['subscriber_email'] . "#" . $websiteId . "#" . $storeId;
        }

        $keyId = $this->customerResourceModel->getKeyId('Email', $storeId);
        $buildRequest[$keyId] = $arrCustomer['subscriber_email'];

        $keyId = $this->customerResourceModel->getKeyId('Magento Subscriber ID', $storeId);
        $buildRequest[$keyId] = $subscribeId;

        $keyId = $this->customerResourceModel->getKeyId('Magento Customer Unique ID', $storeId);
        $buildRequest[$keyId] = $arrCustomer['subscriber_email'] . "#" . $websiteId . "#" . $storeId;

        // Query to get opt-in Id in emarsys from magento table
        $optInEmarsysId = $this->customerResourceModel->getEmarsysFieldId('Opt-In', $storeId);

        $buildRequest[$optInEmarsysId] =  $objCustomer->getSubscriberStatus();
        if ($buildRequest[$optInEmarsysId] != 1) {
            $buildRequest[$optInEmarsysId] = 2;
        }

        $errorMsg = 0;
        if ((count($buildRequest) > 0) && (isset($buildRequest['key_id']))) {
            $logsArray['id'] = $logId;
            $logsArray['emarsys_info'] = 'Send subscriber to Emarsys';
            $logsArray['action'] = 'Magento to Emarsys';
            $logsArray['message_type'] = 'Success';
            $logsArray['description'] = 'PUT ' . " contact/?create_if_not_exists=1 " . json_encode($buildRequest, JSON_PRETTY_PRINT);
            $logsArray['log_action'] = 'sync';
            $this->logsHelper->logs($logsArray);

            $optInResult = $this->api->sendRequest('PUT', 'contact/?create_if_not_exists=1', $buildRequest);

            $logsArray['id'] = $logId;
            $logsArray['emarsys_info'] = 'Create subscriber in Emarsys';
            $logsArray['action'] = 'Synced to Emarsys';
            $res = 'PUT ' . " contact/?create_if_not_exists=1 " . json_encode($optInResult, JSON_PRETTY_PRINT);
            if ($optInResult['status'] == '200') {
                $logsArray['message_type'] = 'Success';
                $logsArray['description'] = "Created subscriber '" . $objCustomer->getEmail() . "' in Emarsys succcessfully " . $res;
            } else {
                $this->dataHelper->syncFail($subscribeId, $websiteId, $storeId, $cron, 2);
                $logsArray['message_type'] = 'Error';
                $logsArray['description'] = $objCustomer->getEmail() . " - " . $optInResult['body']['replyText'] . $res;
                $errorMsg = 1;
            }
            $logsArray['log_action'] = 'sync';
            $this->logsHelper->logs($logsArray);
        }

        if ($optInMagentoStatus == 'doubleOptIn') {
            //get double optin event id and send to emarsys
            $doubleOptInRequest = [];

            if ($pageHandle == 'newsletter_subscriber_new') {
                $doubleOptInEventStatus = $this->customerResourceModel->getDataFromCoreConfig('opt_in/subscription_newsletter_everypage/external_eventid_double_opt_in', $scope, $websiteId);
                if ($doubleOptInEventStatus == '' && $websiteId == 1) {
                    $doubleOptInEventStatus = $this->customerResourceModel->getDataFromCoreConfig('opt_in/subscription_newsletter_everypage/external_eventid_double_opt_in');
                }
            } elseif ($pageHandle == 'customer_account_createpost' || $pageHandle == 'newsletter_manage_save' || $pageHandle == 'customer_index_save') {
                $doubleOptInEventStatus = $this->customerResourceModel->getDataFromCoreConfig('opt_in/subscription_customer_homepage/external_eventid_double_opt_in', $scope, $websiteId);
                if ($doubleOptInEventStatus == '' && $websiteId == 1) {
                    $doubleOptInEventStatus = $this->customerResourceModel->getDataFromCoreConfig('opt_in/subscription_customer_homepage/external_eventid_double_opt_in');
                }
            } elseif ($pageHandle == 'checkout_onepage_success') {
                $doubleOptInEventStatus = $this->customerResourceModel->getDataFromCoreConfig('opt_in/subscription_checkout_process/external_eventid_double_opt_in', $scope, $websiteId);
                if ($doubleOptInEventStatus == '' && $websiteId == 1) {
                    $doubleOptInEventStatus = $this->customerResourceModel->getDataFromCoreConfig('opt_in/subscription_checkout_process/external_eventid_double_opt_in');
                }
            }

            if ($doubleOptInEventStatus != '') {
                // $externalEventStatus is the value
                $doubleOptInRequest['key_id'] = 3;
                $doubleOptInRequest['external_id'] = $objCustomer->getEmail();
                $doubleOptInRequest['data'] = [
                    'global' => [
                        'First Name' => 'testDoubleOptInValue',
                        'Last Name' => 'Test last name'
                    ]
                ];
            }
        }

        if ($pageHandle == 'newsletter_subscriber_new') {
            $externalEventStatusAfterConfirm = $this->customerResourceModel->getDataFromCoreConfig('opt_in/subscription_newsletter_everypage/external_eventid_after_opt_in_confirmation', $scope, $websiteId);
            if ($externalEventStatusAfterConfirm == '' && $websiteId == 1) {
                $externalEventStatusAfterConfirm = $this->customerResourceModel->getDataFromCoreConfig('opt_in/subscription_newsletter_everypage/external_eventid_after_opt_in_confirmation');
            }
        } elseif ($pageHandle == 'customer_account_createpost' || $pageHandle == 'newsletter_manage_save' || $pageHandle == 'customer_index_save') {
            $externalEventStatusAfterConfirm = $this->customerResourceModel->getDataFromCoreConfig('opt_in/subscription_customer_homepage/external_eventid_after_opt_in_confirmation', $scope, $websiteId);
            if ($externalEventStatusAfterConfirm == '' && $websiteId == 1) {
                $externalEventStatusAfterConfirm = $this->customerResourceModel->getDataFromCoreConfig('opt_in/subscription_customer_homepage/external_eventid_after_opt_in_confirmation');
            }
        } elseif ($pageHandle == 'checkout_onepage_success') {
            $externalEventStatusAfterConfirm = $this->customerResourceModel->getDataFromCoreConfig('opt_in/subscription_checkout_process/external_eventid_after_opt_in_confirmation', $scope, $websiteId);
            if ($externalEventStatusAfterConfirm == '' && $websiteId == 1) {
                $externalEventStatusAfterConfirm = $this->customerResourceModel->getDataFromCoreConfig('opt_in/subscription_checkout_process/external_eventid_after_opt_in_confirmation');
            }
        }

        // If it is None , no need API call to Emarsys
        // If it is not None , then API call to emarsys with event id:  "TRIGGER"
        if ($externalEventStatusAfterConfirm != '') {
            // $externalEventStatus is the value
            $externalEventAfterConfirmRequest = [];
            $externalEventAfterConfirmRequest['key_id'] = 3;
            $externalEventAfterConfirmRequest['external_id'] = $objCustomer->getEmail();
            $externalEventAfterConfirmRequest['data'] = [
                'global' => [
                    'First Name' => 'testDoubleOptInValue',
                    'Last Name' => 'Test last name'
                ]
            ];
        }

        /**
         * Logs for Sync completed with / without Error
         */
        $logsArray['id'] = $logId;
        $logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
        $logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
        if ($errorMsg == 1) {
            $logsArray['status'] = 'error';
            $logsArray['messages'] = 'Error in creating subscriber !!!';
        } else {
            $logsArray['status'] = 'success';
            $logsArray['messages'] = 'Created subscriber in Emarsys';
        }
        $this->logsHelper->manualLogsUpdate($logsArray);

        if ($frontendFlag != '') {
            $responseData = [
                'optInStatus' => $optInMagentoStatus,
                'apiResponseStatus' => $optInResult['status']
            ];
            return $responseData;
        }
    }
}
