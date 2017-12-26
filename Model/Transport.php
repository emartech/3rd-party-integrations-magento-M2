<?php
/**
 *  * Mail Transport
 *  */
namespace Emarsys\Emarsys\Model;

use Emarsys\Emarsys\Helper\Data;
use Emarsys\Emarsys\Model\ResourceModel\Customer as customerResourceModel;

class Transport extends \Zend_Mail_Transport_Sendmail implements \Magento\Framework\Mail\TransportInterface
{
    /**
     * @var \Magento\Framework\Mail\MessageInterface
     */
    protected $_message;

    public function __construct(
        Data $dataHelper,
        customerResourceModel $customerResourceModel,
        \Magento\Framework\Stdlib\DateTime\DateTime $date,
        \Magento\Framework\Mail\MessageInterface $message,
        $parameters = null
    ) {
    
        if (!$message instanceof \Zend_Mail) {
            throw new \InvalidArgumentException('The message should be an instance of \Zend_Mail');
        }

        parent::__construct($parameters);
        $this->dataHelper = $dataHelper;
        $this->date = $date;
        $this->customerResourceModel = $customerResourceModel;
        $this->_message = $message;
    }

    public function sendMessage()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $dataHelper = $objectManager->create('Emarsys\Emarsys\Helper\Data');
        $logsHelper = $objectManager->create('Emarsys\Emarsys\Helper\Logs');
        $storeManagerInterface = $objectManager->create('\Magento\Store\Model\StoreManagerInterface');

        try {
            $_emarsysPlaceholdersData = $this->_message->getEmarsysData();

            if (isset($_emarsysPlaceholdersData['store_id'])) {
                $storeId = $_emarsysPlaceholdersData['store_id'];
            } else {
                $storeId = $storeManagerInterface->getStore()->getId();
            }

            $websiteId = $storeManagerInterface->getStore($storeId)->getWebsiteId();
            $logsArray['job_code'] = 'transactional_mail';
            $logsArray['status'] = 'started';
            $logsArray['messages'] = 'Transactional Email Started';
            $logsArray['created_at'] = $this->date->date('Y-m-d H:i:s', time());
            $logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
            $logsArray['run_mode'] = 'Automatic';
            $logsArray['auto_log'] = 'Complete';
            $logsArray['store_id'] = $storeId;

            $logId = $logsHelper->manualLogs($logsArray);

            if($this->dataHelper->isEmarsysEnabled($websiteId) =='false'){
                $logsArray['id'] = $logId;
                $logsArray['emarsys_info'] = 'Transactional Mails';
                $logsArray['description'] = 'Emarsys is not enabled. Email sent from Magento.';
                $logsArray['action'] = 'Mail Sent';
                $logsArray['message_type'] = 'Error';
                $logsArray['log_action'] = 'True';
                $logsArray['website_id'] = $websiteId;
                $logsHelper->logs($logsArray);
                parent::send($this->_message);
            }


            $emarsysPlaceholdersData = '';
            $emarsysApiEventID = '';
            if(is_array($_emarsysPlaceholdersData)){
                if(isset($_emarsysPlaceholdersData['emarsysPlaceholders'])) {
                    $emarsysPlaceholdersData = $_emarsysPlaceholdersData['emarsysPlaceholders'];
                }
                if(isset($_emarsysPlaceholdersData['emarsysEventId'])){
                    $emarsysApiEventID = $_emarsysPlaceholdersData['emarsysEventId'];
                }
            }

            if ($emarsysPlaceholdersData == "" || $emarsysApiEventID == "") {
                $logsArray['id'] = $logId;
                $logsArray['emarsys_info'] = 'Transactional Mails';
                $logsArray['description'] = 'No Mapping Found for the Emarsys Event ID. Email sent from Magento.';
                $logsArray['action'] = 'Mail Sent';
                $logsArray['message_type'] = 'Error';
                $logsArray['log_action'] = 'True';
                $logsArray['website_id'] = $websiteId;
                $logsHelper->logs($logsArray);
                parent::send($this->_message);
            } else {
                $api = $objectManager->create('Emarsys\Emarsys\Model\Api\Api');
                $api->setWebsiteId($websiteId);
                $emarsysEnable = $dataHelper->getConfigValue('transaction_mail/transactionmail/enable_customer', 'websites', $websiteId);
                if ($emarsysEnable == '' && $websiteId == 1) {
                    $emarsysEnable = $dataHelper->getConfigValue('transaction_mail/transactionmail/enable_customer');
                }
                if (!$emarsysEnable) {
                    $logsArray['id'] = $logId;
                    $logsArray['emarsys_info'] = 'Transactional Mails';
                    $logsArray['description'] = 'Emarsys Transaction Email Either Disabled or Some Extension Conflict (if enabled). Email sent from Magento.';
                    $logsArray['action'] = 'Mail Sent';
                    $logsArray['message_type'] = 'Success';
                    $logsArray['log_action'] = 'True';
                    $logsArray['website_id'] = $websiteId;
                    $logsHelper->logs($logsArray);
                    parent::send($this->_message);
                } else {
                    $emarsysPlaceholdersData = $this->_message->getEmarsysData()['emarsysPlaceholders'];
                    $externalId = $this->_message->getRecipients()[0];
                    $buildRequest = [];

                    $keyField = $this->dataHelper->getContactUniqueField($websiteId);
                    if ($keyField == 'email') {
                        $buildRequest['key_id'] = $this->customerResourceModel->getKeyId('Email', $storeId);
                        $buildRequest[$buildRequest['key_id']] = $externalId;
                    } elseif ($keyField == 'magento_id') {
                        // check customer exists in magento or not
                        $customerId = $this->customerResourceModel->checkCustomerExistsInMagento($externalId, $websiteId,$storeId);
                        $data = [
                            'email' => $externalId,
                            'storeId' => $storeId
                        ];
                        $subscribeId = $this->customerResourceModel->getSubscribeIdFromEmail($data);
                        //if customer exists
                        if (!empty($customerId)) {
                            $buildRequest['key_id'] = $this->customerResourceModel->getKeyId('Magento Customer ID', $storeId);
                            $buildRequest[$buildRequest['key_id']] = $customerId;//$customerId;
                        } elseif (!empty($subscribeId)) {
                            $buildRequest['key_id'] = $this->customerResourceModel->getKeyId('Magento Subscriber ID', $storeId);
                            $buildRequest[$buildRequest['key_id']] = $subscribeId;//$subscribeId;
                        } else {
                            $buildRequest['key_id'] = $this->customerResourceModel->getKeyId('Email', $storeId);
                            $buildRequest[$buildRequest['key_id']] = $externalId;
                        }
                    } elseif ($keyField == 'unique_id') {
                        $buildRequest['key_id'] = $this->customerResourceModel->getKeyId('Magento Customer Unique ID', $storeId);
                        $buildRequest[$buildRequest['key_id']] = $externalId . "#" . $websiteId . "#" . $storeId;
                    }

                    $response = $api->sendRequest('PUT', 'contact/?create_if_not_exists=1', $buildRequest);

                    if (($response['status'] == 200) || ($response['status'] == 400 && $response['body']['replyCode'] == 2009)) {
                        $arrCustomerData = [
                            "key_id" => $buildRequest['key_id'],
                            "external_id" => $buildRequest[$buildRequest['key_id']],
                            "data" => $emarsysPlaceholdersData
                        ];
                        $req = 'POST ' . " event/$emarsysApiEventID/trigger: " . json_encode($arrCustomerData, JSON_PRETTY_PRINT);

                        $logsArray['id'] = $logId;
                        $logsArray['emarsys_info'] = 'Transactional Mails';
                        $logsArray['description'] = $req;
                        $logsArray['action'] = 'Mail Sent';
                        $logsArray['message_type'] = 'Success';
                        $logsArray['log_action'] = 'True';
                        $logsArray['website_id'] = $websiteId;
                        $logsHelper->logs($logsArray);

                        $emarsysApiEventID = $this->_message->getEmarsysData()['emarsysEventId'];
                        $res = $api->sendRequest('POST', "event/$emarsysApiEventID/trigger", $arrCustomerData);

                        if ($res['status'] == 200) {
                            $logsArray['id'] = $logId;
                            $logsArray['emarsys_info'] = 'Transactional Mails';
                            $logsArray['description'] = print_r($res, true);
                            $logsArray['action'] = 'Mail Sent';
                            $logsArray['message_type'] = 'Success';
                            $logsArray['log_action'] = 'True';
                            $logsArray['website_id'] = $websiteId;
                            $logsHelper->logs($logsArray);
                            $logsArray['status'] = 'success';
                            $logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
                            $logId = $logsHelper->manualLogs($logsArray);
                        } else {
                            $logsArray['id'] = $logId;
                            $logsArray['emarsys_info'] = 'Transactional Mails';
                            $logsArray['description'] = print_r($res, true);
                            $logsArray['action'] = 'Mail Sent Fail';
                            $logsArray['message_type'] = 'Error';
                            $logsArray['log_action'] = 'False';
                            $logsArray['website_id'] = $websiteId;
                            $logsHelper->logs($logsArray);
                            $logsArray['status'] = 'error';
                            $logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
                            $logId = $logsHelper->manualLogs($logsArray);
                        }
                    } else {
                        $logsArray['id'] = $logId;
                        $logsArray['emarsys_info'] = 'Transactional Mails';
                        $logsArray['description'] = 'Failed to Sync Contact to Emarsys.  Request: ' . print_r($buildRequest, true) . '\n Response: ' . print_r($response, true);
                        $logsArray['action'] = 'Mail Sent Fail';
                        $logsArray['message_type'] = 'Error';
                        $logsArray['log_action'] = 'False';
                        $logsArray['website_id'] = $websiteId;
                        $logsHelper->logs($logsArray);
                    }
                }
            }
        } catch (\Exception $e) {
            $logsArray['id'] = $logId;
            $logsArray['emarsys_info'] = 'Emarsys Transactional Email Error';
            $logsArray['description'] = $e->getMessage() . " Due to this error, Email Sent From Magento.";
            $logsArray['action'] = 'Mail Sending Fail';
            $logsArray['message_type'] = 'Error';
            $logsArray['log_action'] = 'Fail';
            $logsArray['website_id'] = $websiteId;
            $logsHelper->logs($logsArray);
            $logsArray['status'] = 'error';
            $logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
            $logId = $logsHelper->manualLogs($logsArray);
            parent::send($this->_message);
        }
    }

    /**
     * @inheritdoc
     */
    public function getMessage()
    {
        return $this->_message;
    }
}
