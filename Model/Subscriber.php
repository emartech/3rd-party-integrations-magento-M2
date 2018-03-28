<?php
/**
 * @category   Emarsys
 * @package    Emarsys_Emarsys
 * @copyright  Copyright (c) 2017 Emarsys. (http://www.emarsys.net/)
 */
namespace Emarsys\Emarsys\Model;

use Magento\Store\Model\ScopeInterface;
use Emarsys\Emarsys\Helper\Data\Proxy as EmarsysHelperData;

/**
 * Class Subscriber
 * @package Emarsys\Emarsys\Model
 */
class Subscriber extends \Magento\Newsletter\Model\Subscriber
{
    /**
     * @var EmarsysHelperData
     */
    protected $emarsysHelperData;

    /**
     * Subscriber constructor.
     * @param EmarsysHelperData $emarsysHelperData
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Newsletter\Helper\Data $newsletterData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository
     * @param \Magento\Customer\Api\AccountManagementInterface $customerAccountManagement
     * @param \Magento\Framework\Translate\Inline\StateInterface $inlineTranslation
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param \Magento\Framework\Stdlib\DateTime\DateTime|null $dateTime
     * @param array $data
     */
    public function __construct
    (
        EmarsysHelperData $emarsysHelperData,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Newsletter\Helper\Data $newsletterData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Customer\Api\AccountManagementInterface $customerAccountManagement,
        \Magento\Framework\Translate\Inline\StateInterface $inlineTranslation,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        \Magento\Framework\Stdlib\DateTime\DateTime $dateTime = null,
        array $data = []
    ) {
        $this->emarsysHelperData = $emarsysHelperData;
        parent::__construct(
            $context,
            $registry,
            $newsletterData,
            $scopeConfig,
            $transportBuilder,
            $storeManager,
            $customerSession,
            $customerRepository,
            $customerAccountManagement,
            $inlineTranslation,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * @param string $email
     * @return int|void
     * @throws \Exception
     */
    public function subscribe($email)
    {
        $websiteId = $this->_storeManager->getStore()->getWebsiteId();
        if ($this->emarsysHelperData->isEmarsysEnabled($websiteId) == 'false') {
            return parent::subscribe($email);
        } else {
           return $this->subscribeByEmarsys($email);
        }
    }

    /**
     * @param $email
     * @return int|void
     * @throws \Exception
     */
    public function subscribeByEmarsys($email)
    {
        $websiteId = $this->_storeManager->getStore()->getWebsiteId();
        $this->loadByEmail($email);

        if (!$this->getId()) {
            $this->setSubscriberConfirmCode($this->randomSequence());
        }

        $isConfirmNeed = $this->_scopeConfig->getValue(
            self::XML_PATH_CONFIRMATION_FLAG,
            ScopeInterface::SCOPE_STORE
        ) == 1 ? true : false;

        if ($optEnable = $this->_scopeConfig->getValue('opt_in/optin_enable/enable_optin', ScopeInterface::SCOPE_WEBSITES, $websiteId)) {
            //return single / double opt-in
            $optInType = $this->_scopeConfig->getValue('opt_in/optin_enable/opt_in_strategy', ScopeInterface::SCOPE_WEBSITES, $websiteId);
            if ($optInType == 'singleOptIn') {
                $isConfirmNeed = false;
            } elseif ($optInType == 'doubleOptIn') {
                $isConfirmNeed = true;
            }
        }
        $isOwnSubscribes = false;
        //It will return boolean value, If customer is logged in and email Id is the same.
        $isSubscribeOwnEmail = $this->_customerSession->isLoggedIn()
            && $this->_customerSession->getCustomerDataObject()->getEmail() == $email;

        $optinForcedConfirmation = $this->emarsysHelperData->isOptinForcedConfirmationEnabled($websiteId);
        $isOwnSubscribes = $isSubscribeOwnEmail;

        if (!$this->getId() || $this->getStatus() == self::STATUS_UNSUBSCRIBED
            || $this->getStatus() == self::STATUS_NOT_ACTIVE) {
            if ($isConfirmNeed === true && $optinForcedConfirmation == true) {
                $this->setStatus(self::STATUS_NOT_ACTIVE);
            } elseif ($isConfirmNeed === true && $optinForcedConfirmation == false) {
                $this->setStatus(self::STATUS_NOT_ACTIVE);
            } else {
                $this->setStatus(self::STATUS_SUBSCRIBED);
            }
        } elseif ($this->getId() && $this->getStatus() == self::STATUS_SUBSCRIBED) {
            // Who have subID and status subscribed trying for 2nd time or more
            if ($isConfirmNeed === true && $optinForcedConfirmation == true) {
                $this->setStatus(self::STATUS_NOT_ACTIVE);
            } elseif ($isConfirmNeed === true && $optinForcedConfirmation == false) {
                $this->setStatus(self::STATUS_SUBSCRIBED);
            } else {
                $this->setStatus(self::STATUS_SUBSCRIBED);
            }
        } elseif ($this->getId() && ($this->getStatus() == self::STATUS_UNSUBSCRIBED) ||
            $this->getStatus() == self::STATUS_NOT_ACTIVE) {
            // Who have subID and status UnSubscribed or not active trying for 2nd time or more
            if ($isConfirmNeed === true && $optinForcedConfirmation == true) { //Double optin
                $this->setStatus(self::STATUS_NOT_ACTIVE);
            } elseif ($isConfirmNeed === true && $optinForcedConfirmation == false) { //Double optin
                $this->setStatus(self::STATUS_NOT_ACTIVE);
            } else {
                $this->setStatus(self::STATUS_SUBSCRIBED);
            }
        } elseif ($this->getId() && $isOwnSubscribes) {
            //loged in customer with subscription
            if ($isConfirmNeed === true && $optinForcedConfirmation == true) { //Double optin
                $this->setStatus(self::STATUS_NOT_ACTIVE);
            } elseif ($isConfirmNeed === true && $optinForcedConfirmation == false) { //Double optin
                $this->setStatus(self::STATUS_SUBSCRIBED);
            } else {
                $this->setStatus(self::STATUS_SUBSCRIBED);
            }
        }
        if ($isOwnSubscribes) {
            //loged in customer with subscription
            if ($isConfirmNeed === true && $optinForcedConfirmation == true) { //Double optin
                $this->setStatus(self::STATUS_NOT_ACTIVE);
            } elseif ($isConfirmNeed === true && $optinForcedConfirmation == false) { //Double optin
                $this->setStatus(self::STATUS_SUBSCRIBED);
            } else {
                $this->setStatus(self::STATUS_SUBSCRIBED);
            }
        }
        $this->setSubscriberEmail($email);

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
            if($this->getStatus() == self::STATUS_NOT_ACTIVE) {
                $this->sendConfirmationRequestEmail();
            } elseif ($this->getStatus() == self::STATUS_SUBSCRIBED) {
                $this->sendConfirmationSuccessEmail();
            }
            return $this->getStatus();
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
