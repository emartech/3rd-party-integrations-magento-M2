<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Model\CustomerFactory;
use Emarsys\Emarsys\Helper\Data as EmarsysHelper;
use Emarsys\Emarsys\Model\Api\Contact;
use Emarsys\Emarsys\Model\ResourceModel\Customer;

/**
 * Class AfterAddressSaveObserver
 */
class AfterAddressSaveObserver implements ObserverInterface
{
    /**
     * @var EmarsysHelper
     */
    protected $emarsysHelper;

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var Contact
     */
    protected $contactModel;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var Customer
     */
    protected $customerResourceModel;

    /**
     * @var CustomerFactory
     */
    protected $customerFactory;

    /**
     * AfterAddressSaveObserver constructor.
     *
     * @param EmarsysHelper $emarsysHelper
     * @param Registry $registry
     * @param Contact $contactModel
     * @param StoreManagerInterface $storeManager
     * @param Customer $customerResourceModel
     * @param CustomerFactory $customerFactory
     */
    public function __construct(
        EmarsysHelper $emarsysHelper,
        Registry $registry,
        Contact $contactModel,
        StoreManagerInterface $storeManager,
        Customer $customerResourceModel,
        CustomerFactory $customerFactory
    ) {
        $this->emarsysHelper = $emarsysHelper;
        $this->registry = $registry;
        $this->contactModel = $contactModel;
        $this->storeManager = $storeManager;
        $this->customerResourceModel = $customerResourceModel;
        $this->customerFactory = $customerFactory;
    }

    /**
     * @param Observer $observer
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(Observer $observer)
    {
        try {
            /** @var $customerAddress Address */
            $customerAddress = $observer->getEvent()->getCustomerAddress();
            if (!$customerAddress) {
                return;
            }
            $customer = $customerAddress->getCustomer();
            $customerObj = $this->customerFactory->create()->load($customer->getId());

            $customerId = $customerObj->getEntityId();
            $websiteId = $customerObj->getWebsiteId();

            if (!$this->emarsysHelper->isContactsSynchronizationEnable($websiteId)) {
                return;
            }

            $defaultBillingId = $customerObj->getDefaultBilling();
            $defaultShippingId = $customerObj->getDefaultShipping();

            if (!empty($defaultBillingId) || !empty($defaultShippingId)) {
                if (!in_array($customerAddress->getId(), [$defaultBillingId, $defaultShippingId])) {
                    return;
                }
            }

            $storeId = $customerObj->getStoreId();

            $customerVar = 'create_customer_variable_' . $customerId;
            if ($this->registry->registry($customerVar) == 'created') {
                return;
            }
            $this->contactModel->syncContact($customer, $websiteId, $storeId, 0, $customerAddress);
            $this->registry->register($customerVar, 'created');
        } catch (\Exception $e) {
            $this->emarsysHelper->addErrorLog(
                EmarsysHelper::LOG_MESSAGE_CUSTOMER,
                $e->getMessage(),
                $this->storeManager->getStore()->getId(),
                'AfterAddressSaveObserver::execute()'
            );
        }
    }
}
