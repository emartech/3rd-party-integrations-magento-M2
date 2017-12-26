<?php

namespace Emarsys\Emarsys\Model;

class Subscriber extends \Magento\Newsletter\Model\Subscriber
{

    public function subscribe($email)
    {
        $this->loadByEmail($email);
        $handle = '';
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $emarsysHelper = $objectManager->get('\Emarsys\Emarsys\Helper\Data');
        if($emarsysHelper->isEmarsysEnabled()=='false'){
            parent::subscribe($email);
        }
        $request = $objectManager->get('\Magento\Framework\App\Request\Http');
        $handle = $request->getFullActionName();
        if (!$this->getId()) {
            $this->setSubscriberConfirmCode($this->randomSequence());
        }
        $isConfirmNeed = $this->_scopeConfig->getValue(
            self::XML_PATH_CONFIRMATION_FLAG,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        ) == 1 ? true : false;

        if ($this->_scopeConfig->getValue('opt_in/optin_enable/enable_optin', \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITES)) {
            if ($handle == 'checkout_success_page') {
                $optInType = $this->_scopeConfig->getValue('opt_in/subscription_checkout_process/opt_in_strategy');  // return single / double opt-in
                if ($optInType == 'singleOptIn') {
                    $isConfirmNeed = false;
                } elseif ($optInType == 'doubleOptIn') {
                    $isConfirmNeed = true;
                }
            } elseif ($handle == 'customer_account_createpost') {
                $optInType = $this->_scopeConfig->getValue('opt_in/subscription_customer_homepage/opt_in_strategy');  // return single / double opt-in
                if ($optInType == 'singleOptIn') {
                    $isConfirmNeed = false;
                } elseif ($optInType == 'doubleOptIn') {
                    $isConfirmNeed = true;
                }
            } else { //$handle =='newsletter_subscriber_new'
                $optInType = $this->_scopeConfig->getValue('opt_in/subscription_newsletter_everypage/opt_in_strategy');  // return single / double opt-in
                if ($optInType == 'singleOptIn') {
                    $isConfirmNeed = false;
                } elseif ($optInType == 'doubleOptIn') {
                    $isConfirmNeed = true;
                }
            }
        }
        $isOwnSubscribes = false;

        $isSubscribeOwnEmail = $this->_customerSession->isLoggedIn()
            && $this->_customerSession->getCustomerDataObject()->getEmail() == $email;

        if (!$this->getId() || $this->getStatus() == self::STATUS_UNSUBSCRIBED
            || $this->getStatus() == self::STATUS_NOT_ACTIVE
        ) {
            if ($isConfirmNeed === true) {
                // if user subscribes own login email - confirmation is not needed
                $isOwnSubscribes = $isSubscribeOwnEmail;
                if ($isOwnSubscribes == true) {
                    $this->setStatus(self::STATUS_SUBSCRIBED);
                } else {
                    $this->setStatus(self::STATUS_NOT_ACTIVE);
                }
            } else {
                $this->setStatus(self::STATUS_SUBSCRIBED);
            }
            $this->setSubscriberEmail($email);
        }

        if ($isSubscribeOwnEmail) {
            try {
                $customer = $this->customerRepository->getById($this->_customerSession->getCustomerId());
                $this->setStoreId($customer->getStoreId());
                $this->setCustomerId($customer->getId());
            } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                $this->setStoreId($this->_storeManager->getStore()->getId());
                $this->setCustomerId(0);
            }
        } else {
            $this->setStoreId($this->_storeManager->getStore()->getId());
            $this->setCustomerId(0);
        }

        $this->setStatusChanged(true);

        try {
            $this->save();
            if ($isConfirmNeed === true
                && $isOwnSubscribes === false
            ) {
                $this->sendConfirmationRequestEmail();
            } else {
                $this->sendConfirmationSuccessEmail();
            }
            return $this->getStatus();
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
