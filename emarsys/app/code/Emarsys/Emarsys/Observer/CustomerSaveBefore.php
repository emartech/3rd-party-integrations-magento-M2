<?php
/**
 * @category   Emarsys
 * @package    Emarsys_Emarsys
 * @copyright  Copyright (c) 2017 Emarsys. (http://www.emarsys.net/)
 */
namespace Emarsys\Emarsys\Observer;

use Emarsys\Emarsys\Model\Logs;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Registry as Registry;

/**
 * Class CustomerSaveBefore
 * @package Emarsys\Emarsys\Observer
 */
class CustomerSaveBefore implements ObserverInterface
{
    /**
     * @var Logs
     */
    protected $emarsysLogs;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var Registry
     */
    protected $_registry;

    /**
     * CustomerSaveBefore constructor.
     * @param Logs $emarsysLogs
     * @param StoreManagerInterface $storeManager
     * @param Registry $registry
     */
    public function __construct(
        Logs $emarsysLogs,
        StoreManagerInterface $storeManager,
        Registry $registry
    ) {
        $this->emarsysLogs = $emarsysLogs;
        $this->storeManager = $storeManager;
        $this->_registry = $registry;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        try {
            $data = $observer->getEvent();
            $customer = $data->getCustomer();
            $storeId = $customer->getStoreId();
            $websiteId = $customer->getWebsiteId();
            $_customerId = $customer->getId();

            if (isset($_customerId)) {
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $customerObj = $objectManager->create('\Magento\Customer\Model\Customer')
                    ->load($_customerId);
                $customerEmailSaved = $customerObj->getEmail();
                $observer->getEvent()->getCustomer()->setOrigData('customer_email', $customerEmailSaved);
            } else {
                $observer->getEvent()->getCustomer()->setOrigData('NewCustomerCheck', true);
            }
        } catch (\Exception $e) {
            $this->emarsysLogs->addErrorLog(
                $e->getMessage(),
                $this->storeManager->getStore()->getId(),
                'GetCustomerBeforeSave Observer'
            );
        }
    }
}
