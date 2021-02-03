<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Plugin;

use Magento\Newsletter\Model\Subscriber as Sub;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Model\Session;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Emarsys\Emarsys\Helper\Data as EmarsysHelper;

/**
 * Class Subscriber
 *
 */
class Subscriber
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
     * Customer session
     *
     * @var Session
     */
    protected $customerSession;


    /**
     * Customer
     *
     * @var CustomerRepositoryInterface
     */
    protected $customer;

    /**
     * Subscriber constructor.
     *
     * @param EmarsysHelper $emarsysHelper
     * @param StoreManagerInterface $storeManager
     * @param Session $customerSession
     * @param CustomerRepositoryInterface $customer
     */
    public function __construct(
        EmarsysHelper $emarsysHelper,
        StoreManagerInterface $storeManager,
        Session $customerSession,
        CustomerRepositoryInterface $customer
    ) {
        $this->emarsysHelper = $emarsysHelper;
        $this->storeManager = $storeManager;
        $this->customerSession = $customerSession;
        $this->customer = $customer;
    }

    /**
     * @param Sub $subscriber
     * @param callable $proceed
     * @param $email
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function aroundSubscribe(
        Sub $subscriber,
        callable $proceed,
        $email
    ) {
        if (!$this->emarsysHelper->isContactsSynchronizationEnable()) {
            return $proceed($email);
        }
        /** @var \Magento\Store\Model\Store $store */
        $store = $this->storeManager->getStore();
        $subscriber->loadByEmail($email);

        if (!$subscriber->getId()) {
            $subscriber->setSubscriberConfirmCode($subscriber->randomSequence());
        }

        $isConfirmNeed = $store->getConfig(Sub::XML_PATH_CONFIRMATION_FLAG) == 1 ? true : false;
        if ($optEnable = $store->getConfig(EmarsysHelper::XPATH_OPTIN_ENABLED)) {
            //return single / double opt-in
            $optInType = $store->getConfig(EmarsysHelper::XPATH_OPTIN_EVERYPAGE_STRATEGY);
            if ($optInType == 'singleOptIn') {
                $isConfirmNeed = false;
            } elseif ($optInType == 'doubleOptIn') {
                $isConfirmNeed = true;
            }
        }

        //It will return true, If customer is logged in and email is the same.
        $isSubscribeOwnEmail = $this->customerSession->isLoggedIn()
            && $this->customerSession->getCustomerDataObject()->getEmail() == $email;

        if (!$subscriber->getId()
            || $subscriber->getStatus() == Sub::STATUS_UNSUBSCRIBED
            || $subscriber->getStatus() == Sub::STATUS_NOT_ACTIVE
        ) {
            if ($isConfirmNeed) {
                $subscriber->setStatus(Sub::STATUS_NOT_ACTIVE);
            } else {
                $subscriber->setStatus(Sub::STATUS_SUBSCRIBED);
            }
        } elseif ($subscriber->getId() && $subscriber->getStatus() == Sub::STATUS_SUBSCRIBED) {
            if ($isConfirmNeed) {
                $subscriber->setStatus(Sub::STATUS_NOT_ACTIVE);
            } else {
                $subscriber->setStatus(Sub::STATUS_SUBSCRIBED);
            }
        }

        $subscriber->setSubscriberEmail($email);
        $subscriber->setStoreId($store->getId());
        $subscriber->setCustomerId(0);
        if ($isSubscribeOwnEmail) {
            $customer = $this->customer->getById($this->customerSession->getCustomerId());
            if ($customer->getId()) {
                $subscriber->setStoreId($customer->getStoreId());
            }
            if ($customer->getStoreId()) {
                $subscriber->setCustomerId($customer->getId());
            }
        }
        $subscriber->setStatusChanged(true);

        try {
            $subscriber->save();
            if ($subscriber->getStatus() == Sub::STATUS_NOT_ACTIVE) {
                $subscriber->sendConfirmationRequestEmail();
            } elseif ($subscriber->getStatus() == Sub::STATUS_SUBSCRIBED) {
                $subscriber->sendConfirmationSuccessEmail();
            }

            return $subscriber->getStatus();
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
