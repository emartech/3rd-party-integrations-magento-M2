<?php
/**
 * @category  Emarsys
 * @package   Emarsys_Emarsys
 * @copyright Copyright (c) 2020 Emarsys. (http://www.emarsys.net/)
 */

namespace Emarsys\Emarsys\Plugin\Checkout\Model\Checkout;

use Emarsys\Emarsys\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Model\Session;
use Magento\Newsletter\Model\Subscriber;

/**
 * Class LayoutProcessor
 */
class LayoutProcessor
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfigInterface;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManagerInterface;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var Subscriber
     */
    protected $subscriber;

    /**
     * LayoutProcessor constructor.
     *
     * @param ScopeConfigInterface $scopeConfigInterface
     * @param StoreManagerInterface $storeManagerInterface
     * @param Session $session
     * @param Subscriber $subscriber
     */
    public function __construct(
        ScopeConfigInterface $scopeConfigInterface,
        StoreManagerInterface $storeManagerInterface,
        Session $session,
        Subscriber $subscriber
    ) {
        $this->scopeConfigInterface = $scopeConfigInterface;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->session = $session;
        $this->subscriber = $subscriber;
    }

    /**
     * @param \Magento\Checkout\Block\Checkout\LayoutProcessor $processor
     * @param array $jsLayout
     * @return array
     * @throws NoSuchEntityException
     */
    public function afterProcess(\Magento\Checkout\Block\Checkout\LayoutProcessor $processor, $jsLayout)
    {
        $store = $this->storeManagerInterface->getStore();

        $isEnable = $store->getConfig(Data::XPATH_EMARSYS_ENABLED);
        $newsLetterConfValue = $store->getConfig(Data::XPATH_OPTIN_SUBSCRIPTION_CHECKOUT);

        if (!$isEnable || !$newsLetterConfValue) {
            return $jsLayout;
        }

        $subscribed = false;

        if ($this->session->isLoggedIn()) {
            $customerEmail = $this->session->getCustomer()->getEmail();
            $subColl = $this->subscriber->loadByEmail($customerEmail);
            if ($subColl->getId()) {
                $subscribed = true;
            }
        }

        if (!$this->session->isLoggedIn() || !$subscribed) {
            if (isset($jsLayout['components']['checkout']['children']['steps']['children']['shipping-step']['children']
                ['shippingAddress']['children']['shipping-address-fieldset']['children']
            )) {
                $jsLayout['components']['checkout']['children']['steps']['children']['shipping-step']['children']
                ['shippingAddress']['children']['shipping-address-fieldset']['children']['emarsys_subscriber'] = [
                    'component' => 'Emarsys_Emarsys/js/view/newsletter_sub_checkout',
                    'dataScope' => 'shippingAddress.emarsys_subscriber',
                    'provider' => 'checkoutProvider',
                    'visible' => true,
                    'validation' => [],
                    'sortOrder' => 2500,
                    'id' => 'emarsys_subscriber',
                    'value' => 1,
                ];
            }
        }

        return $jsLayout;
    }
}
