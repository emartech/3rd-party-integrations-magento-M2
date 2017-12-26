<?php
/**
 * @category   Emarsys
 * @package    Emarsys_Emarsys
 * @copyright  Copyright (c) 2017 Emarsys. (http://www.emarsys.net/)
 */
namespace Emarsys\Emarsys\Plugin\Checkout\Model\Checkout;

/**
 * Class LayoutProcessor
 * @package Emarsys\Emarsys\Plugin\Checkout\Model\Checkout
 */
class LayoutProcessor
{
    public function afterProcess(
        \Magento\Checkout\Block\Checkout\LayoutProcessor $subject,
        array $jsLayout
    ) {
        $flag = 0;
        $om = \Magento\Framework\App\ObjectManager::getInstance();
        $scopeConfigInterface = $om->create('\Magento\Framework\App\Config\ScopeConfigInterface');
        $storeManagerInterface = $om->create('\Magento\Store\Model\StoreManagerInterface');
        $storeId = $storeManagerInterface->getStore()->getId();
        $storeCode = $storeManagerInterface->getStore()->getCode();
        $websiteId = $storeManagerInterface->getStore()->getWebsiteId();
        $newsLetterConfValue = $scopeConfigInterface->getValue('opt_in/subscription_checkout_process/newsletter_sub_checkout_yes_no', $storeCode, $websiteId);
        $customerSession = $om->get('Magento\Customer\Model\Session');
        $subscriberFactory = $om->create('Magento\Newsletter\Model\SubscriberFactory');
        if ($customerSession->isLoggedIn()) {
            $customerEmail = $customerSession->getCustomer()->getEmail();
            $subColl = $subscriberFactory->create()->getCollection()->addFieldToFilter('subscriber_email', $customerEmail)->addFieldToFilter('subscriber_status', 1);
            if (count($subColl->getData())) {
                $flag++;
            }
        }
        if ((!$customerSession->isLoggedIn() || $flag < 1) && $newsLetterConfValue) {
            $jsLayout['components']['checkout']['children']['steps']['children']['shipping-step']['children']
            ['shippingAddress']['children']['shipping-address-fieldset']['children']['subscribe'] = [
                'component' => 'Magento_Ui/js/form/element/abstract',
                'config' => [
                    'customScope' => 'shippingAddress',
                    'template' => 'ui/form/field',
                    'elementTmpl' => 'ui/form/element/checkbox',
                    'options' => [],
                    'id' => 'subscribe'
                ],
                'dataScope' => 'shippingAddress.subscribe',
                'label' => 'Sign Up for Newsletter',
                'provider' => 'checkoutProvider',
                'visible' => true,
                'validation' => [],
                'sortOrder' => 250,
                'id' => 'subscribe',
                'value' => 'subscription'
            ];
        }

        return $jsLayout;
    }
}
