<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Model\Api;

use Emarsys\Emarsys\Helper\Country as EmarsysCountryHelper;
use Emarsys\Emarsys\Helper\Cron as EmarsysCronHelper;
use Emarsys\Emarsys\Helper\Data as EmarsysHelperData;
use Emarsys\Emarsys\Helper\Logs;
use Emarsys\Emarsys\Logger\Logger as EmarsysLogger;
use Emarsys\Emarsys\Model\AsyncFactory;
use Emarsys\Emarsys\Model\QueueFactory;
use Emarsys\Emarsys\Model\ResourceModel\CustomerFactory as CustomerResourceModel;
use Emarsys\Emarsys\Model\ResourceModel\Field\CollectionFactory as EmarsysFieldCollectionFactory;
use Magento\Customer\Model\Address;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\ResourceModel\Customer\Collection as CustomerCollection;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\StoreManagerInterface;

class Contact
{
    /**
     * @var Api
     */
    protected $api;

    /**
     * @var Customer
     */
    protected $customer;

    /**
     * @var CustomerFactory
     */
    protected $customerFactory;

    /**
     * @var CustomerResourceModel
     */
    protected $customerResourceModel;

    /**
     * @var DateTime
     */
    protected $date;

    /**
     * @var Logs
     */
    protected $logsHelper;

    /**
     * @var EmarsysHelperData
     */
    protected $emarsysHelper;

    /**
     * @var CustomerCollection
     */
    protected $custColl;

    /**
     * @var EmarsysCountryHelper
     */
    protected $emarsysCountryHelper;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var QueueFactory
     */
    protected $queueModel;

    /**
     * @var AsyncFactory
     */
    protected $asyncModel;

    /**
     * @var MessageManagerInterface
     */
    protected $messageManager;

    /**
     * @var Subscriber
     */
    protected $subscriberApi;

    /**
     * @var EmarsysLogger
     */
    protected $emarsysLogger;

    /**
     * @var array
     */
    protected $mappedCustomerAttribute = [];

    /**
     * @var array
     */
    protected $fetchMappedFields = [];

    /**
     * @var array
     */
    protected $emarsysContactFields = [];

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
     * @var int
     */
    protected $emailKey;

    /**
     * @var int
     */
    protected $customerIdKey;

    /**
     * @var EmarsysFieldCollectionFactory
     */
    protected $fieldCollection;

    /**
     * @var array
     */
    protected $logsArray;

    /**
     * Contact constructor.
     *
     * @param Api $api
     * @param Customer $customer
     * @param CustomerFactory $customerFactory
     * @param CustomerResourceModel $customerResourceModel
     * @param DateTime $date
     * @param Logs $logsHelper
     * @param EmarsysHelperData $emarsysHelper
     * @param CustomerCollection $customerColl
     * @param EmarsysCountryHelper $emarsysCountryHelper
     * @param StoreManagerInterface $storeManager
     * @param QueueFactory $queueModel
     * @param AsyncFactory $asyncModel
     * @param MessageManagerInterface $messageManager
     * @param Subscriber $subscriberApi
     * @param EmarsysLogger $emarsysLogger
     * @param EmarsysFieldCollectionFactory $fieldCollection
     */
    public function __construct(
        Api $api,
        Customer $customer,
        CustomerFactory $customerFactory,
        CustomerResourceModel $customerResourceModel,
        DateTime $date,
        Logs $logsHelper,
        EmarsysHelperData $emarsysHelper,
        CustomerCollection $customerColl,
        EmarsysCountryHelper $emarsysCountryHelper,
        StoreManagerInterface $storeManager,
        QueueFactory $queueModel,
        AsyncFactory $asyncModel,
        MessageManagerInterface $messageManager,
        Subscriber $subscriberApi,
        EmarsysLogger $emarsysLogger,
        EmarsysFieldCollectionFactory $fieldCollection
    ) {
        $this->api = $api;
        $this->customer = $customer;
        $this->customerFactory = $customerFactory;
        $this->customerResourceModel = $customerResourceModel;
        $this->date = $date;
        $this->logsHelper = $logsHelper;
        $this->emarsysHelper = $emarsysHelper;
        $this->custColl = $customerColl;
        $this->emarsysCountryHelper = $emarsysCountryHelper;
        $this->storeManager = $storeManager;
        $this->queueModel = $queueModel;
        $this->asyncModel = $asyncModel;
        $this->messageManager = $messageManager;
        $this->subscriberApi = $subscriberApi;
        $this->emarsysLogger = $emarsysLogger;
        $this->fieldCollection = $fieldCollection;
    }

    /**
     * @param Customer $customer
     * @param $websiteId
     * @param $storeId
     * @param int $cron
     * @param null|Address $customerAddress
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Zend_Json_Exception
     */
    public function syncContact($customer, $websiteId, $storeId, $cron = 0, $customerAddress = null)
    {
        $this->logsArray['job_code'] = 'customer';
        $this->logsArray['status'] = 'started';
        $this->logsArray['messages'] = 'Customer sync to Emarsys';
        $this->logsArray['created_at'] = $this->date->date('Y-m-d H:i:s', time());
        $this->logsArray['run_mode'] = 'Manual';
        $this->logsArray['auto_log'] = 'Complete';
        $this->logsArray['store_id'] = $storeId;
        $this->logsArray['website_id'] = $websiteId;
        $this->logsArray['id'] = $this->logsHelper->manualLogs($this->logsArray);
        $this->logsArray['log_action'] = 'sync';
        $this->api->setWebsiteId($websiteId);

        $errorMsg = 0;
        $store = $this->storeManager->getStore($storeId);
        $sId = $this->emarsysHelper->getFirstStoreIdOfWebsite($websiteId);

        if (!($customer instanceof Customer)) {
            $customer = $this->customer->load($customer->getId());
        }

        $customerResourceModel = $this->customerResourceModel->create();
        $emailKey = $customerResourceModel->getKeyId(EmarsysHelperData::CUSTOMER_EMAIL, $sId);
        $customerIdKey = $customerResourceModel->getKeyId(EmarsysHelperData::CUSTOMER_ID, $sId);
        unset($customerResourceModel);

        $buildRequest = $this->getCustomerPayload($customer, $sId, $emailKey, $customerIdKey);
        $getEmarsysMappedFields = $this->fetchMappedFields($sId);
        if (empty($getEmarsysMappedFields)) {
            $errorMsg = 1;
        }

        if (count($buildRequest) > 0) {
            $this->logsArray['emarsys_info'] = 'Send Customer to Emarsys';
            $this->logsArray['action'] = 'Magento to Emarsys';
            $this->logsArray['message_type'] = 'Success';
            $this->logsArray['description'] = 'PUT ' . Api::CONTACT_CREATE_IF_NOT_EXISTS . ' ' . \Zend_Json::encode($buildRequest);
            if ($this->emarsysHelper->isAsyncEnabled()) {
                $this->asyncModel->create()
                    ->setWebsiteId($websiteId)
                    ->setEndpoint(Api::CONTACT_CREATE_IF_NOT_EXISTS)
                    ->setEmail($customer->getEmail())
                    ->setCustomerId($customer->getId())
                    ->setSubscriberId(null)
                    ->setRequestBody(\Zend_Json::encode($buildRequest))
                    ->save();

                $this->logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
                $this->logsArray['emarsys_info'] = 'Added to Async queue';
                $this->logsArray['description'] = 'Added to Async queue';
                $this->logsHelper->manualLogs($this->logsArray);
                return true;
            }
            $this->logsHelper->manualLogs($this->logsArray);

            $result = $this->api->createContactInEmarsys($buildRequest);

            $this->logsArray['emarsys_info'] = 'Create customer in Emarsys';
            $this->logsArray['action'] = 'Synced to Emarsys';

            if ($result['status'] == '200') {
                $this->logsArray['message_type'] = 'Success';

                $confirmUrl = '';
                if ($customer->getConfirmation()) {
                    $confirmUrl = ' | Confirmation URL : '
                        . $store->getUrl(
                            'customer/account/confirm',
                            ['id' => $customer->getId(), 'key' => $customer->getConfirmation()]
                        );
                }

                $res = 'PUT ' . Api::CONTACT_CREATE_IF_NOT_EXISTS . ' ' . \Zend_Json::encode($result);
                $this->logsArray['description'] = 'Created customer ' . $customer->getEmail() .
                    ' in Emarsys successfully | ' . $res . ' | ' . $confirmUrl;
                $this->emarsysHelper->syncSuccess($customer->getId(), $websiteId, $storeId, $cron);
            } else {
                $this->emarsysHelper->syncFail($customer->getId(), $websiteId, $storeId, $cron, 1);
                $this->logsArray['message_type'] = 'Error';
                $this->logsArray['description'] = \Zend_Json::encode($result);
                $errorMsg = 1;
            }
            $this->logsHelper->manualLogs($this->logsArray);
        } else {
            $this->logsArray['emarsys_info'] = 'Create customer';
            $this->logsArray['action'] = 'Synced to Emarsys';
            $this->logsArray['message_type'] = 'error';
            $this->logsArray['description'] = 'Customer attribute mapping not working.';
            $this->logsHelper->manualLogs($this->logsArray);
        }

        /**
         * Logs for Sync completed with / without Error
         */
        $this->logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
        $this->logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
        if ($errorMsg == 1) {
            $this->logsArray['status'] = 'error';
            $this->logsArray['message_type'] = 'Error';
            $this->logsArray['emarsys_info'] = 'Error';
            $this->logsArray['description'] = 'ERROR on Customer creation';
            if (empty($getEmarsysMappedFields)) {
                $this->logsArray['description'] = 'ERROR on Customer creation. Mapping is empty.';
            }
        } else {
            $this->logsArray['status'] = 'success';
            $this->logsArray['message_type'] = 'Success';
            $this->logsArray['emarsys_info'] = 'Success';
            $this->logsArray['description'] = 'Created Customer in Emarsys';
        }
        $this->logsHelper->manualLogs($this->logsArray);

        return $errorMsg ? false : true;
    }

    /**
     * Fetch Customer's Mapped Address attributes values
     *
     * @param $customer
     * @param int $storeId
     * @param null|Address $customerAddress
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Zend_Json_Exception
     */
    public function getMappedCustomersAddressAttributes($customer, $storeId, $customerAddress = null)
    {
        $addressFields = [];
        $mappedAttributes = $this->getMappedCustomerAttribute($storeId);
        if (count($mappedAttributes)) {
            $primaryShipping = $customer->getPrimaryShippingAddress();
            if (($customerAddress && $customerAddress->getDefaultShipping()) || !$primaryShipping) {
                $primaryShipping = $customerAddress;
            } elseif ($customerAddress && $primaryShipping->getId() == $customerAddress->getId()) {
                $primaryShipping = $customerAddress;
            }
            if (!$primaryShipping) {
                $primaryShipping = current($customer->getAddresses());
            }

            $primaryBilling = $customer->getPrimaryBillingAddress();
            if (($customerAddress && $customerAddress->getDefaultBilling()) || !$primaryBilling) {
                $primaryBilling = $customerAddress;
            } elseif ($customerAddress && $primaryBilling->getId() == $customerAddress->getId()) {
                $primaryBilling = $customerAddress;
            }
            if (!$primaryBilling) {
                $primaryBilling = $primaryShipping;
            }

            $mappedCountries = $this->emarsysCountryHelper->getMapping($storeId);

            foreach ($mappedAttributes as $key => $attribute) {
                if (!$attribute['emarsys_contact_field']) {
                    continue;
                }
                $attributeCode = $this->customerResourceModel->create()->getMagentoAttributeCode(
                    $attribute['magento_custom_attribute_id'],
                    $storeId
                );
                if (!empty($attributeCode) && $attributeCode['entity_type_id'] == 2) { // If the field type is Address
                    $isShippingAttr = strpos(
                        $attributeCode['attribute_code_custom'],
                        'default_shipping_'
                    ) !== false;
                    $isBillingAttr = strpos(
                        $attributeCode['attribute_code_custom'],
                        'default_billing_'
                    ) !== false;
                    $attrValue = '';
                    if ($isShippingAttr && $primaryShipping) {
                        $attrValue = $primaryShipping->getData($attributeCode['attribute_code']);
                    } elseif ($isBillingAttr && $primaryBilling) {
                        $attrValue = $primaryBilling->getData($attributeCode['attribute_code']);
                    }
                    if ($attributeCode['attribute_code'] == 'country_id') {
                        $attrValue = isset($mappedCountries[$attrValue]) ? $mappedCountries[$attrValue] : '';
                    } elseif ($attributeCode['attribute_code'] == 'street') {
                        $attrValue = str_replace("\n", ', ', $attrValue);
                    }

                    $addressFields[$attribute['emarsys_contact_field']] = $attrValue;
                }
            }
        }

        return $addressFields;
    }

    /**
     * @param $objCustomer
     * @param $storeId
     * @param $emailKey
     * @param $customerIdKey
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Zend_Json_Exception
     */
    public function getCustomerPayload(
        $objCustomer,
        $storeId,
        $emailKey,
        $customerIdKey
    ) {
        $customerData = [];
        if ($emailKey && $objCustomer->getEmail()) {
            $customerData[$emailKey] = $objCustomer->getEmail();
        }

        if ($customerIdKey && $objCustomer->getId()) {
            $customerData[$customerIdKey] = $objCustomer->getId();
        }

        $getEmarsysMappedFields = $this->fetchMappedFields($storeId);

        foreach ($getEmarsysMappedFields as $mappedField) {
            if ($objCustomer->getData($mappedField['attribute_code']) && $mappedField['emarsys_contact_field'] != 0) {
                if (!is_null($mappedField['source_model'])) {
                    if ($mappedField['frontend_input'] != 'multiselect') {
                        $optionId = $objCustomer->getData($mappedField['attribute_code']);
                        //Get Mapped Emarsys OptionId
                        $option = $this->getMappedOption($storeId, $optionId);
                        if ($option->getEmarsysOptionId()) {
                            $customerData[$option->getEmarsysFieldId()] = $option->getEmarsysOptionId();
                        }
                    }
                } else {
                    $customerData[$mappedField['emarsys_contact_field']] = $objCustomer->getData($mappedField['attribute_code']);
                }
            }
        }
        //Fetch Customer's Mapped Address Attributes
        $customerMappedAddressAttributes = $this->getMappedCustomersAddressAttributes($objCustomer, $storeId);
        foreach ($customerMappedAddressAttributes as $key => $value) {
            $customerData[$key] = $value;
        }

        return $customerData;
    }

    /**
     * @param $customerCollectionArray
     * @param $keyId
     * @return array
     */
    public function prepareCustomerPayload($customerCollectionArray, $keyId)
    {
        $buildRequest = [];
        if ($keyId) {
            $buildRequest['key_id'] = $keyId;
            $buildRequest['contacts'] = $customerCollectionArray;
        }

        return $buildRequest;
    }

    /**
     * @param $exportMode
     * @param $data
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Zend_Json_Exception
     */
    public function preparePayloadAndSyncMultipleContacts($exportMode, $data)
    {
        $websiteId = $data['website'];

        if (isset($data['storeId'])) {
            $storeId = $data['storeId'];
        } else {
            $storeId = $this->emarsysHelper->getFirstStoreIdOfWebsite($websiteId);
        }

        $sId = $this->emarsysHelper->getFirstStoreIdOfWebsite($websiteId);

        $this->exportMode = $exportMode;
        $this->storeId = $storeId;
        $this->websiteId = $websiteId;

        $customerResourceModel = $this->customerResourceModel->create();
        $emailKey = $customerResourceModel->getKeyId(EmarsysHelperData::CUSTOMER_EMAIL, $sId);
        $customerIdKey = $customerResourceModel->getKeyId(EmarsysHelperData::CUSTOMER_ID, $sId);
        $this->emailKey = $emailKey;
        $this->customerIdKey = $customerIdKey;

        $success = false;

        //initial logs for customer export
        $this->logsArray['job_code'] = 'customer';
        $this->logsArray['status'] = 'started';
        $this->logsArray['messages'] = 'Customer Bulk Export initiated';
        $this->logsArray['created_at'] = $this->date->date('Y-m-d H:i:s', time());
        $this->logsArray['run_mode'] = 'Manual';
        $this->logsArray['auto_log'] = 'Complete';
        $this->logsArray['store_id'] = $this->storeId;
        $this->logsArray['website_id'] = $this->websiteId;
        if (!$this->logsArray['id']) {
            $this->logsArray['id'] = $this->logsHelper->manualLogs($this->logsArray, 1);
        }
        $this->logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
        $this->logsArray['log_action'] = 'sync';
        $this->logsArray['action'] = 'contact sync';

        //customer export starts
        $this->logsArray['emarsys_info'] = __('Customer Export Started');
        $this->logsArray['description'] = __('Customer Export Started for Store ID : %1', $this->storeId);
        $this->logsArray['message_type'] = 'Success';
        $this->logsHelper->manualLogs($this->logsArray);

        //check customer attributes are mapped
        $mappedAttributes = $this->getMappedCustomerAttribute($sId);
        if (count($mappedAttributes)) {
            $allCustomersPayload = [];
            if ($exportMode == EmarsysCronHelper::CRON_JOB_CUSTOMER_SYNC_QUEUE) {
                $queueCollection = $this->queueModel->create()->getCollection();
                $queueCollection->addFieldToSelect('entity_id');
                $queueCollection->addFieldToFilter('entity_type_id', 1);

                //Prepare Customers Payload Array
                foreach ($queueCollection as $item) {
                    $allCustomersPayload[] = $this->getCustomerPayload(
                        $this->customerFactory->create()->load($item->getEntityId()),
                        $sId,
                        $emailKey,
                        $customerIdKey
                    );
                }

                $this->processBatch($allCustomersPayload, $this->logsArray);
            } else {
                $params = [
                    'website' => $websiteId,
                    'storeId' => $storeId,
                    'fromDate' => $data['fromDate'],
                    'toDate' => $data['toDate'],
                ];
                $firstPageNumber = $currentPageNumber = isset($data['page']) ? $data['page'] : 1;

                $customerCollection = $this->customerResourceModel->create()->getCustomerCollection(
                    $params,
                    $storeId,
                    $currentPageNumber
                );
                $lastPageNumber = $customerCollection->getLastPageNumber();

                //Prepare Customers Payload Array
                while ($currentPageNumber <= $lastPageNumber) {
                    if ($currentPageNumber != $firstPageNumber) {
                        $customerCollection = $this->customerResourceModel->create()->getCustomerCollection(
                            $params,
                            $storeId,
                            $currentPageNumber
                        );
                    }
                    foreach ($customerCollection as $customerData) {
                        $allCustomersPayload[] = $this->getCustomerPayload(
                            $customerData,
                            $sId,
                            $emailKey,
                            $customerIdKey
                        );
                    }

                    $success = $this->processBatch($allCustomersPayload);

                    $this->logsArray['emarsys_info'] = __('Processing data for store %1', $storeId);
                    $this->logsArray['description'] = __('%1 of %2', $currentPageNumber, $lastPageNumber);
                    $this->logsArray['message_type'] = $success ? 'Success' : 'False';
                    $this->logsHelper->manualLogs($this->logsArray);
                    $currentPageNumber++;
                    unset($customerCollection);
                    unset($allCustomersPayload);
                }
            }
        } else {
            $this->logsArray['emarsys_info'] = 'Attributes are not mapped';
            $this->logsArray['description'] = 'Failed to sync contacts. Customer attributes are not mapped.';
            $this->logsArray['action'] = 'synced to emarsys';
            $this->logsArray['message_type'] = 'Error';
            $this->logsHelper->manualLogs($this->logsArray);
            $this->messageManager->addErrorMessage("Attributes are not mapped for this store view !!!");
        }

        if ($success) {
            $this->logsArray['status'] = 'success';
            $this->logsArray['messages'] = 'Customer export completed';
        } else {
            $this->logsArray['status'] = 'error';
            $this->logsArray['messages'] = 'Customer export have an error. Please check';
        }
        $this->logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
        $this->logsHelper->manualLogs($this->logsArray);

        return $success;
    }

    public function processBatch($allCustomersPayload)
    {
        if (empty($allCustomersPayload)) {
            //no Customers data found
            $this->logsArray['emarsys_info'] = 'No Customers Found.';
            $this->logsArray['action'] = 'Magento to Emarsys';
            $this->logsArray['message_type'] = 'Error';
            $this->logsArray['description'] = __('No Customers for the store with store id %1.', $this->storeId);
            $this->logsHelper->manualLogs($this->logsArray);
            $this->messageManager->addErrorMessage(__(
                'No Customers found for the store with store id %1.',
                $this->storeId
            ));
            return false;
        }

        $buildRequest = $this->prepareCustomerPayload($allCustomersPayload, $this->emailKey);
        if (count($buildRequest) > 0) {
            $this->logsArray['emarsys_info'] = 'Send customers to Emarsys';
            $this->logsArray['action'] = 'Magento to Emarsys';
            $this->logsArray['message_type'] = 'Success';
            $this->logsArray['description'] = 'PUT ' . Api::CONTACT_CREATE_IF_NOT_EXISTS
                . ' ' . \Zend_Json::encode($buildRequest);
            $this->logsHelper->manualLogs($this->logsArray);
            $this->emarsysLogger->info($this->logsArray['description']);

            //Send request to Emarsys with Customer's Data
            $this->api->setWebsiteId($this->websiteId);
            $result = $this->api->createContactInEmarsys($buildRequest);

            $this->logsArray['emarsys_info'] = 'Create customers in Emarsys';
            $this->logsArray['action'] = 'Synced to Emarsys';
            $res = 'PUT ' . Api::CONTACT_CREATE_IF_NOT_EXISTS . ' ' . \Zend_Json::encode($result);

            if ($result['status'] == '200') {
                $this->logsArray['message_type'] = 'Success';
                $this->logsArray['emarsys_info'] = __('Created customers in Emarsys succcessfully');
                $this->logsArray['description'] = "Created customers in Emarsys succcessfully " . $res;

                if ($this->exportMode == EmarsysCronHelper::CRON_JOB_CUSTOMER_SYNC_QUEUE) {
                    $custEmailIds = [];
                    foreach ($allCustomersPayload as $cust) {
                        if (isset($cust[$this->emailKey])) {
                            $custEmailIds[] = $cust[$this->emailKey];
                        }
                    }
                    $customerCollFail = $this->custColl;
                    $customerCollSuccess = $this->custColl;
                    $errIds = [];

                    if (isset($result['body']['data']['errors'])) {
                        $errIds = array_keys($result['body']['data']['errors']);
                        if (count($result['body']['data']['errors'])) {
                            $dataDataColl = $customerCollFail->addAttributeToFilter('email', ["in" => $errIds]);
                            $custData = $dataDataColl->getData();
                            foreach ($custData as $custIndividualData) {
                                $this->emarsysHelper->syncFail(
                                    $custIndividualData['entity_id'],
                                    $custIndividualData['website_id'],
                                    $custIndividualData['store_id'],
                                    1,
                                    1
                                );
                            }
                        }
                    }

                    if (count($result['body']['data']['ids'])) {
                        $successIds = array_diff($custEmailIds, $errIds);
                        $dataDataColl = $customerCollSuccess->addAttributeToFilter('email', ["in" => $successIds]);
                        $custData = $dataDataColl->getData();
                        foreach ($custData as $custIndividualData) {
                            $this->emarsysHelper->syncSuccess(
                                $custIndividualData['entity_id'],
                                $custIndividualData['website_id'],
                                $custIndividualData['store_id'],
                                1
                            );
                        }
                    }
                }
                $this->logsHelper->manualLogs($this->logsArray);
                $this->emarsysLogger->info($this->logsArray['description']);
            } else {
                //error response from emarsys
                $this->logsArray['emarsys_info'] = __('Error while customer export.');
                $this->logsArray['message_type'] = 'Error';
                $this->logsArray['description'] = $res;
                $this->messageManager->addErrorMessage(
                    __('Customers export have an error. Please check emarsys logs for more details!!')
                );
                $this->logsHelper->manualLogs($this->logsArray);
                $this->emarsysLogger->info($this->logsArray['description']);
                return false;
            }
        }

        return true;
    }

    /**
     * @param $exportMode
     * @param $data
     * @return bool|void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function syncFullContactUsingApi($exportMode, $data)
    {
        $websiteId = $data['website'];

        $website = $this->storeManager->getWebsite($websiteId);

        if (!$this->emarsysHelper->isContactsSynchronizationEnable($websiteId)) {
            return;
        }

        if (isset($data['storeId'])) {
            $storeId = $data['storeId'];
        } else {
            $storeId = $this->emarsysHelper->getFirstStoreIdOfWebsite($websiteId);
        }

        $fromDate = (isset($data['fromDate']) && !empty($data['fromDate']))
            ? $data['fromDate']
            : '';
        $toDate = (isset($data['toDate']) && !empty($data['toDate']))
            ? $data['toDate']
            : $this->date->date('Y-m-d') . ' 23:59:59';
        $page = (isset($data['page']) && !empty($data['page']))
            ? $data['page']
            : 1;

        $params = [
            'website' => $websiteId,
            'storeId' => $storeId,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'page' => $page,
        ];
        $errorStatus = true;

        //initial logs for customer export
        $this->logsArray['job_code'] = 'customer';
        $this->logsArray['status'] = 'started';
        $this->logsArray['messages'] = 'Customer Bulk Export initiated';
        $this->logsArray['created_at'] = $this->date->date('Y-m-d H:i:s', time());
        $this->logsArray['run_mode'] = 'Manual';
        $this->logsArray['auto_log'] = 'Complete';
        $this->logsArray['store_id'] = $storeId;
        $this->logsArray['website_id'] = $websiteId;
        $this->logsArray['id'] = $this->logsHelper->manualLogs($this->logsArray, 1);
        $this->logsArray['log_action'] = 'sync';
        $this->logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
        $this->logsArray['action'] = 'synced to emarsys';

        //check if emarsys enabled for the website
        if ($this->emarsysHelper->isEmarsysEnabled($websiteId) &&
            $website->getConfig(EmarsysHelperData::XPATH_EMARSYS_ENABLE_CONTACT_FEED)
        ) {
            $errorStatus = $this->exportDataToApi($exportMode, $params);
        } else {
            //Emarsys is disabled for the store
            $this->logsArray['emarsys_info'] = __('Emarsys is disabled');
            $this->logsArray['description'] = __('Emarsys is disabled for the store');
            $this->logsArray['message_type'] = 'Error';
            $this->logsHelper->manualLogs($this->logsArray);
        }

        if ($errorStatus) {
            $this->logsArray['status'] = 'error';
            $this->logsArray['messages'] = 'Something went wrong, please check logs';
        } else {
            $this->logsArray['status'] = 'success';
            $this->logsArray['messages'] = 'Contacts successfully synced';
        }
        $this->logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
        $this->logsHelper->manualLogs($this->logsArray);

        return $errorStatus;
    }

    public function exportDataToApi($exportMode, $data)
    {
        $errorStatus = true;

        switch ($exportMode) {
            case EmarsysCronHelper::CRON_JOB_CUSTOMER_SYNC_QUEUE:
                try {
                    $customerExportStatus = $this->preparePayloadAndSyncMultipleContacts($exportMode, $data);
                } catch (\Exception $e) {
                    $customerExportStatus = false;
                }
                try {
                    $subscriberExportStatus = $this->subscriberApi->syncMultipleSubscriber(
                        $exportMode,
                        $data,
                        $this->logsArray['id']
                    );
                } catch (\Exception $e) {
                    $subscriberExportStatus = false;
                }

                if ($subscriberExportStatus && $customerExportStatus) {
                    $errorStatus = false;
                }
                break;
            default:
                try {
                    $customerExportStatus = $this->preparePayloadAndSyncMultipleContacts($exportMode, $data);
                } catch (\Exception $e) {
                    $customerExportStatus = false;
                }
                if ($customerExportStatus) {
                    $errorStatus = false;
                }
                break;
        }

        return $errorStatus;
    }

    /**
     * @param $storeId
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getMappedCustomerAttribute($storeId)
    {
        if (!isset($this->mappedCustomerAttribute[$storeId])) {
            $this->mappedCustomerAttribute[$storeId] = $this->customerResourceModel->create()
                ->getMappedCustomerAttribute($storeId);
        }

        return $this->mappedCustomerAttribute[$storeId];
    }

    /**
     * @param $storeId
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function fetchMappedFields($storeId)
    {
        if (!isset($this->fetchMappedFields[$storeId])) {
            $this->fetchMappedFields[$storeId] = $this->customerResourceModel->create()->fetchMappedFields($storeId);
        }

        return $this->fetchMappedFields[$storeId];
    }

    /**
     * @param int $storeId
     * @param int $magentoOptionId
     * @return bool | int
     */
    public function getMappedOption($storeId, $magentoOptionId)
    {
        if (!isset($this->emarsysContactFields[$storeId])) {
            $this->emarsysContactFields[$storeId] = $this->fieldCollection->create()
                ->addFilter('store_id', $storeId);
        }

        return $this->emarsysContactFields[$storeId]->getItemByColumnValue('magento_option_id', $magentoOptionId);
    }
}
