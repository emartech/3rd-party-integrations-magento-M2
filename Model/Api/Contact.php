<?php
/**
 * @category   Emarsys
 * @package    Emarsys_Emarsys
 * @copyright  Copyright (c) 2017 Emarsys. (http://www.emarsys.net/)
 */
namespace Emarsys\Emarsys\Model\Api;

use Magento\Customer\Model\Customer;
use Emarsys\Emarsys\Model\ResourceModel\Customer as customerResourceModel;
use Emarsys\Emarsys\Helper\Data;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Emarsys\Emarsys\Helper\Logs;
use Magento\Customer\Model\ResourceModel\Customer\Collection as CustomerCollection;
use Emarsys\Emarsys\Helper\Country as EmarsysCountryHelper;

/**
 * Class Contact
 * API class for Emarsys API wrappers
 *
 * @package Emarsys\Emarsys\Model\Api
 */
class Contact
{
    /**
     * @var Api
     */
    protected $api;

    /**
     * @var
     */
    protected $customer;

    /**
     * @var
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
     * @var
     */
    protected $dataHelper;

    /**
     * @var CustomerCollection
     */
    protected $custColl;

    /**
     * @var EmarsysCountryHelper
     */
    protected $emarsysCountryHelper;

    /**
     * Contact constructor.
     *
     * @param Api $api
     * @param Customer $customer
     * @param customerResourceModel $customerResourceModel
     * @param DateTime $date
     * @param Logs $logsHelper
     * @param Data $dataHelper
     * @param CustomerCollection $customerColl
     * @param EmarsysFieldHelper $emarsysFieldHelper
     */
    public function __construct(
        Api $api,
        Customer $customer,
        customerResourceModel $customerResourceModel,
        DateTime $date,
        Logs $logsHelper,
        Data $dataHelper,
        CustomerCollection $customerColl,
        EmarsysCountryHelper $emarsysCountryHelper
    ) {
        $this->api = $api;
        $this->customer = $customer;
        $this->customerResourceModel = $customerResourceModel;
        $this->date = $date;
        $this->logsHelper = $logsHelper;
        $this->dataHelper = $dataHelper;
        $this->custColl = $customerColl;
        $this->emarsysCountryHelper = $emarsysCountryHelper;
    }

    /**
     * @param $customerId
     * @param $websiteId
     * @param $storeId
     * @param int $cron
     */
    public function syncContact($customerId, $websiteId, $storeId, $cron = 0)
    {
        $objCustomer = $this->customer->load($customerId);
        $arrCustomer = $objCustomer->getData();
        $logsArray['job_code'] = 'customer';
        $logsArray['status'] = 'started';
        $logsArray['messages'] = 'Customer is sync to Emarsys';
        $logsArray['created_at'] = $this->date->date('Y-m-d H:i:s', time());
        $logsArray['run_mode'] = 'Manual';
        $logsArray['auto_log'] = 'Complete';
        $logsArray['store_id'] = $storeId;
        $logsArray['website_id'] = $websiteId;
        $logId = $this->logsHelper->manualLogs($logsArray);
        $this->api->setWebsiteId($websiteId);

        $buildRequest = [];
        $keyField = $this->dataHelper->getContactUniqueField($websiteId);
        if ($keyField == 'email') {
            $buildRequest['key_id'] = $this->customerResourceModel->getKeyId('Email', $storeId);
            $buildRequest[$buildRequest['key_id']] = $objCustomer->getEmail();
        } elseif ($keyField == 'magento_id') {
            $buildRequest['key_id'] = $this->customerResourceModel->getKeyId('Magento Customer ID', $storeId);
            $buildRequest[$buildRequest['key_id']] = $customerId;
        } elseif ($keyField == 'unique_id') {
            $buildRequest['key_id'] = $this->customerResourceModel->getKeyId('Magento Customer Unique ID', $storeId);
            $buildRequest[$buildRequest['key_id']] = $objCustomer->getEmail() . "#" . $websiteId . "#" . $storeId;
        }

        $keyId = $this->customerResourceModel->getKeyId('Email', $storeId);
        $buildRequest[$keyId] = $objCustomer->getEmail();

        $keyId = $this->customerResourceModel->getKeyId('Magento Customer ID', $storeId);
        $buildRequest[$keyId] = $customerId;

        $keyId = $this->customerResourceModel->getKeyId('Magento Customer Unique ID', $storeId);
        $buildRequest[$keyId] = $objCustomer->getEmail() . "#" . $websiteId . "#" . $storeId;

        $getEmarsysMappedFields = $this->customerResourceModel->fetchMappedFields($storeId);

        foreach ($getEmarsysMappedFields as $mappedField) {
            if (isset($arrCustomer[$mappedField['attribute_code']]) && $mappedField['emarsys_contact_field'] != 0) {
                if (!is_null($mappedField['source_model'])) {
                    if ($mappedField['frontend_input'] == 'multiselect') {
                    } else {
                        $optionId = $arrCustomer[$mappedField['attribute_code']];
                        /**
                         * Get Mapped Emarsys OptionId
                         */
                        $emarsysOptionId = $optionId;
                        if ($emarsysOptionId) {
                            $buildRequest[$mappedField['emarsys_contact_field']] = $emarsysOptionId;
                        }
                    }
                } else {
                    $buildRequest[$mappedField['emarsys_contact_field']] = $arrCustomer[$mappedField['attribute_code']];
                }
            }
        }

        //Fetch Customer's Mapped Address Attributes
        $customerMappedAddressAttributes = $this->getMappedCustomersAddressAttributes($objCustomer, $storeId);
        foreach ($customerMappedAddressAttributes as $key => $value) {
            $buildRequest[$key] = $value;
        }

        $errorMsg = 0;
        if (count($buildRequest) > 0) {
            $logsArray['id'] = $logId;
            $logsArray['emarsys_info'] = 'Send Customer to Emarsys';
            $logsArray['action'] = 'Magento to Emarsys';
            $logsArray['message_type'] = 'Success';
            $logsArray['description'] = 'PUT ' . " contact/?create_if_not_exists=1 " . json_encode($buildRequest, JSON_PRETTY_PRINT);
            $logsArray['log_action'] = 'sync';
            $this->logsHelper->logs($logsArray);
            $result = $this->createContactInEmarsys($buildRequest);
            $logsArray['id'] = $logId;
            $logsArray['emarsys_info'] = 'Create customer in Emarsys';
            $logsArray['action'] = 'Synced to Emarsys';
            if ($result['status'] == '200') {
                $logsArray['message_type'] = 'Success';
                $res = 'PUT ' . " contact/?create_if_not_exists=1 " . json_encode($result, JSON_PRETTY_PRINT);
                $logsArray['description'] = 'Created customer ' . $objCustomer->getEmail() . ' in Emarsys succcessfully '.$res;
                $this->dataHelper->syncSuccess($customerId, $websiteId, $storeId, $cron);
            } else {
                $this->dataHelper->syncFail($customerId, $websiteId, $storeId, $cron, 1);
                $logsArray['message_type'] = 'Error';
                $logsArray['description'] = $result['body']['replyText'];
                $errorMsg = 1;
            }
            $logsArray['log_action'] = 'sync';
            $this->logsHelper->logs($logsArray);
        } else {
            $logsArray['id'] = $logId;
            $logsArray['emarsys_info'] = 'Create customer';
            $logsArray['action'] = 'Synced to Emarsys';
            $logsArray['message_type'] = 'error';
            $logsArray['description'] = 'Customer attribute mapping not working.';
            $logsArray['log_action'] = 'sync';
            $this->logsHelper->logs($logsArray);
        }

        /**
         * Logs for Sync completed with / without Error
         */
        $logsArray['id'] = $logId;
        $logsArray['executed_at'] = $this->date->date('Y-m-d H:i:s', time());
        $logsArray['finished_at'] = $this->date->date('Y-m-d H:i:s', time());
        if ($errorMsg == 1) {
            $logsArray['status'] = 'error';
            $logsArray['messages'] = 'Create customer in Emarsys with ERROR !!!';
        } else {
            $logsArray['status'] = 'success';
            $logsArray['messages'] = 'Create customer in Emarsys';
        }
        $this->logsHelper->manualLogsUpdate($logsArray);
    }

    /**
     * @param $customer
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function syncMultipleContacts($customer)
    {
        $logsArray['job_code'] = 'customerMultiple';
        $logsArray['status'] = 'started';
        $logsArray['messages'] = 'Customer is sync to Emarsys';
        $logsArray['created_at'] = $this->date->date('Y-m-d H:i:s', time());
        $logsArray['run_mode'] = 'Manual';
        $logsArray['auto_log'] = 'Complete';
        $logsArray['store_id'] = '';
        $logId = $this->logsHelper->manualLogs($logsArray);

        $buildRequest = [];
        $buildRequest['key_id'] = '3';
        $buildRequest['contacts'] = $customer;

        $custEmailIds = [];
        foreach ($customer as $cust) {
            $custEmailIds[] = $cust['3'];
        }
        $errorMsg = 0;
        if (count($buildRequest) > 0) {
            $result = $this->createContactInEmarsys($buildRequest);
            $logsArray['id'] = $logId;
            $logsArray['emarsys_info'] = 'Create customer in Emarsys';
            $logsArray['action'] = 'Synced to Emarsys';

            if ($result['status'] == '200') {
                $customerCollFail = $this->custColl;
                $customerCollSuccess = $this->custColl;
                $errIds = array_keys($result['body']['data']['errors']);
                if (count($result['body']['data']['errors'])) {
                    $dataDataColl = $customerCollFail->addAttributeToFilter('email', ["in" => $errIds]);
                    $custData = $dataDataColl->getData();
                    foreach ($custData as $custIndividualData) {
                        $this->dataHelper->syncFail(
                            $custIndividualData['entity_id'],
                            $custIndividualData['website_id'],
                            $custIndividualData['store_id'],
                            1,
                            1
                        );
                    }
                }
                if (count($result['body']['data']['ids'])) {
                    $successIds = array_diff($custEmailIds, $errIds);
                    $dataDataColl = $customerCollSuccess->addAttributeToFilter('email', ["in" => $successIds]);
                    $custData = $dataDataColl->getData();

                    foreach ($custData as $custIndividualData) {
                        $this->dataHelper->syncSuccess(
                            $custIndividualData['entity_id'],
                            $custIndividualData['website_id'],
                            $custIndividualData['store_id'],
                            1
                        );
                    }
                }
            }
        }
    }

    /**
     * @param $arrCustomerData
     * @return mixed|string
     * @throws \Exception
     */
    public function createContactInEmarsys($arrCustomerData)
    {
        $response = $this->api->sendRequest('PUT', 'contact/?create_if_not_exists=1', $arrCustomerData);
        return $response;
    }

    /**
     * Fetch Customer's Mapped Address attributes values
     * @param $customer
     * @param $storeId
     * @return array
     */
    public function getMappedCustomersAddressAttributes($customer, $storeId)
    {
        $addressFields = [];
        $mappedAttributes = $this->customerResourceModel->getMappedCustomerAttribute($storeId);
        if (isset($mappedAttributes) && count($mappedAttributes) != '') {
            $primaryBilling  = $customer->getPrimaryBillingAddress();
            $primaryShipping = $customer->getPrimaryshippingAddress();
            $mappedCountries = $this->emarsysCountryHelper->getMapping($storeId);
            $headers = [];
            $headerIndex = [];

            foreach($mappedAttributes as $attribute) {
                if ($attribute['emarsys_contact_field'] == NULL) {
                    continue;
                }
                $emarsysField = $this->customerResourceModel->getEmarsysFieldNameContact($attribute, $storeId);
                $headers[$attribute['magento_custom_attribute_id']] = $emarsysField['name'];
                $headerIndex[$attribute['emarsys_contact_field']] = $attribute['magento_custom_attribute_id'];
            }

            foreach($headers as $key => $value) {
                //using the custom magento id from the emarsys_magento_customer_attributes table
                $attributeCode = $this->customerResourceModel->getMagentoAttributeCode($key, $storeId);
                if ($attributeCode['entity_type_id'] == 2) { // If the field type is Address
                    $isShippingAttr = (strpos($attributeCode['attribute_code_custom'], 'default_shipping_') !== false) ? true : false;
                    $isBillingAttr = (strpos($attributeCode['attribute_code_custom'], 'default_billing_') !== false) ? true : false;
                    $index = array_search($key, $headerIndex);
                    if($index == 0) continue;
                    $attrValue = '';
                    if ($isShippingAttr) {
                        if(isset($customer['default_shipping']) && $customer['default_shipping'] != NULL && $customer['default_shipping'] != 0)  {
                            if ($primaryShipping) {
                                $attrValue = $primaryShipping->getData($attributeCode['attribute_code']);
                                if ($attributeCode['attribute_code'] == 'country_id') {
                                    $attrValue = (isset($mappedCountries[$attrValue]) ? $mappedCountries[$attrValue] : '');
                                } elseif ($attributeCode['attribute_code'] == 'street') {
                                    $attrValue = str_replace("\n", ',', $attrValue);
                                }
                            }
                        }
                    } elseif ($isBillingAttr) {
                        if(isset($customer['default_billing']) && $customer['default_billing'] != NULL && $customer['default_billing'] != 0)
                        {
                            if($primaryBilling) {
                                $attrValue = $primaryBilling->getData($attributeCode['attribute_code']);
                                if ($attributeCode['attribute_code'] == 'country_id') {
                                    $attrValue = (isset($mappedCountries[$attrValue]) ? $mappedCountries[$attrValue] : '');
                                } elseif ($attributeCode['attribute_code'] == 'street') {
                                    $attrValue = str_replace("\n", ',', $attrValue);
                                }
                            }
                        }
                    }
                    $addressFields[$index] = $attrValue;
                }
            }
        }

        return $addressFields;
    }
}
