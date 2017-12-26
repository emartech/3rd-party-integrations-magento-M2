<?php
/**
 * @category   Emarsys
 * @package    Emarsys_Emarsys
 * @copyright  Copyright (c) 2017 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Model\Observer;

use Psr\Log\LoggerInterface;
use Magento\Framework\Event\ObserverInterface;

class RealTimeAdminSubscriber implements ObserverInterface
{
    private $logger;

    protected $customerFactory;

    protected $customerResourceModel;

    public function __construct(
        LoggerInterface $logger,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Emarsys\Emarsys\Model\Api\Subscriber $subscriberModel,
        \Magento\Framework\App\Request\Http $request,
        \Emarsys\Emarsys\Helper\Data $dataHelper,
        \Emarsys\Emarsys\Model\ResourceModel\Customer $customerResourceModel
    ) {
    
        $this->logger = $logger;
        $this->subscriberModel = $subscriberModel;
        $this->_storeManager = $storeManager;
        $this->dataHelper = $dataHelper;
        $this->customerResourceModel = $customerResourceModel;
        $this->_request = $request;
        $this->customerFactory = $customerFactory;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $pageHandle = $this->_request->getFullActionName();
        $subscriberId = $observer->getEvent()->getSubscriber()->getId();
        $storeId = $observer->getEvent()->getSubscriber()->getStoreId();
        $websiteId = $this->_storeManager->getStore($storeId)->getWebsiteId();
        $realtimeStatus = $this->customerResourceModel->getDataFromCoreConfig('contacts_synchronization/emarsys_emarsys/realtime_sync', \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE,$websiteId);
        if ($realtimeStatus == 1) {
            $frontendFlag = '';
            $result = $this->subscriberModel->syncSubscriber($subscriberId, $storeId, $frontendFlag, $pageHandle, $websiteId);
        } else {
            $this->dataHelper->syncFail($subscriberId, $websiteId, $storeId, 0, 2);
        }
    }
}
