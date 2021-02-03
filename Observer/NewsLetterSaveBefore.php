<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Newsletter\Model\SubscriberFactory;

/**
 * Class NewsLetterSaveBefore
 */
class NewsLetterSaveBefore implements ObserverInterface
{
    /**
     * NewsLetterSaveBefore constructor.
     * @param StoreManagerInterface $storeManager
     * @param SubscriberFactory $subscriberModel
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        SubscriberFactory $subscriberModel
    ) {
        $this->_storeManager = $storeManager;
        $this->subscriberModel = $subscriberModel;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $subscriber = $observer->getSubscriber();
        $subscriberId = $subscriber->getSubscriberId();
        $oldSubscriptionStatus = $this->subscriberModel->create()->load($subscriberId)->getSubscriberStatus();
        $currentSubscriptionStatus = $observer->getSubscriber()->getSubscriberStatus();
        $subscriber->setOrigData('subscriber_status', $oldSubscriptionStatus);
        $oldSubscriptionEmail = $this->subscriberModel->create()->load($subscriberId)->getEmail();
        $subscriber->setOrigData('subscriber_email', $oldSubscriptionEmail);

        if ($oldSubscriptionStatus != $currentSubscriptionStatus) {
            $subscriber['change_status_at'] = (date("Y-m-d H:i:s", time()));
        }
    }
}
