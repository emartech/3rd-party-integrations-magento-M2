<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Observer;

use Magento\Customer\Model\Session;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Request\Http;
use Magento\Store\Model\StoreManagerInterface;
use Emarsys\Emarsys\Model\Api\Subscriber;
use Emarsys\Emarsys\Model\ResourceModel\Customer;
use Emarsys\Emarsys\Helper\Data as EmarsysHelper;

/**
 * Class RealTimeSubscriber
 */
class RealTimeSubscriber implements ObserverInterface
{
    /**
     * @var Subscriber
     */
    protected $subscriberModel;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var Customer
     */
    protected $customerResourceModel;

    /**
     * @var Http
     */
    protected $request;

    /**
     * @var EmarsysHelper
     */
    protected $emarsysHelper;

    /**
     * @var Session
     */
    protected $customerSession;

    /**
     * RealTimeSubscriber constructor.
     *
     * @param StoreManagerInterface $storeManager
     * @param Subscriber $subscriberModel
     * @param Customer $customerResourceModel
     * @param Http $request
     * @param EmarsysHelper $emarsysHelper
     * @param Session $customerSession
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        Subscriber $subscriberModel,
        Customer $customerResourceModel,
        Http $request,
        EmarsysHelper $emarsysHelper,
        Session $customerSession
    ) {
        $this->subscriberModel = $subscriberModel;
        $this->storeManager = $storeManager;
        $this->customerResourceModel = $customerResourceModel;
        $this->request = $request;
        $this->emarsysHelper = $emarsysHelper;
        $this->customerSession = $customerSession;
    }

    /**
     * @param Observer $observer
     * @return bool|void
     * @throws \Exception
     */
    public function execute(Observer $observer)
    {
        $event = $observer->getEvent();
        $subscriber = $event->getSubscriber();
        $store = $this->storeManager->getStore($subscriber->getStoreId());
        $subscriberId = $subscriber->getId();
        $storeId = $store->getStoreId();
        $websiteId = $store->getWebsiteId();

        if ($subscriber->getEmarsysNoExport()
            || !$this->emarsysHelper->isContactsSynchronizationEnable($websiteId)
        ) {
            return true;
        }

        $this->customerSession->setWebExtendCustomerEmail($subscriber->getSubscriberEmail());
        $logMessage = 'Created Subscriber in Emarsys';
        try {
            $createSubscriber = $this->emarsysHelper->realtimeTimeBasedOptinSync($subscriber, $logMessage);

            if ($createSubscriber) {
                $result = $this->subscriberModel->syncSubscriber($subscriberId, $storeId);
                $subscriber->setEmarsysNoExport(true);
                return $result;
            }

            return true;
        } catch (\Exception $e) {
            $this->emarsysHelper->syncFail($subscriberId, $websiteId, $storeId, 0, 2);
            $this->emarsysHelper->addErrorLog(
                EmarsysHelper::LOG_MESSAGE_SUBSCRIBER,
                $e->getMessage(),
                $storeId,
                'RealTimeSubscriber::execute'
            );
        }
        return false;
    }
}
