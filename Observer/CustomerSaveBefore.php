<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */
namespace Emarsys\Emarsys\Observer;

use Emarsys\Emarsys\Helper\Data as EmarsysHelper;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Model\CustomerFactory;

/**
 * Class CustomerSaveBefore
 */
class CustomerSaveBefore implements ObserverInterface
{
    /**
     * @var EmarsysHelper
     */
    protected $emarsysHelper;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var CustomerFactory
     */
    protected $customerFactory;

    /**
     * CustomerSaveBefore constructor.
     * @param EmarsysHelper $emarsysHelper
     * @param StoreManagerInterface $storeManager
     * @param CustomerFactory $customerFactory
     */
    public function __construct(
        EmarsysHelper $emarsysHelper,
        StoreManagerInterface $storeManager,
        CustomerFactory $customerFactory
    ) {
        $this->emarsysHelper = $emarsysHelper;
        $this->storeManager = $storeManager;
        $this->customerFactory = $customerFactory;
    }

    public function execute(Observer $observer)
    {
        try {
            $customer = $observer->getEvent()->getCustomer();
            if ($customer->getId()) {
                $customer->setOrigData('customer_email', $customer->getEmail());
            } else {
                $customer->setOrigData('NewCustomerCheck', true);
            }
        } catch (\Exception $e) {
            $this->emarsysHelper->addErrorLog(
                EmarsysHelper::LOG_MESSAGE_CUSTOMER,
                $e->getMessage(),
                $this->storeManager->getStore()->getId(),
                'GetCustomerBeforeSave Observer'
            );
        }
    }
}
