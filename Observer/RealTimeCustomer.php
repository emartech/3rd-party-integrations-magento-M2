<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Observer;

use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Registry;
use Magento\Newsletter\Model\Subscriber;
use Magento\Store\Model\StoreManagerInterface;
use Emarsys\Emarsys\Model\Api\Contact;
use Emarsys\Emarsys\Model\ResourceModel\Customer;
use Emarsys\Emarsys\Helper\Data as EmarsysHelper;

/**
 * Class RealTimeCustomer
 */
class RealTimeCustomer implements ObserverInterface
{
    /**
     * @var EmarsysHelper
     */
    protected $emarsysHelper;

    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var Contact
     */
    private $contactModel;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Customer
     */
    private $customerResourceModel;

    /**
     * @var CustomerFactory
     */
    private $customerFactory;

    /**
     * @var Subscriber
     */
    protected $subscriber;

    /**
     * RealTimeCustomer constructor.
     *
     * @param CustomerFactory $customerFactory
     * @param Registry $registry
     * @param StoreManagerInterface $storeManager
     * @param Contact $contactModel
     * @param EmarsysHelper $emarsysHelper
     * @param Customer $customerResourceModel
     * @param Subscriber $subscriber
     */
    public function __construct(
        CustomerFactory $customerFactory,
        Registry $registry,
        StoreManagerInterface $storeManager,
        Contact $contactModel,
        EmarsysHelper $emarsysHelper,
        Customer $customerResourceModel,
        Subscriber $subscriber
    ) {
        $this->emarsysHelper = $emarsysHelper;
        $this->registry = $registry;
        $this->contactModel = $contactModel;
        $this->storeManager = $storeManager;
        $this->customerResourceModel = $customerResourceModel;
        $this->customerFactory = $customerFactory;
        $this->subscriber = $subscriber;
    }

    /**
     * @param Observer $observer
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(Observer $observer)
    {
        /** @var \Magento\Customer\Model\Customer $customer */
        $customer = $observer->getEvent()->getCustomer();
        $customerId = $customer->getId();
        $storeId = $customer->getStoreId();
        $store = $this->storeManager->getStore($storeId);
        $websiteId = $customer->getWebsiteId();

        if (!$this->emarsysHelper->isContactsSynchronizationEnable($websiteId)) {
            return;
        }

        try {
            if ($store->getConfig(EmarsysHelper::XPATH_EMARSYS_REALTIME_SYNC) == 1) {
                $customerVar = 'create_customer_variable_' . $customerId;
                if ($this->registry->registry($customerVar) == 'created') {
                    return;
                }
                $this->contactModel->syncContact($customer, $websiteId, $storeId);
                $this->registry->register($customerVar, 'created');
            } else {
                $this->emarsysHelper->syncFail($customerId, $websiteId, $storeId, 0, 1);
            }
        } catch (\Exception $e) {
            $this->emarsysHelper->syncFail($customerId, $websiteId, $storeId, 0, 1);
            $this->emarsysHelper->addErrorLog(
                EmarsysHelper::LOG_MESSAGE_CUSTOMER,
                $e->getMessage(),
                $this->storeManager->getStore()->getId(),
                'RealTimeCustomer::execute()'
            );
        }
    }
}
