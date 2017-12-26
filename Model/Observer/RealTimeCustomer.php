<?php
/**
 * @category   Emarsys
 * @package    Emarsys_Emarsys
 * @copyright  Copyright (c) 2017 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Model\Observer;

use Psr\Log\LoggerInterface;
use Magento\Framework\Event\ObserverInterface;

class RealTimeCustomer implements ObserverInterface
{
    private $logger;

    protected $customerFactory;

    protected $customerResourceModel;

    public function __construct(
        LoggerInterface $logger,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Framework\Registry $registry,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Emarsys\Emarsys\Model\Api\Contact $contactModel,
        \Emarsys\Emarsys\Helper\Data $dataHelper,
        \Emarsys\Emarsys\Model\ResourceModel\Customer $customerResourceModel
    ) {
    
        $this->dataHelper = $dataHelper;
        $this->logger = $logger;
        $this->registry = $registry;
        $this->contactModel = $contactModel;
        $this->_storeManager = $storeManager;
        $this->customerResourceModel = $customerResourceModel;
        $this->customerFactory = $customerFactory;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        try {
            $data = $observer->getEvent();
            $customer = $data->getCustomer();
            $storeId = $customer->getStoreId();
            $websiteId = $customer->getWebsiteId();

            if ($this->dataHelper->isEmarsysEnabled($websiteId) == 'false') {
                return;
            }

            $realtimeStatus = $this->customerResourceModel->getDataFromCoreConfig(
                'contacts_synchronization/emarsys_emarsys/realtime_sync',
                \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE,
                $websiteId
            );

            if (isset($data['email'])) {
                $customerData = $this->customerFactory->create()->setWebsiteId($websiteId)->loadByEmail($data['email']);
                $customerId = $customerData->getEntityId();
                $websiteId = $customerData->getWebsiteId();
            } else {
                $customerId = $customer->getId();
            }

            if ($realtimeStatus == 1) {
                $customerVar = 'create_customer_variable_' . $customerId;
                if ($this->registry->registry($customerVar) == 'created') {
                    return;
                }
                $this->contactModel->syncContact($customerId, $websiteId, $storeId);
                $this->registry->register($customerVar, 'created');
            } else {
                $this->dataHelper->syncFail($customerId, $websiteId, $storeId, 0, 1);
            }
        } catch (\Exception $e) {
            //TODO: add the error to log table
            //echo $e->getTraceAsString();
            //exit;
        }
    }

    public function syncContactData($customerId)
    {
        try {
            $storeId = $this->_storeManager->getStore()->getStoreId();
            $websiteId = $this->_storeManager->getStore()->getWebsiteId();
            $realtimeStatus = $this->customerResourceModel->getDataFromCoreConfig('contacts_synchronization/emarsys_emarsys/realtime_sync', \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE,$websiteId);
            if ($realtimeStatus == 1) {
                $this->contactModel->syncContact($customerId, $websiteId, $storeId);
            } else {
                $this->dataHelper->syncFail($customerId, $websiteId, $storeId, 0, 1);
            }
        } catch (\Exception $e) {
           //TODO: add the error to log table
           // echo $e->getMessage();
           // exit;
        }
    }

}
